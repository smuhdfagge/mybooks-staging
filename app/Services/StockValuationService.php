<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryLayer;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

class StockValuationService
{
    /**
     * Add an inventory layer when stock is received (bill paid, adjustment-in, assembly).
     */
    public function addLayer(
        int $tenantId,
        int $itemId,
        float $quantity,
        float $unitCost,
        ?int $warehouseId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $batchNumber = null
    ): InventoryLayer {
        return InventoryLayer::create([
            'tenant_id' => $tenantId,
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'unit_cost' => $unitCost,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'batch_number' => $batchNumber,
            'received_date' => now()->toDateString(),
        ]);
    }

    /**
     * Calculate COGS for a given item and quantity using the item's valuation method.
     * Returns the total COGS amount.
     */
    public function calculateCogs(Item $item, float $quantity, ?int $warehouseId = null): float
    {
        $method = $item->valuation_method ?? 'weighted_average';

        return match ($method) {
            'fifo' => $this->calculateFifoCogs($item, $quantity, $warehouseId),
            default => $this->calculateWeightedAverageCogs($item, $quantity, $warehouseId),
        };
    }

    /**
     * Consume inventory using the item's valuation method. Actually deducts from layers.
     * Returns the total COGS amount.
     */
    public function consumeStock(Item $item, float $quantity, ?int $warehouseId = null): float
    {
        $method = $item->valuation_method ?? 'weighted_average';

        return match ($method) {
            'fifo' => $this->consumeFifo($item, $quantity, $warehouseId),
            default => $this->consumeWeightedAverage($item, $quantity, $warehouseId),
        };
    }

    /**
     * FIFO: Calculate COGS by consuming oldest layers first (read-only).
     */
    protected function calculateFifoCogs(Item $item, float $quantity, ?int $warehouseId): float
    {
        $layers = InventoryLayer::where('tenant_id', $item->tenant_id)
            ->forItem($item->id, $warehouseId)
            ->withStock()
            ->get();

        $remaining = $quantity;
        $cogs = 0;

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $consumed = min($remaining, (float) $layer->remaining_quantity);
            $cogs += $consumed * (float) $layer->unit_cost;
            $remaining -= $consumed;
        }

        // If layers don't fully cover the quantity, use item's cost_price for remainder
        if ($remaining > 0) {
            $cogs += $remaining * ($item->cost_price ?? 0);
        }

        return round($cogs, 2);
    }

    /**
     * FIFO: Actually consume from oldest layers.
     */
    protected function consumeFifo(Item $item, float $quantity, ?int $warehouseId): float
    {
        $layers = InventoryLayer::where('tenant_id', $item->tenant_id)
            ->forItem($item->id, $warehouseId)
            ->withStock()
            ->lockForUpdate()
            ->get();

        $remaining = $quantity;
        $cogs = 0;

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $consumed = $layer->consume($remaining);
            $cogs += $consumed * (float) $layer->unit_cost;
            $remaining -= $consumed;
        }

        // Fallback for untracked remainder
        if ($remaining > 0) {
            $cogs += $remaining * ($item->cost_price ?? 0);
        }

        return round($cogs, 2);
    }

    /**
     * Weighted Average: Calculate COGS based on current weighted average unit cost.
     */
    protected function calculateWeightedAverageCogs(Item $item, float $quantity, ?int $warehouseId): float
    {
        $unitCost = $this->getWeightedAverageCost($item, $warehouseId);
        return round($quantity * $unitCost, 2);
    }

    /**
     * Weighted Average: Consume stock using average cost.
     */
    protected function consumeWeightedAverage(Item $item, float $quantity, ?int $warehouseId): float
    {
        $unitCost = $this->getWeightedAverageCost($item, $warehouseId);

        // Consume proportionally from all layers
        $layers = InventoryLayer::where('tenant_id', $item->tenant_id)
            ->forItem($item->id, $warehouseId)
            ->withStock()
            ->lockForUpdate()
            ->get();

        $totalRemaining = $layers->sum('remaining_quantity');
        $remaining = $quantity;

        foreach ($layers as $layer) {
            if ($remaining <= 0 || $totalRemaining <= 0) {
                break;
            }

            // Proportional consumption
            $proportion = (float) $layer->remaining_quantity / $totalRemaining;
            $consume = min($remaining, $proportion * $quantity, (float) $layer->remaining_quantity);
            $layer->consume($consume);
            $remaining -= $consume;
        }

        return round($quantity * $unitCost, 2);
    }

    /**
     * Get the weighted average cost from inventory layers.
     */
    public function getWeightedAverageCost(Item $item, ?int $warehouseId = null): float
    {
        $query = InventoryLayer::where('tenant_id', $item->tenant_id)
            ->forItem($item->id, $warehouseId)
            ->where('remaining_quantity', '>', 0);

        $totalQty = (float) $query->sum('remaining_quantity');

        if ($totalQty <= 0) {
            return $item->cost_price ?? 0;
        }

        $totalValue = (float) $query->selectRaw('SUM(remaining_quantity * unit_cost) as total_value')
            ->value('total_value');

        return round($totalValue / $totalQty, 4);
    }

    /**
     * Get stock valuation summary for an item across all warehouses or a specific one.
     */
    public function getValuation(Item $item, ?int $warehouseId = null): array
    {
        $method = $item->valuation_method ?? 'weighted_average';

        $query = InventoryLayer::where('tenant_id', $item->tenant_id)
            ->forItem($item->id, $warehouseId)
            ->where('remaining_quantity', '>', 0);

        $totalQty = (float) $query->sum('remaining_quantity');
        $totalValue = (float) $query->selectRaw('SUM(remaining_quantity * unit_cost) as v')->value('v');
        $avgCost = $totalQty > 0 ? $totalValue / $totalQty : 0;

        return [
            'item_id' => $item->id,
            'warehouse_id' => $warehouseId,
            'valuation_method' => $method,
            'total_quantity' => round($totalQty, 4),
            'total_value' => round($totalValue, 2),
            'average_cost' => round($avgCost, 4),
            'layers_count' => InventoryLayer::where('tenant_id', $item->tenant_id)
                ->forItem($item->id, $warehouseId)
                ->where('remaining_quantity', '>', 0)
                ->count(),
        ];
    }

    /**
     * Update weighted average cost on the Inventory model when stock is received.
     */
    public function updateWeightedAverageCost(
        Inventory $inventory,
        float $newQuantity,
        float $newUnitCost
    ): void {
        $existingValue = (float) $inventory->quantity * (float) $inventory->unit_cost;
        $newValue = $newQuantity * $newUnitCost;
        $totalQty = (float) $inventory->quantity + $newQuantity;

        $inventory->unit_cost = $totalQty > 0
            ? round(($existingValue + $newValue) / $totalQty, 4)
            : 0;
    }
}
