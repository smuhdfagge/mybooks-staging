<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Part of a supplier credit used against a bill (posts nothing: both sides are in payables). */
class VendorCreditApplication extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'vendor_credit_id',
        'bill_id',
        'amount',
        'applied_date',
        'applied_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'applied_date' => 'date',
    ];

    /** @return BelongsTo<VendorCredit, $this> */
    public function vendorCredit(): BelongsTo
    {
        return $this->belongsTo(VendorCredit::class);
    }

    /** @return BelongsTo<Bill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /** @return BelongsTo<User, $this> */
    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
