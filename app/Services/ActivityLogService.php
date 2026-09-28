<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    /**
     * Log a login event
     */
    public static function logLogin($user): ActivityLog
    {
        return ActivityLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => ActivityLog::ACTION_LOGIN,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => "User '{$user->name}' logged in",
        ]);
    }

    /**
     * Log a logout event
     */
    public static function logLogout($user): ActivityLog
    {
        return ActivityLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => ActivityLog::ACTION_LOGOUT,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => "User '{$user->name}' logged out",
        ]);
    }

    /**
     * Log a failed login attempt
     */
    public static function logFailedLogin(string $email): ActivityLog
    {
        $log = new ActivityLog([
            'tenant_id' => null,
            'user_id' => null,
            'user_name' => $email,
            'action' => ActivityLog::ACTION_LOGIN_FAILED,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => "Failed login attempt for '{$email}'",
        ]);
        $log->skipTenantGuard = true;
        $log->save();

        return $log;
    }

    /**
     * Log a password reset
     */
    public static function logPasswordReset($user): ActivityLog
    {
        return ActivityLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => ActivityLog::ACTION_PASSWORD_RESET,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => "Password was reset for user '{$user->name}'",
        ]);
    }

    /**
     * Log data export
     */
    public static function logExport(string $type, array $filters = []): ActivityLog
    {
        $user = Auth::user();

        return ActivityLog::create([
            'tenant_id' => $user?->tenant_id,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => ActivityLog::ACTION_EXPORTED,
            'model_type' => $type,
            'new_values' => $filters,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => "Exported {$type} data",
        ]);
    }

    /**
     * Log data import
     */
    public static function logImport(string $type, int $count): ActivityLog
    {
        $user = Auth::user();

        return ActivityLog::create([
            'tenant_id' => $user?->tenant_id,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => ActivityLog::ACTION_IMPORTED,
            'model_type' => $type,
            'new_values' => ['count' => $count],
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => "Imported {$count} {$type} records",
        ]);
    }

    /**
     * Log a backup creation
     */
    public static function logBackup(array $includedData, string $format): ActivityLog
    {
        $user = Auth::user();

        return ActivityLog::create([
            'tenant_id' => $user?->tenant_id,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => ActivityLog::ACTION_BACKUP,
            'model_type' => 'Backup',
            'new_values' => [
                'included_data' => $includedData,
                'format' => $format,
            ],
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => "Full backup created (format: {$format}, includes: ".implode(', ', $includedData).')',
        ]);
    }

    /**
     * Log a custom action
     */
    public static function log(
        string $action,
        ?string $description = null,
        ?string $modelType = null,
        ?int $modelId = null,
        ?string $modelName = null,
        array $properties = []
    ): ActivityLog {
        $user = Auth::user();

        return ActivityLog::create([
            'tenant_id' => $user?->tenant_id,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'model_name' => $modelName,
            'new_values' => ! empty($properties) ? $properties : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => $description,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Security Event Logging
    // ──────────────────────────────────────────────────────────────

    /**
     * Log 2FA enabled
     */
    public static function log2FAEnabled($user): ActivityLog
    {
        return ActivityLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => ActivityLog::ACTION_2FA_ENABLED,
            'model_type' => get_class($user),
            'model_id' => $user->id,
            'model_name' => $user->name,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => "Two-factor authentication enabled for user '{$user->name}'",
        ]);
    }

    /**
     * Log 2FA disabled
     */
    public static function log2FADisabled($user): ActivityLog
    {
        return ActivityLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => ActivityLog::ACTION_2FA_DISABLED,
            'model_type' => get_class($user),
            'model_id' => $user->id,
            'model_name' => $user->name,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => "Two-factor authentication disabled for user '{$user->name}'",
        ]);
    }

    /**
     * Log password change (not reset)
     */
    public static function logPasswordChanged($user): ActivityLog
    {
        return ActivityLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => ActivityLog::ACTION_PASSWORD_CHANGED,
            'model_type' => get_class($user),
            'model_id' => $user->id,
            'model_name' => $user->name,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => "Password changed for user '{$user->name}'",
        ]);
    }

    /**
     * Log role assignment/removal
     */
    public static function logRoleChanged($user, string $role, string $changeType = 'assigned'): ActivityLog
    {
        return ActivityLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => Auth::id() ?? $user->id,
            'user_name' => Auth::user()?->name ?? $user->name,
            'action' => ActivityLog::ACTION_ROLE_CHANGED,
            'model_type' => get_class($user),
            'model_id' => $user->id,
            'model_name' => $user->name,
            'new_values' => ['role' => $role, 'change' => $changeType],
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => "Role '{$role}' {$changeType} for user '{$user->name}'",
        ]);
    }

    /**
     * Log account lockout (too many failed attempts)
     */
    public static function logAccountLocked(string $email, string $reason = 'Too many failed login attempts'): ActivityLog
    {
        $log = new ActivityLog([
            'tenant_id' => null,
            'user_id' => null,
            'user_name' => $email,
            'action' => ActivityLog::ACTION_ACCOUNT_LOCKED,
            'new_values' => ['reason' => $reason],
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => "Account locked for '{$email}': {$reason}",
        ]);
        $log->skipTenantGuard = true;
        $log->save();

        return $log;
    }

    /**
     * Log API token creation
     */
    public static function logApiTokenCreated($user, string $tokenName): ActivityLog
    {
        return ActivityLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => ActivityLog::ACTION_API_TOKEN_CREATED,
            'model_type' => get_class($user),
            'model_id' => $user->id,
            'model_name' => $user->name,
            'new_values' => ['device_name' => $tokenName],
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => "API token created for device '{$tokenName}'",
        ]);
    }

    /**
     * Log API token revocation
     */
    public static function logApiTokenRevoked($user, string $tokenName = 'all'): ActivityLog
    {
        return ActivityLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => ActivityLog::ACTION_API_TOKEN_REVOKED,
            'model_type' => get_class($user),
            'model_id' => $user->id,
            'model_name' => $user->name,
            'new_values' => ['device_name' => $tokenName],
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => "API token revoked for device '{$tokenName}'",
        ]);
    }

    /**
     * Log suspicious activity (e.g., cross-tenant access attempt)
     */
    public static function logSuspiciousActivity(string $description, array $context = []): ActivityLog
    {
        $user = Auth::user();

        $log = new ActivityLog([
            'tenant_id' => $user?->tenant_id,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'anonymous',
            'action' => ActivityLog::ACTION_SUSPICIOUS_ACTIVITY,
            'new_values' => $context,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => $description,
        ]);

        if (! $user?->tenant_id) {
            $log->skipTenantGuard = true;
        }

        $log->save();

        return $log;
    }
}
