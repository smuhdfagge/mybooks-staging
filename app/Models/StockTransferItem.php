<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line of a stock transfer. quantity is what is sent; once received,
 * quantity_received arrived, quantity_returned went back to the source and
 * quantity_lost was written off. The costs are what left the source
 * (shipped_cost), arrived (received_cost) and was lost (lost_cost).
 */
class StockTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id',
        'item_id',
        'quantity',
        'quantity_received',
        'quantity_returned',
        'quantity_lost',
        'shipped_cost',
        'received_cost',
        'lost_cost',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'quantity_received' => 'decimal:4',
        'quantity_returned' => 'decimal:4',
        'quantity_lost' => 'decimal:4',
        'shipped_cost' => 'decimal:2',
        'received_cost' => 'decimal:2',
        'lost_cost' => 'decimal:2',
    ];

    /** @return BelongsTo<StockTransfer, $this> */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * The pieces of cost layers this line took from the source warehouse.
     *
     * @return HasMany<InventoryLayerConsumption, $this>
     */
    public function consumptions(): HasMany
    {
        return $this->hasMany(InventoryLayerConsumption::class, 'source_id')
            ->where('source_type', self::class);
    }

    /** Average cost per unit sent. */
    public function unitCost(): float
    {
        return (float) $this->quantity > 0 ? round((float) $this->shipped_cost / (float) $this->quantity, 2) : 0.0;
    }
}
