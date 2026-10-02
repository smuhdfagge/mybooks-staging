<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\InventoryLayer;
use App\Models\InventoryLayerConsumption;
use App\Models\Item;
use App\Models\VendorCredit;

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
     * Take stock out for a sales document and return its cost (findings M3, N6).
     *
     * The cost is decided here, once: FIFO uses the oldest cost layers first;
     * weighted average uses the current average cost and takes the quantity
     * from the layers in proportion. Stock beyond the recorded layers is costed
     * at the item's cost price (FIFO) or the average (weighted average).
     *
     * Every piece taken is recorded against the document, so returnStock()
     * can put back exactly what was taken if it is edited, cancelled or deleted.
     *
     * $reduceOnHand also lowers the quantity on hand (cash sales). Invoices
     * leave it alone: their quantity is reserved when created and reduced
     * when released.
     */
    public function issue(Item $item, float $quantity, string $sourceType, int $sourceId, bool $reduceOnHand = false, ?int $warehouseId = null): float
    {
        if ($quantity <= 0) {
            return 0.0;
        }

        $layers = InventoryLayer::where('tenant_id', $item->tenant_id)
            ->forItem($item->id, $warehouseId)
            ->withStock()
            ->lockForUpdate()
            ->get();

        $pieces = [];
        $remaining = $quantity;

        if (($item->valuation_method ?? 'weighted_average') === 'fifo') {
            foreach ($layers as $layer) {
                if ($remaining <= 0.00001) {
                    break;
                }
                $take = min($remaining, (float) $layer->remaining_quantity);
                $pieces[] = [$layer, $take, (float) $layer->unit_cost];
                $remaining -= $take;
            }
            $fallbackCost = (float) ($item->cost_price ?? 0);
        } else {
            $average = $this->getWeightedAverageCost($item, $warehouseId);
            $total = (float) $layers->sum('remaining_quantity');
            foreach ($layers->values() as $index => $layer) {
                if ($remaining <= 0.00001 || $total <= 0) {
                    break;
                }
                $share = $index === $layers->count() - 1
                    ? $remaining
                    : round($quantity * (float) $layer->remaining_quantity / $total, 4);
                $take = min($share, (float) $layer->remaining_quantity, $remaining);
                if ($take > 0) {
                    $pieces[] = [$layer, $take, $average];
                    $remaining -= $take;
                }
            }
            $fallbackCost = $average;
        }

        if ($remaining > 0.00001) {
            $pieces[] = [null, $remaining, $fallbackCost];
        }

        $cost = 0.0;
        foreach ($pieces as [$layer, $taken, $unitCost]) {
            if ($layer) {
                $layer->remaining_quantity = round((float) $layer->remaining_quantity - $taken, 4);
                $layer->save();
            }

            InventoryLayerConsumption::create([
                'tenant_id' => $item->tenant_id,
                'item_id' => $item->id,
                'inventory_layer_id' => $layer?->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'quantity' => round($taken, 4),
                'unit_cost' => round($unitCost, 4),
                'reduced_on_hand' => $reduceOnHand,
            ]);

            $cost += $taken * $unitCost;
        }

        if ($reduceOnHand) {
            $this->adjustOnHand($item->tenant_id, $item->id, -$quantity, $sourceType, $sourceId, 'out');
        }

        return round($cost, 2);
    }

    /**
     * Send goods back to the supplier (a supplier credit) and return their
     * cost. When the credit is for a bill, the goods come out of that bill's
     * own cost layers first, at the price that bill put them in at; anything
     * more (or with no bill) is taken the item's usual way (FIFO or weighted
     * average) through issue(). Every piece is recorded against the credit,
     * so returnStock() puts it all back if the credit is voided.
     *
     * The stock record's average cost is worked out again without the
     * returned goods, the same way Bill::reverseInventory() does.
     */
    public function returnToSupplier(Item $item, float $quantity, string $sourceType, int $sourceId, ?int $billId = null): float
    {
        if ($quantity <= 0) {
            return 0.0;
        }

        $inventory = Inventory::where('tenant_id', $item->tenant_id)->where('item_id', $item->id)->lockForUpdate()->first();
        $qtyBefore = (float) ($inventory->quantity ?? 0);
        $avgBefore = (float) ($inventory->unit_cost ?? 0);

        $cost = 0.0;
        $remaining = $quantity;

        if ($billId) {
            $layers = InventoryLayer::where('tenant_id', $item->tenant_id)
                ->forItem($item->id)
                ->where('reference_type', 'bill')
                ->where('reference_id', $billId)
                ->withStock()
                ->lockForUpdate()
                ->get();

            $taken = 0.0;
            foreach ($layers as $layer) {
                if ($remaining <= 0.00001) {
                    break;
                }
                $take = min($remaining, (float) $layer->remaining_quantity);
                $layer->remaining_quantity = round((float) $layer->remaining_quantity - $take, 4);
                $layer->save();

                InventoryLayerConsumption::create([
                    'tenant_id' => $item->tenant_id,
                    'item_id' => $item->id,
                    'inventory_layer_id' => $layer->id,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'quantity' => round($take, 4),
                    'unit_cost' => round((float) $layer->unit_cost, 4),
                    'reduced_on_hand' => true,
                ]);

                $cost += $take * (float) $layer->unit_cost;
                $remaining -= $take;
                $taken += $take;
            }

            if ($taken > 0) {
                $this->adjustOnHand($item->tenant_id, $item->id, -$taken, $sourceType, $sourceId, 'out');
            }
        }

        if ($remaining > 0.00001) {
            $cost += $this->issue($item, $remaining, $sourceType, $sourceId, true);
        }

        $cost = round($cost, 2);

        if ($inventory) {
            $inventory->refresh();
            $left = $qtyBefore - $quantity;
            if ($left > 0.00001) {
                $inventory->unit_cost = round(max(0, ($qtyBefore * $avgBefore - $cost) / $left), 4);
                $inventory->save();
            }
        }

        return $cost;
    }

    /**
     * Put back everything a document took with issue(). Safe to call when it
     * took nothing.
     */
    public function returnStock(string $sourceType, int $sourceId): void
    {
        $taken = InventoryLayerConsumption::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->lockForUpdate()
            ->get();

        $onHand = [];
        foreach ($taken as $row) {
            if ($row->inventory_layer_id) {
                InventoryLayer::whereKey($row->inventory_layer_id)
                    ->increment('remaining_quantity', (float) $row->quantity);
            }
            if ($row->reduced_on_hand) {
                $key = $row->tenant_id.':'.$row->item_id;
                $onHand[$key] = ($onHand[$key] ?? 0) + (float) $row->quantity;
            }
            $row->delete();
        }

        foreach ($onHand as $key => $quantity) {
            [$tenantId, $itemId] = array_map('intval', explode(':', $key));
            $this->adjustOnHand($tenantId, $itemId, $quantity, $sourceType, $sourceId, 'in');
        }
    }

    protected function adjustOnHand(int $tenantId, int $itemId, float $delta, string $sourceType, int $sourceId, string $type): void
    {
        $inventory = Inventory::where('tenant_id', $tenantId)->where('item_id', $itemId)->lockForUpdate()->first();
        if (! $inventory) {
            return;
        }

        $inventory->quantity = round((float) $inventory->quantity + $delta, 4);
        $inventory->save();

        InventoryHistory::create([
            'tenant_id' => $tenantId,
            'item_id' => $itemId,
            'type' => $type,
            'quantity' => $delta,
            'reference_type' => strtolower(class_basename($sourceType)),
            'reference_id' => $sourceId,
            'notes' => $sourceType === VendorCredit::class
                ? ($delta < 0 ? 'Sent back to supplier on' : 'Back in stock from voided').' supplier credit #'.$sourceId
                : ($delta < 0 ? 'Sold via ' : 'Returned from ').class_basename($sourceType)." #{$sourceId}",
            'created_by' => auth()->id(),
        ]);
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
