<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class NotificationLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'notification_type',
        'notifiable_type',
        'notifiable_id',
        'reference_type',
        'reference_id',
        'channel',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_PENDING = 'pending';

    /**
     * Get the notifiable entity
     */
    public function notifiable()
    {
        return $this->morphTo();
    }

    /**
     * Get the reference entity (Invoice, Bill, etc.)
     */
    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * Check if a notification was already sent recently
     */
    public static function wasRecentlySent(
        int $tenantId,
        string $notificationType,
        string $referenceType,
        int $referenceId,
        int $withinHours = 24
    ): bool {
        return static::where('tenant_id', $tenantId)
            ->where('notification_type', $notificationType)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('status', self::STATUS_SENT)
            ->where('sent_at', '>=', now()->subHours($withinHours))
            ->exists();
    }

    /**
     * Log a sent notification
     */
    public static function logSent(
        int $tenantId,
        string $notificationType,
        $notifiable,
        ?string $referenceType = null,
        ?int $referenceId = null,
        string $channel = 'mail'
    ): self {
        return static::create([
            'tenant_id' => $tenantId,
            'notification_type' => $notificationType,
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'channel' => $channel,
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * Log a failed notification
     */
    public static function logFailed(
        int $tenantId,
        string $notificationType,
        $notifiable,
        string $errorMessage,
        ?string $referenceType = null,
        ?int $referenceId = null,
        string $channel = 'mail'
    ): self {
        return static::create([
            'tenant_id' => $tenantId,
            'notification_type' => $notificationType,
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'channel' => $channel,
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'sent_at' => now(),
        ]);
    }
}
