<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasDocumentNumber;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;
    use HasDocumentNumber;

    const STATUS_DRAFT = 'draft';

    const STATUS_IN_TRANSIT = 'in_transit';

    const STATUS_COMPLETED = 'completed';

    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'transfer_number',
        'from_warehouse_id',
        'to_warehouse_id',
        'status',
        'notes',
        'shipped_at',
        'received_at',
        'created_by',
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
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

    /** @return array{0: string, 1: string, 2: int} */
    protected static function documentNumberFormat(): array
    {
        return ['transfer_number', 'ST-', 5];
    }

    /**
     * Ship the transfer — deduct from source warehouse.
     */
    public function ship(): void
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \RuntimeException('Only draft transfers can be shipped.');
        }

        foreach ($this->items as $transferItem) {
            $item = $transferItem->item;
            if (! $item || ! $item->track_inventory) {
                continue;
            }

            // Deduct from source warehouse inventory
            $inventory = Inventory::where('tenant_id', $this->tenant_id)
                ->where('item_id', $transferItem->item_id)
                ->where('warehouse_id', $this->from_warehouse_id)
                ->first();

            if ($inventory) {
                $inventory->quantity -= $transferItem->quantity;
                $inventory->save();
            }

            // Record history
            InventoryHistory::create([
                'tenant_id' => $this->tenant_id,
                'item_id' => $transferItem->item_id,
                'type' => 'transfer',
                'quantity' => -$transferItem->quantity,
                'reference_type' => 'stock_transfer',
                'reference_id' => $this->id,
                'notes' => "Transfer out to {$this->toWarehouse->name} (#{$this->transfer_number})",
                'created_by' => auth()->id(),
            ]);
        }

        $this->update([
            'status' => self::STATUS_IN_TRANSIT,
            'shipped_at' => now(),
        ]);
    }

    /**
     * Receive the transfer — add to destination warehouse.
     */
    public function receive(): void
    {
        if ($this->status !== self::STATUS_IN_TRANSIT) {
            throw new \RuntimeException('Only in-transit transfers can be received.');
        }

        foreach ($this->items as $transferItem) {
            $item = $transferItem->item;
            if (! $item || ! $item->track_inventory) {
                continue;
            }

            $receivedQty = $transferItem->quantity_received > 0
                ? $transferItem->quantity_received
                : $transferItem->quantity;

            // Add to destination warehouse inventory
            $inventory = Inventory::firstOrCreate(
                [
                    'tenant_id' => $this->tenant_id,
                    'item_id' => $transferItem->item_id,
                    'warehouse_id' => $this->to_warehouse_id,
                ],
                ['quantity' => 0, 'reserved_quantity' => 0, 'unit_cost' => $item->cost_price ?? 0]
            );

            $inventory->quantity += $receivedQty;
            $inventory->save();

            // Transfer inventory layers (FIFO support)
            $this->transferLayers($transferItem, $receivedQty);

            // Record history
            InventoryHistory::create([
                'tenant_id' => $this->tenant_id,
                'item_id' => $transferItem->item_id,
                'type' => 'transfer',
                'quantity' => $receivedQty,
                'reference_type' => 'stock_transfer',
                'reference_id' => $this->id,
                'notes' => "Transfer in from {$this->fromWarehouse->name} (#{$this->transfer_number})",
                'created_by' => auth()->id(),
            ]);
        }

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'received_at' => now(),
        ]);
    }

    /**
     * Transfer FIFO layers from source to destination warehouse.
     */
    protected function transferLayers(StockTransferItem $transferItem, float $quantity): void
    {
        $remaining = $quantity;
        $layers = InventoryLayer::where('tenant_id', $this->tenant_id)
            ->where('item_id', $transferItem->item_id)
            ->where('warehouse_id', $this->from_warehouse_id)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_date')
            ->orderBy('id')
            ->get();

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $consumed = $layer->consume($remaining);
            $remaining -= $consumed;

            // Create new layer at destination
            InventoryLayer::create([
                'tenant_id' => $this->tenant_id,
                'item_id' => $transferItem->item_id,
                'warehouse_id' => $this->to_warehouse_id,
                'quantity' => $consumed,
                'remaining_quantity' => $consumed,
                'unit_cost' => $layer->unit_cost,
                'reference_type' => 'stock_transfer',
                'reference_id' => $this->id,
                'batch_number' => $layer->batch_number,
                'received_date' => now()->toDateString(),
            ]);
        }
    }
}
