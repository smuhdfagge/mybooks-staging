<?php

namespace App\Services;

use App\Models\ActivityLog;

/**
 * Provides HMAC-SHA256 integrity hashing for activity log entries.
 *
 * Each log entry gets:
 *   - `integrity_hash`: HMAC of the entry's payload, keyed with APP_KEY
 *   - `previous_hash`:  integrity_hash of the preceding entry (hash-chain)
 *
 * Verification:
 *   - Recompute the HMAC and compare to stored hash.
 *   - Walk the chain to detect deletions or reordering.
 */
class LogIntegrityService
{
    /**
     * Fields included in the HMAC payload (order matters).
     */
    private const HASH_FIELDS = [
        'id',
        'tenant_id',
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'description',
        'created_at',
        'previous_hash',
    ];

    /**
     * Compute and persist the integrity hash for a freshly created log entry.
     */
    public static function sign(ActivityLog $log): void
    {
        // Get the previous entry's hash for chain continuity
        $previous = ActivityLog::withoutGlobalScopes()
            ->where('id', '<', $log->id)
            ->orderByDesc('id')
            ->value('integrity_hash');

        $log->previous_hash = $previous ?? str_repeat('0', 64);
        $log->integrity_hash = static::computeHash($log);

        // Use query builder to avoid triggering model events / observers
        ActivityLog::withoutGlobalScopes()
            ->where('id', $log->id)
            ->update([
                'integrity_hash' => $log->integrity_hash,
                'previous_hash' => $log->previous_hash,
            ]);
    }

    /**
     * Verify a single entry's integrity hash.
     */
    public static function verify(ActivityLog $log): bool
    {
        if (empty($log->integrity_hash)) {
            return false; // Pre-upgrade entry — no hash to verify
        }

        return hash_equals($log->integrity_hash, static::computeHash($log));
    }

    /**
     * Verify a business's log entries: each entry's own hash, and that the
     * entry it links to is still in the log, earlier than it.
     *
     * sign() links an entry to the entry before it in the whole table,
     * whichever business wrote that one, and two saves at once can link to
     * an older entry still. So the link is checked against the entry it
     * names, not against this business's previous entry: other businesses'
     * entries in between, and simultaneous saves, are not breaks. What is
     * a break is a link to an entry that is gone (deleted) or that comes
     * later. previous_hash is inside the HMAC, so a link can't be changed
     * without the entry's own hash failing too.
     *
     * retention:purge deletes entries past mybooks.retention's
     * activity_log_months each month, so an entry near that age may follow
     * one that was purged: that is not reported.
     *
     * @return array{valid: bool, checked: int, errors: array<string>}
     */
    public static function verifyChain(int $tenantId, int $limit = 1000): array
    {
        $errors = [];
        $genesis = str_repeat('0', 64);

        $logs = ActivityLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('integrity_hash')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        // Where each link points: integrity_hash => id, across every business
        // (only the hashes and ids of other businesses' entries are read).
        $linked = [];
        $links = $logs->pluck('previous_hash')->filter(fn ($h) => $h !== null && $h !== $genesis)->unique()->values();
        foreach ($links->chunk(500) as $chunk) {
            $linked += ActivityLog::withoutGlobalScopes()
                ->whereIn('integrity_hash', $chunk->all())
                ->orderBy('id')
                ->pluck('id', 'integrity_hash')
                ->all();
        }

        $months = (int) (config('mybooks.retention.activity_log_months') ?? 84);
        // Purge runs monthly; an entry that follows a purged one is about as old.
        $purgedBefore = $months > 0 ? now()->subMonths($months)->addMonths(2) : null;

        foreach ($logs as $log) {
            if (! static::verify($log)) {
                $errors[] = "Entry #{$log->id}: integrity hash mismatch — possible tampering.";

                continue; // its link can't be trusted either
            }

            // The first entry signed, or one signed while the entry before it
            // was still being written, starts from the zero hash.
            if ($log->previous_hash === null || $log->previous_hash === $genesis) {
                continue;
            }

            $target = $linked[$log->previous_hash] ?? null;
            if ($target === null) {
                if ($purgedBefore !== null && $log->created_at !== null && $log->created_at->lessThan($purgedBefore)) {
                    continue; // follows an entry retention:purge removed
                }
                $errors[] = "Entry #{$log->id}: chain break — the entry it follows is no longer in the log (deleted?).";
            } elseif ($target >= $log->id) {
                $errors[] = "Entry #{$log->id}: chain break — it follows entry #{$target}, which comes after it.";
            }
        }

        return [
            'valid' => empty($errors),
            'checked' => $logs->count(),
            'errors' => $errors,
        ];
    }

    /**
     * Compute the HMAC-SHA256 hash for a log entry.
     */
    protected static function computeHash(ActivityLog $log): string
    {
        $payload = [];

        foreach (self::HASH_FIELDS as $field) {
            $value = $log->{$field};

            // Normalize arrays to JSON strings for deterministic hashing
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $payload[] = (string) ($value ?? '');
        }

        $data = implode('|', $payload);

        return hash_hmac('sha256', $data, config('app.key'));
    }
}
