<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryLayer extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'item_id',
        'warehouse_id',
        'quantity',
        'remaining_quantity',
        'unit_cost',
        'reference_type',
        'reference_id',
        'batch_number',
        'received_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'remaining_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'received_date' => 'date',
    ];

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to get layers with remaining stock, ordered FIFO.
     */
    public function scopeWithStock($query)
    {
        return $query->where('remaining_quantity', '>', 0)
            ->orderBy('received_date')
            ->orderBy('id');
    }

    /**
     * Scope to filter by item and optionally warehouse.
     */
    public function scopeForItem($query, int $itemId, ?int $warehouseId = null)
    {
        $query->where('item_id', $itemId);

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query;
    }

    /**
     * Consume quantity from this layer. Returns the amount actually consumed.
     */
    public function consume(float $quantity): float
    {
        $consumed = min($quantity, (float) $this->remaining_quantity);
        $this->remaining_quantity -= $consumed;
        $this->save();

        return $consumed;
    }
}
