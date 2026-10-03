<?php

namespace App\Models;

use App\Enums\SalesOrderStatus;
use App\Traits\BelongsToTenant;
use App\Traits\GuardsStatusTransitions;
use App\Traits\HasDocumentNumber;
use App\Traits\KeepsTotalsBalanced;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use BelongsToTenant, HasFactory, KeepsTotalsBalanced, LogsActivity, SoftDeletes, ValidatesAccountingPeriod;
    use GuardsStatusTransitions, HasDocumentNumber;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'order_number',
        'reference',
        'order_date',
        'expected_date',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'discount_type',
        'total',
        'total_fulfilled_amount',
        'notes',
        'terms',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'total_fulfilled_amount' => 'decimal:2',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<SalesOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<DeliveryNote, $this> */
    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    /** @return HasOne<Quotation, $this> */
    public function quotation(): HasOne
    {
        return $this->hasOne(Quotation::class, 'converted_to_so_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array{0: string, 1: string, 2: int} */
    protected static function documentNumberFormat(): array
    {
        return ['order_number', 'SO-', 6];
    }

    /**
     * Set the order's status from what has been delivered (dispatched
     * delivery notes) and invoiced:
     *   everything delivered        -> completed
     *   some delivered              -> processing
     *   nothing delivered (again)   -> invoiced if anything is invoiced, else confirmed
     * Draft and cancelled orders are left alone.
     */
    public function updateFulfillmentStatus(): void
    {
        $this->load('items');

        if (in_array($this->status, [SalesOrderStatus::Draft->value, SalesOrderStatus::Cancelled->value], true)) {
            return;
        }

        $lines = $this->items;
        $allDelivered = $lines->isNotEmpty() && $lines->every(fn ($l) => (float) $l->quantity_fulfilled + 0.001 >= (float) $l->quantity);
        $anyDelivered = $lines->contains(fn ($l) => (float) $l->quantity_fulfilled > 0);
        $anyInvoiced = $lines->contains(fn ($l) => (float) $l->quantity_invoiced > 0);

        $this->status = match (true) {
            $allDelivered => SalesOrderStatus::Completed->value,
            $anyDelivered => SalesOrderStatus::Processing->value,
            $anyInvoiced => SalesOrderStatus::Invoiced->value,
            default => SalesOrderStatus::Confirmed->value,
        };

        $this->total_fulfilled_amount = round($lines->sum(function ($line) {
            $ratio = (float) $line->quantity > 0 ? (float) $line->quantity_fulfilled / (float) $line->quantity : 0;

            return (float) $line->total * min($ratio, 1);
        }), 2);

        $this->save();
    }

    /** Lines with quantities not yet invoiced. */
    public function hasUninvoicedItems(): bool
    {
        return $this->items()->whereColumn('quantity_invoiced', '<', 'quantity')->exists();
    }

    /**
     * Check if any items have unfulfilled quantities.
     */
    public function hasUnfulfilledItems(): bool
    {
        return $this->items()->whereRaw('quantity_fulfilled < quantity')->exists();
    }

    /**
     * total = subtotal + tax_amount - discount_amount (see KeepsTotalsBalanced, Q2).
     */
    protected function documentTotalParts(): array
    {
        return [['subtotal', 'tax_amount'], ['discount_amount']];
    }

    /** Allowed status moves (Q3). */
    protected static function statusEnum(): string
    {
        return SalesOrderStatus::class;
    }
}
