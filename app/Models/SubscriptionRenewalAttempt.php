<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One automatic charge of a saved card to renew a subscription (session 15).
 * (subscription_id, period_end, attempt) is unique, so a period can't be
 * charged twice even if the command runs twice at once.
 */
class SubscriptionRenewalAttempt extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id', 'subscription_id', 'subscription_payment_id', 'period_end', 'attempt',
        'reference', 'amount', 'currency', 'card_last4', 'status', 'message', 'next_retry_at',
    ];

    protected $casts = [
        'period_end' => 'datetime',
        'attempt' => 'integer',
        'amount' => 'decimal:2',
        'next_retry_at' => 'datetime',
    ];

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<SubscriptionPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscription_payment_id');
    }
}
