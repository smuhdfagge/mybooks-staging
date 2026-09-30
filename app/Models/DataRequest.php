<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log of data-protection requests under the Nigeria Data Protection Act
 * (finding O7): access, export, correction, erasure and closure. Not
 * scoped to a tenant and without foreign keys, so the record of an
 * erasure survives the erasure itself. Holds no personal data beyond the
 * requester's name.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string|null $tenant_name
 * @property int|null $user_id
 * @property string|null $requester
 * @property string $type
 * @property string $status
 * @property string|null $details
 * @property \Illuminate\Support\Carbon|null $due_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string|null $handled_by
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class DataRequest extends Model
{
    public const TYPES = [
        'access' => 'Access (copy of personal data)',
        'export' => 'Data export',
        'rectification' => 'Correction',
        'erasure' => 'Erasure',
        'closure' => 'Close organisation',
        'objection' => 'Objection / restriction',
    ];

    public const STATUS_RECEIVED = 'received';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** The NDPA expects a reply within 30 days. */
    public const RESPONSE_DAYS = 30;

    protected $fillable = [
        'tenant_id', 'tenant_name', 'user_id', 'requester', 'type', 'status',
        'details', 'due_at', 'completed_at', 'handled_by',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Record a request made by a user of a business.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function record(string $type, ?Tenant $tenant, ?User $user, array $attributes = []): self
    {
        return static::create(array_merge([
            'tenant_id' => $tenant?->id,
            'tenant_name' => $tenant?->name,
            'user_id' => $user?->id,
            'requester' => $user?->name,
            'type' => $type,
            'status' => self::STATUS_RECEIVED,
            'due_at' => now()->addDays(self::RESPONSE_DAYS),
        ], $attributes));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_RECEIVED, self::STATUS_SCHEDULED], true);
    }
}
