<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\ValidatesAccountingPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/** The supplier paying back (part of) a credit: Dr bank, Cr accounts payable. */
class VendorCreditRefund extends Model
{
    use BelongsToTenant, ValidatesAccountingPeriod;

    protected $fillable = [
        'tenant_id',
        'vendor_credit_id',
        'refund_date',
        'amount',
        'payment_method',
        'bank_id',
        'reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'refund_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<VendorCredit, $this> */
    public function vendorCredit(): BelongsTo
    {
        return $this->belongsTo(VendorCredit::class);
    }

    /** @return BelongsTo<Bank, $this> */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /** @return MorphOne<Journal, $this> */
    public function journal(): MorphOne
    {
        return $this->morphOne(Journal::class, 'reference');
    }

    protected function getPeriodDateField(): string
    {
        return 'refund_date';
    }
}
