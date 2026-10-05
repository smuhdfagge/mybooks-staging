<?php

namespace App\Models;

use App\Enums\VendorCreditStatus;
use App\Traits\BelongsToTenant;
use App\Traits\GuardsStatusTransitions;
use App\Traits\HasDocumentNumber;
use App\Traits\HasWarehouse;
use App\Traits\KeepsTotalsBalanced;
use App\Traits\ValidatesAccountingPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A supplier's credit note (vendor credit): goods sent back, or a price
 * reduction, optionally against one bill. Opening it posts the journal
 * and takes returned goods out of stock (App\Actions\VendorCredits).
 * The open balance can be used against bills or refunded by the supplier.
 */
class VendorCredit extends Model
{
    use BelongsToTenant, HasWarehouse, KeepsTotalsBalanced, SoftDeletes, ValidatesAccountingPeriod;
    use GuardsStatusTransitions, HasDocumentNumber;

    public const REASONS = [
        'goods_returned' => 'Goods returned',
        'damaged_goods' => 'Damaged or faulty goods',
        'price_correction' => 'Price correction',
        'short_delivery' => 'Short delivery',
        'discount' => 'Discount after billing',
        'other' => 'Other',
    ];

    protected $fillable = [
        'tenant_id',
        'vendor_id',
        'bill_id',
        'vendor_credit_number',
        'vendor_reference',
        'credit_date',
        'status',
        'reason',
        'subtotal',
        'tax_amount',
        'total',
        'balance',
        'notes',
        'stock_returned_at',
        'created_by',
    ];

    protected $casts = [
        'credit_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'balance' => 'decimal:2',
        'stock_returned_at' => 'datetime',
    ];

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<Bill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /** @return HasMany<VendorCreditItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(VendorCreditItem::class);
    }

    /** @return HasMany<VendorCreditApplication, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(VendorCreditApplication::class);
    }

    /** @return HasMany<VendorCreditRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(VendorCreditRefund::class);
    }

    /** @return MorphMany<Journal, $this> */
    public function journals(): MorphMany
    {
        return $this->morphMany(Journal::class, 'reference');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->status === VendorCreditStatus::Open->value;
    }

    /** Used against bills plus refunded. */
    public function usedAmount(): float
    {
        return round((float) $this->applications()->sum('amount') + (float) $this->refunds()->sum('amount'), 2);
    }

    /** @return array{0: string, 1: string, 2: int} */
    protected static function documentNumberFormat(): array
    {
        return ['vendor_credit_number', 'VCN-', 6];
    }

    protected function getPeriodDateField(): string
    {
        return 'credit_date';
    }

    /** total = subtotal + tax_amount (see KeepsTotalsBalanced). */
    protected function documentTotalParts(): array
    {
        return [['subtotal', 'tax_amount'], []];
    }

    protected static function statusEnum(): string
    {
        return VendorCreditStatus::class;
    }

    protected static function statusDocumentName(): string
    {
        return 'supplier credit';
    }
}
