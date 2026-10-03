<?php

namespace App\Models;

use App\Traits\RecordsVatTreatment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteItem extends Model
{
    use HasFactory, RecordsVatTreatment;

    protected $fillable = [
        'credit_note_id',
        'item_id',
        'description',
        'quantity',
        'unit_price',
        'unit_cost',
        'tax_rate',
        'tax_amount',
        'vat_treatment',
        'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /** @return BelongsTo<CreditNote, $this> */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /**
     * A credit note against an invoice takes the treatment of the matching
     * invoice line (same item, else same description), so crediting a
     * zero-rated sale reduces zero-rated supplies.
     */
    public function inheritedVatTreatment(): ?string
    {
        $invoiceId = CreditNote::withoutGlobalScopes()->whereKey($this->credit_note_id)->value('invoice_id');
        if (! $invoiceId) {
            return null;
        }

        $lines = InvoiceItem::where('invoice_id', $invoiceId)->get(['item_id', 'description', 'vat_treatment']);
        $match = ($this->item_id ? $lines->firstWhere('item_id', $this->item_id) : null)
            ?? $lines->firstWhere('description', $this->description);

        return $match?->vat_treatment;
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** The line before VAT. */
    public function net(): float
    {
        return round((float) $this->total - (float) $this->tax_amount, 2);
    }

    /** Goods that can go back into stock (a stock item, not a service). */
    public function isStocked(): bool
    {
        return $this->item_id && $this->item && $this->item->track_inventory
            && $this->item->type !== 'service' && (float) $this->quantity > 0;
    }
}
