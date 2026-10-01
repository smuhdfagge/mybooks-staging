<?php

namespace App\Models;

use App\Actions\SalesOrders\SaveSalesOrder;
use App\Traits\BelongsToTenant;
use App\Traits\HasDocumentNumber;
use App\Traits\KeepsTotalsBalanced;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use BelongsToTenant, HasFactory, KeepsTotalsBalanced, LogsActivity, SoftDeletes;
    use HasDocumentNumber;

    const STATUS_DRAFT = 'draft';

    const STATUS_SENT = 'sent';

    const STATUS_ACCEPTED = 'accepted';

    const STATUS_REJECTED = 'rejected';

    const STATUS_EXPIRED = 'expired';

    const STATUS_CONVERTED = 'converted';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'quotation_number',
        'reference',
        'quotation_date',
        'expiry_date',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'discount_type',
        'total',
        'notes',
        'terms',
        'converted_to_so_id',
        'created_by',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'expiry_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<QuotationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'converted_to_so_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array{0: string, 1: string, 2: int} */
    protected static function documentNumberFormat(): array
    {
        return ['quotation_number', 'QTN-', 6];
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast() && ! in_array($this->status, ['accepted', 'converted']);
    }

    /**
     * Convert this quotation to a Sales Order.
     */
    public function convertToSalesOrder(): SalesOrder
    {
        // The same rules as every other sales order (R3), so the order's
        // figures match the quotation's and the invoice that follows.
        $salesOrder = app(SaveSalesOrder::class)->create($this->tenant_id, [
            'customer_id' => $this->customer_id,
            'reference' => "From {$this->quotation_number}",
            'order_date' => now()->toDateString(),
            'expected_date' => $this->expiry_date?->toDateString(),
            'notes' => $this->notes,
            'terms' => $this->terms,
            // Stored as money on the quotation.
            'discount_type' => 'fixed',
            'discount_amount' => $this->discount_amount ?? 0,
            'items' => $this->items->map(fn ($i) => [
                'item_id' => $i->item_id,
                'description' => $i->description,
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price,
                'discount' => $i->discount ?? 0,
                'discount_type' => 'fixed',
                'tax_rate' => $i->tax_rate ?? 0,
            ])->all(),
        ], auth()->id());

        $this->update([
            'status' => self::STATUS_CONVERTED,
            'converted_to_so_id' => $salesOrder->id,
        ]);

        return $salesOrder;
    }

    /**
     * total = subtotal + tax_amount - discount_amount (see KeepsTotalsBalanced, Q2).
     */
    protected function documentTotalParts(): array
    {
        return [['subtotal', 'tax_amount'], ['discount_amount']];
    }
}
