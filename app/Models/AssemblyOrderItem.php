<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A component line of an assembly order (session 14): planned_quantity
 * from the bill (wastage included), quantity actually used (or got back
 * from a break-down) once completed, and its cost.
 */
class AssemblyOrderItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'assembly_order_id',
        'item_id',
        'bom_item_id',
        'planned_quantity',
        'quantity',
        'cost',
    ];

    protected $casts = [
        'planned_quantity' => 'decimal:4',
        'quantity' => 'decimal:4',
        'cost' => 'decimal:2',
    ];

    /** @return BelongsTo<AssemblyOrder, $this> */
    public function assemblyOrder(): BelongsTo
    {
        return $this->belongsTo(AssemblyOrder::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * The pieces of cost layers this line took (a build), so undoing puts
     * them back exactly.
     *
     * @return HasMany<InventoryLayerConsumption, $this>
     */
    public function consumptions(): HasMany
    {
        return $this->hasMany(InventoryLayerConsumption::class, 'source_id')
            ->where('source_type', self::class);
    }

    /** Actual quantity when completed, else the plan. */
    public function usedQuantity(): float
    {
        return (float) ($this->quantity ?? $this->planned_quantity);
    }

    public function unitCost(): float
    {
        $qty = (float) $this->quantity;

        return $qty > 0 ? round((float) $this->cost / $qty, 2) : 0.0;
    }
}
