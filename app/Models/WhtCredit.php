<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WHT a customer took off a payment to us (tax pack 2). It is a credit
 * against our own income tax once the customer sends the WHT certificate
 * (credit note), so each one is tracked until the certificate arrives.
 */
class WhtCredit extends Model
{
    use BelongsToTenant;

    public const STATUS_AWAITING = 'awaiting';

    public const STATUS_RECEIVED = 'received';

    protected $fillable = [
        'tenant_id', 'customer_id', 'payment_received_id', 'amount', 'deducted_on',
        'status', 'certificate_number', 'certificate_date', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'deducted_on' => 'date',
        'certificate_date' => 'date',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<PaymentReceived, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentReceived::class, 'payment_received_id');
    }
}
