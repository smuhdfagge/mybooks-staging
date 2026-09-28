<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;
use App\Traits\LogsActivity;

class BillOfMaterial extends Model
{
    use HasFactory, BelongsToTenant, LogsActivity;

    protected $table = 'bill_of_materials';

    protected $fillable = [
        'tenant_id',
        'item_id',
        'name',
        'description',
        'output_quantity',
        'is_active',
    ];

    protected $casts = [
        'output_quantity' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return HasMany<BomItem, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(BomItem::class, 'bill_of_materials_id');
    }

    /** @return HasMany<AssemblyOrder, $this> */
    public function assemblyOrders(): HasMany
    {
        return $this->hasMany(AssemblyOrder::class);
    }

    /**
     * Calculate total component cost for one unit of the finished product.
     */
    public function getUnitCostAttribute(): float
    {
        $totalCost = 0;

        foreach ($this->components as $component) {
            $effectiveQty = $component->quantity * (1 + ($component->waste_percentage / 100));
            $unitCost = $component->item->cost_price ?? 0;
            $totalCost += $effectiveQty * $unitCost;
        }

        return $this->output_quantity > 0 ? $totalCost / (float) $this->output_quantity : 0;
    }

    /**
     * Check if all components have sufficient stock for a given build quantity.
     */
    public function canBuild(float $quantity, ?int $warehouseId = null): bool
    {
        foreach ($this->components as $component) {
            $required = $component->quantity * $quantity * (1 + ($component->waste_percentage / 100));

            $inventoryQuery = Inventory::where('tenant_id', $this->tenant_id)
                ->where('item_id', $component->item_id);

            if ($warehouseId) {
                $inventoryQuery->where('warehouse_id', $warehouseId);
            }

            $available = $inventoryQuery->sum('quantity') - $inventoryQuery->sum('reserved_quantity');

            if ($available < $required) {
                return false;
            }
        }

        return true;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
