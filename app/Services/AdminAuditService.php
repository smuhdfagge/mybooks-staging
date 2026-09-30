<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Audit trail for the platform admin panel (finding S2).
 *
 * Entries go in activity_logs (signed and hash-chained like every other
 * entry) with admin_user_id set and no tenant_id, so they don't show in a
 * business's own activity log. Who, when, what, and the before and after
 * values are all recorded.
 */
class AdminAuditService
{
    /** Fields never written to the audit trail. */
    private const HIDDEN = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    public static function log(
        string $action,
        string $description,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?AdminUser $admin = null,
        ?string $actorLabel = null,
    ): ActivityLog {
        $admin ??= Auth::guard('admin')->user();
        $actor = $admin ? "{$admin->name} ({$admin->email})" : ($actorLabel ?? 'unknown');

        $old = $old === null ? null : self::clean($old);
        $new = $new === null ? null : self::clean($new);

        $log = new ActivityLog([
            'tenant_id' => null,
            'user_id' => null,
            'admin_user_id' => $admin?->id,
            'user_name' => 'Admin: '.$actor,
            'action' => $action,
            'model_type' => $subject ? get_class($subject) : null,
            'model_id' => $subject?->getKey(),
            'model_name' => $subject ? (string) ($subject->getAttribute('name') ?? $subject->getKey()) : null,
            'old_values' => $old,
            'new_values' => $new,
            'changed_fields' => $old !== null && $new !== null ? array_keys($new) : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => "[Admin] {$actor}: {$description}",
        ]);
        $log->skipTenantGuard = true;
        $log->save();

        return $log;
    }

    /**
     * Log a change to a model: only the fields that changed, before and after.
     *
     * @param  array<string, mixed>  $before  the model's attributes before the change
     */
    public static function logChange(string $description, Model $subject, array $before): ?ActivityLog
    {
        $after = $subject->getAttributes();
        $changed = array_keys(array_diff_assoc(
            array_map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v), $after),
            array_map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v), $before),
        ));
        $changed = array_values(array_diff($changed, ['updated_at']));

        if ($changed === []) {
            return null;
        }

        return self::log(
            ActivityLog::ACTION_UPDATED,
            $description,
            $subject,
            array_intersect_key($before, array_flip($changed)),
            array_intersect_key($after, array_flip($changed)),
        );
    }

    private static function clean(array $values): array
    {
        foreach (self::HIDDEN as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = '[hidden]';
            }
        }

        return $values;
    }
}
