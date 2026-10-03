<?php

namespace App\Models;

use App\Traits\RecordsVatTreatment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorCreditItem extends Model
{
    use RecordsVatTreatment;

    protected $fillable = [
        'vendor_credit_id',
        'item_id',
        'account_id',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'vat_treatment',
        'total',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'unit_cost' => 'decimal:4',
    ];

    /** @return BelongsTo<VendorCredit, $this> */
    public function vendorCredit(): BelongsTo
    {
        return $this->belongsTo(VendorCredit::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return BelongsTo<ChartOfAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    /**
     * A line without VAT takes the treatment of the bill line it credits
     * (same item, else same description), so returning zero-rated or exempt
     * goods comes off the right box of the VAT return.
     */
    public function inheritedVatTreatment(): ?string
    {
        $billId = VendorCredit::withoutGlobalScopes()->whereKey($this->vendor_credit_id)->value('bill_id');
        if (! $billId) {
            return null;
        }

        $lines = BillItem::where('bill_id', $billId)->get(['item_id', 'description', 'vat_treatment']);
        $match = ($this->item_id ? $lines->firstWhere('item_id', $this->item_id) : null)
            ?? $lines->firstWhere('description', $this->description);

        return $match?->vat_treatment;
    }

    /** The line before VAT. */
    public function net(): float
    {
        return round((float) $this->total - (float) $this->tax_amount, 2);
    }

    /** Goods that go back out of stock. */
    public function isStocked(): bool
    {
        return $this->item_id && $this->item && $this->item->track_inventory && (float) $this->quantity > 0;
    }
}
