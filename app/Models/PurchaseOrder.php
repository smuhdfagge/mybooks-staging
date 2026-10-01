<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Traits\BelongsToTenant;
use App\Traits\GuardsStatusTransitions;
use App\Traits\KeepsTotalsBalanced;
use App\Traits\LogsActivity;
use App\Traits\ValidatesAccountingPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use \App\Traits\HasDocumentNumber, GuardsStatusTransitions;
    use BelongsToTenant, HasFactory, KeepsTotalsBalanced, LogsActivity, SoftDeletes, ValidatesAccountingPeriod;

    /** A bill has been raised for this order (finding N8). */
    public const STATUS_BILLED = PurchaseOrderStatus::Billed->value;

    /** Statuses from which a bill can be raised. */
    public const BILLABLE = [PurchaseOrderStatus::Confirmed->value, PurchaseOrderStatus::PartiallyReceived->value, PurchaseOrderStatus::Received->value];

    protected $fillable = [
        'tenant_id',
        'vendor_id',
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
        'total_received_amount',
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
        'total_received_amount' => 'decimal:2',
    ];

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return HasMany<PurchaseOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /** @return HasMany<Bill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array{0: string, 1: string, 2: int} */
    protected static function documentNumberFormat(): array
    {
        return ['order_number', 'PO-', 6];
    }

    public function hasUnreceivedItems(): bool
    {
        return $this->items()->whereColumn('quantity_received', '<', 'quantity')->exists();
    }

    public function updateReceivingStatus(): void
    {
        $totalQuantity = $this->items()->sum('quantity');
        $totalReceived = $this->items()->sum('quantity_received');

        $this->total_received_amount = $this->items()
            ->selectRaw('SUM(quantity_received * unit_price) as received_amount')
            ->value('received_amount') ?? 0;

        if ($totalReceived == 0) {
            $this->status = 'confirmed';
        } elseif ($totalReceived >= $totalQuantity) {
            $this->status = 'received';
        } else {
            $this->status = 'partially_received';
        }

        $this->save();
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
        return PurchaseOrderStatus::class;
    }
}
