<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Part of a supplier advance used against a bill. Mirrors
 * CustomerDepositApplication: the applied_payment is a payment made with
 * method "advance" that clears the bill (Dr payables, Cr supplier advances).
 */
class VendorAdvanceApplication extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'vendor_id',
        'advance_payment_id',
        'bill_id',
        'applied_payment_id',
        'amount',
        'application_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'application_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<PaymentMade, $this> */
    public function advancePayment(): BelongsTo
    {
        return $this->belongsTo(PaymentMade::class, 'advance_payment_id');
    }

    /** @return BelongsTo<PaymentMade, $this> */
    public function appliedPayment(): BelongsTo
    {
        return $this->belongsTo(PaymentMade::class, 'applied_payment_id');
    }

    /** @return BelongsTo<Bill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
