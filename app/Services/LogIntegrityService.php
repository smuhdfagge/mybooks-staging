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
     * Verify a range of log entries, checking both hashes and chain links.
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public static function verifyChain(int $tenantId, int $limit = 1000): array
    {
        $errors = [];

        $logs = ActivityLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('integrity_hash')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $previousHash = null;

        foreach ($logs as $log) {
            // Verify individual hash
            if (! static::verify($log)) {
                $errors[] = "Entry #{$log->id}: integrity hash mismatch — possible tampering.";
            }

            // Verify chain link
            if ($previousHash !== null && $log->previous_hash !== $previousHash) {
                $prevId = $log->id - 1;
                $errors[] = "Entry #{$log->id}: chain break — previous_hash does not match entry #{$prevId}.";
            }

            $previousHash = $log->integrity_hash;
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
