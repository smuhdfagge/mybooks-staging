<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Subscription extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'billing_cycle',
        'status',
        'amount',
        'currency',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'cancellation_reason',
        'payment_reference',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'trial_ends_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending'; // signed up, not yet paid
    const STATUS_ACTIVE = 'active';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';
    const STATUS_PAST_DUE = 'past_due';
    // Trial status removed - no trial for tenants

    /**
     * Billing cycle constants
     */
    const CYCLE_MONTHLY = 'monthly';
    const CYCLE_ANNUAL = 'annual';

    /**
     * Get the plan for this subscription
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if subscription is on trial
     * @deprecated Trials have been removed
     */
    public function onTrial(): bool
    {
        return false; // Trials have been removed
    }

    /**
     * Check if subscription has expired
     */
    public function hasExpired(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    /**
     * Check if subscription is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED || $this->cancelled_at !== null;
    }

    /**
     * Get days until expiration
     */
    public function daysUntilExpiration(): ?int
    {
        if (!$this->ends_at) {
            return null;
        }
        return max(0, now()->diffInDays($this->ends_at, false));
    }

    /**
     * Renew the subscription
     */
    public function renew(): self
    {
        $duration = $this->billing_cycle === self::CYCLE_ANNUAL ? 12 : 1;
        
        $this->update([
            'starts_at' => now(),
            'ends_at' => now()->addMonths($duration),
            'status' => self::STATUS_ACTIVE,
        ]);

        return $this;
    }

    /**
     * Cancel the subscription (finding M1).
     *
     * The tenant has paid for the current period, so the subscription stays
     * active with cancelled_at set and access continues until ends_at. The
     * daily subscriptions:expire command closes it after that.
     */
    public function cancel(?string $reason = null): self
    {
        $this->update([
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        return $this;
    }

    /**
     * Undo a cancellation. Only possible while the paid period is still
     * running; after that the tenant has to pay again.
     */
    public function reactivate(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE || $this->hasExpired()) {
            return false;
        }

        $this->update(['cancelled_at' => null, 'cancellation_reason' => null]);

        return true;
    }

    /**
     * Active, and the paid period has not ended. A subscription with no end
     * date (granted by an admin) counts as running.
     */
    public function isRunning(): bool
    {
        return $this->isActive() && ! $this->hasExpired();
    }

    /**
     * Scope to get active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to get expiring soon subscriptions
     */
    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->where('ends_at', '<=', now()->addDays($days))
                     ->where('ends_at', '>', now());
    }
}
