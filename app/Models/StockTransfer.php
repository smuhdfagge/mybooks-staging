<?php

namespace App\Models;

use App\Enums\StockTransferStatus;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Traits\BelongsToTenant;
use App\Traits\GuardsStatusTransitions;
use App\Traits\HasDocumentNumber;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Moving stock from one warehouse to another (session 13). The work is
 * done by the actions in App\Actions\StockTransfers: shipping takes the
 * goods and their cost out of the source warehouse, receiving puts the
 * same cost into the destination. While in transit the goods are in
 * neither warehouse but still the business's stock.
 */
class StockTransfer extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;
    use GuardsStatusTransitions, HasDocumentNumber;

    const STATUS_DRAFT = 'draft';

    const STATUS_IN_TRANSIT = 'in_transit';

    const STATUS_RECEIVED = 'received';

    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'transfer_number',
        'transfer_date',
        'from_warehouse_id',
        'to_warehouse_id',
        'status',
        'reference',
        'notes',
        'shipped_at',
        'received_at',
        'received_date',
        'cancelled_at',
        'created_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'received_date' => 'date',
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** @return BelongsTo<Warehouse, $this> */
    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    /** @return HasMany<StockTransferItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isInTransit(): bool
    {
        return $this->status === self::STATUS_IN_TRANSIT;
    }

    /** Cost of the goods that left the source warehouse. */
    public function shippedCost(): float
    {
        return round((float) $this->items->sum('shipped_cost'), 2);
    }

    public static function moduleOn(): bool
    {
        return EnsureFeatureEnabled::enabled('stock_transfers') && Warehouse::moduleOn();
    }

    /**
     * Quantity of an item on the road (shipped, not yet received), for the
     * item's stock split. Optionally only what is going to, or coming from,
     * one warehouse.
     */
    public static function inTransitQuantity(Item $item, ?int $toWarehouseId = null, ?int $fromWarehouseId = null): float
    {
        if (! static::moduleOn()) {
            return 0.0;
        }

        return (float) StockTransferItem::query()
            ->where('item_id', $item->id)
            ->whereHas('stockTransfer', fn ($q) => $q->where('stock_transfers.tenant_id', $item->tenant_id)->where('status', self::STATUS_IN_TRANSIT)
                ->when($toWarehouseId, fn ($w) => $w->where('to_warehouse_id', $toWarehouseId))
                ->when($fromWarehouseId, fn ($w) => $w->where('from_warehouse_id', $fromWarehouseId)))
            ->sum('quantity');
    }

    /**
     * Quantity and cost of everything on the road, per item:
     * [item_id => ['quantity' => float, 'cost' => float]].
     *
     * @return array<int, array{quantity: float, cost: float}>
     */
    public static function inTransitByItem(int $tenantId): array
    {
        if (! static::moduleOn()) {
            return [];
        }

        return StockTransferItem::query()
            ->whereHas('stockTransfer', fn ($q) => $q->where('stock_transfers.tenant_id', $tenantId)->where('status', self::STATUS_IN_TRANSIT))
            ->selectRaw('item_id, SUM(quantity) as qty, SUM(shipped_cost) as cost')
            ->groupBy('item_id')
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->item_id => ['quantity' => (float) $row->qty, 'cost' => round((float) $row->cost, 2)]])
            ->all();
    }

    /** @return array{0: string, 1: string, 2: int} */
    protected static function documentNumberFormat(): array
    {
        return ['transfer_number', 'TRF-', 6];
    }

    /** Allowed status moves. */
    protected static function statusEnum(): string
    {
        return StockTransferStatus::class;
    }
}
