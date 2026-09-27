<?php

namespace App\Models;

use App\Services\LogIntegrityService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class ActivityLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'user_name',
        'action',
        'model_type',
        'model_id',
        'model_name',
        'old_values',
        'new_values',
        'changed_fields',
        'ip_address',
        'user_agent',
        'description',
        'integrity_hash',
        'previous_hash',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
    ];

    /**
     * Boot: auto-sign new entries with an HMAC integrity hash.
     */
    protected static function booted(): void
    {
        static::created(function (ActivityLog $log) {
            LogIntegrityService::sign($log);
        });
    }

    // Action constants
    const ACTION_CREATED = 'created';
    const ACTION_UPDATED = 'updated';
    const ACTION_DELETED = 'deleted';
    const ACTION_RESTORED = 'restored';
    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_LOGIN_FAILED = 'login_failed';
    const ACTION_PASSWORD_RESET = 'password_reset';
    const ACTION_EXPORTED = 'exported';
    const ACTION_IMPORTED = 'imported';
    const ACTION_APPROVED = 'approved';
    const ACTION_REJECTED = 'rejected';
    const ACTION_POSTED = 'posted';
    const ACTION_SENT = 'sent';
    const ACTION_PAID = 'paid';
    const ACTION_RELEASED = 'released';
    const ACTION_BACKUP = 'backup';

    // Security events
    const ACTION_2FA_ENABLED = '2fa_enabled';
    const ACTION_2FA_DISABLED = '2fa_disabled';
    const ACTION_PASSWORD_CHANGED = 'password_changed';
    const ACTION_ROLE_CHANGED = 'role_changed';
    const ACTION_PERMISSION_CHANGED = 'permission_changed';
    const ACTION_ACCOUNT_LOCKED = 'account_locked';
    const ACTION_ACCOUNT_UNLOCKED = 'account_unlocked';
    const ACTION_API_TOKEN_CREATED = 'api_token_created';
    const ACTION_API_TOKEN_REVOKED = 'api_token_revoked';
    const ACTION_SUSPICIOUS_ACTIVITY = 'suspicious_activity';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the related model instance
     */
    public function subject()
    {
        if ($this->model_type && $this->model_id) {
            return $this->model_type::withTrashed()->find($this->model_id);
        }
        return null;
    }

    /**
     * Get a human-readable action label
     */
    public function getActionLabelAttribute(): string
    {
        return match($this->action) {
            self::ACTION_CREATED => 'Created',
            self::ACTION_UPDATED => 'Updated',
            self::ACTION_DELETED => 'Deleted',
            self::ACTION_RESTORED => 'Restored',
            self::ACTION_LOGIN => 'Logged In',
            self::ACTION_LOGOUT => 'Logged Out',
            self::ACTION_LOGIN_FAILED => 'Failed Login',
            self::ACTION_PASSWORD_RESET => 'Password Reset',
            self::ACTION_EXPORTED => 'Exported',
            self::ACTION_IMPORTED => 'Imported',
            self::ACTION_APPROVED => 'Approved',
            self::ACTION_REJECTED => 'Rejected',
            self::ACTION_POSTED => 'Posted',
            self::ACTION_SENT => 'Sent',
            self::ACTION_PAID => 'Marked as Paid',
            self::ACTION_RELEASED => 'Released',
            self::ACTION_BACKUP => 'Backup Created',
            self::ACTION_2FA_ENABLED => '2FA Enabled',
            self::ACTION_2FA_DISABLED => '2FA Disabled',
            self::ACTION_PASSWORD_CHANGED => 'Password Changed',
            self::ACTION_ROLE_CHANGED => 'Role Changed',
            self::ACTION_PERMISSION_CHANGED => 'Permission Changed',
            self::ACTION_ACCOUNT_LOCKED => 'Account Locked',
            self::ACTION_ACCOUNT_UNLOCKED => 'Account Unlocked',
            self::ACTION_API_TOKEN_CREATED => 'API Token Created',
            self::ACTION_API_TOKEN_REVOKED => 'API Token Revoked',
            self::ACTION_SUSPICIOUS_ACTIVITY => 'Suspicious Activity',
            default => ucfirst($this->action),
        };
    }

    /**
     * Get action color for UI
     */
    public function getActionColorAttribute(): string
    {
        return match($this->action) {
            self::ACTION_CREATED => 'green',
            self::ACTION_UPDATED => 'blue',
            self::ACTION_DELETED => 'red',
            self::ACTION_RESTORED => 'purple',
            self::ACTION_LOGIN => 'green',
            self::ACTION_LOGOUT => 'gray',
            self::ACTION_LOGIN_FAILED => 'red',
            self::ACTION_APPROVED, self::ACTION_POSTED => 'green',
            self::ACTION_REJECTED => 'red',
            self::ACTION_SENT => 'blue',
            self::ACTION_PAID => 'green',
            self::ACTION_RELEASED => 'indigo',
            self::ACTION_EXPORTED, self::ACTION_BACKUP => 'indigo',
            self::ACTION_2FA_ENABLED => 'green',
            self::ACTION_2FA_DISABLED => 'orange',
            self::ACTION_PASSWORD_CHANGED => 'blue',
            self::ACTION_ROLE_CHANGED, self::ACTION_PERMISSION_CHANGED => 'purple',
            self::ACTION_ACCOUNT_LOCKED => 'red',
            self::ACTION_ACCOUNT_UNLOCKED => 'green',
            self::ACTION_API_TOKEN_CREATED => 'blue',
            self::ACTION_API_TOKEN_REVOKED => 'orange',
            self::ACTION_SUSPICIOUS_ACTIVITY => 'red',
            default => 'gray',
        };
    }

    /**
     * Get the short model type name
     */
    public function getModelTypeShortAttribute(): string
    {
        if (!$this->model_type) {
            return '';
        }
        return class_basename($this->model_type);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by action
     */
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by model type
     */
    public function scopeForModel($query, $modelType)
    {
        return $query->where('model_type', $modelType);
    }

    /**
     * Scope to filter by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
