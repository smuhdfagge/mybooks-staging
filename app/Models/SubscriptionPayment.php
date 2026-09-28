<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * One checkout for a subscription (finding C1). Created before the customer
 * is sent to the payment gateway; marked success only after the gateway
 * confirms the charge.
 */
class SubscriptionPayment extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id', 'subscription_id', 'plan_id', 'user_id', 'billing_cycle',
        'reference', 'amount', 'currency', 'gateway',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'gateway_response' => 'array',
    ];

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** Amount in the smallest currency unit, as Paystack expects. */
    public function amountInKobo(): int
    {
        return (int) round((float) $this->amount * 100);
    }
}
