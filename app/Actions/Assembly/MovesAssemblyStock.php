<?php

namespace App\Actions\Assembly;

use App\Models\AssemblyOrder;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\InventoryLayer;
use App\Models\InventoryLayerConsumption;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\Accounting\LockDates;
use App\Services\StockValuationService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Shared steps for the assembly actions (session 14), the same way stock
 * transfers move stock (session 13):
 *
 * - Goods going out (components of a build, finished items of a break-down)
 *   leave through StockValuationService::issue(), recorded against the
 *   order, so their cost follows FIFO layers or the weighted average and
 *   undoing puts each piece back in the layer it came from.
 * - Goods coming in get a cost layer marked with the order, whose value is
 *   exactly the cost given (see putIn()).
 * - Stock history rows say "Used in ASM-000001" / "Made in ASM-000001" and
 *   link to the order.
 */
trait MovesAssemblyStock
{
    protected function valuation(): StockValuationService
    {
        return app(StockValuationService::class);
    }

    /** Lock dates and closed periods (session 11). */
    protected function assertDateOpen(AssemblyOrder $order, mixed $date, string $key = 'assembly_date'): void
    {
        if ($reason = LockDates::instance()->blockReason($date, (int) $order->tenant_id, auth()->user())) {
            throw ValidationException::withMessages([$key => $reason]);
        }
    }

    /** One of this business's warehouses, in use. */
    protected static function ownWarehouse(int $tenantId, mixed $id, string $key): Warehouse
    {
        $warehouse = $id ? Warehouse::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey((int) $id)->first() : null;
        if (! $warehouse) {
            throw ValidationException::withMessages([$key => 'Choose one of your own warehouses.']);
        }
        if (! $warehouse->is_active) {
            throw ValidationException::withMessages([$key => "{$warehouse->name} is not in use any more."]);
        }

        return $warehouse;
    }

    /**
     * Refuse when there isn't enough free stock (on hand less reserved for
     * invoices) of each item in the warehouse.
     *
     * @param  array<int, float>  $needed  item id => quantity
     * @param  Collection<int, Item>  $items  keyed by id
     */
    protected function assertFree(AssemblyOrder $order, array $needed, Collection $items, Warehouse $warehouse, string $key, string $what): void
    {
        foreach ($needed as $itemId => $wanted) {
            if ($wanted <= 0.00001) {
                continue;
            }
            $item = $items->get($itemId);
            $row = $this->valuation()->stockRow((int) $order->tenant_id, (int) $itemId, $warehouse->id, false);
            $onHand = (float) ($row->quantity ?? 0);
            $reserved = (float) ($row->reserved_quantity ?? 0);
            $free = max(0, $onHand - $reserved);
            if ($wanted - $free > 0.00001) {
                $why = $reserved > 0 ? " ({$this->qty($onHand)} on hand, {$this->qty($reserved)} reserved for invoices)" : '';
                throw ValidationException::withMessages([$key => "Only {$this->qty($free, $item)} {$item->name} free in {$warehouse->name}{$why}, but {$what} needs {$this->qty($wanted, $item)}."]);
            }
        }
    }

    /**
     * Take goods out of a warehouse at their FIFO / average cost, recorded
     * against $sourceType/$sourceId. Returns their cost.
     */
    protected function takeOut(AssemblyOrder $order, Item $item, float $quantity, string $sourceType, int $sourceId, int $warehouseId, string $note): float
    {
        if ($quantity <= 0) {
            return 0.0;
        }
        $row = $this->valuation()->stockRow((int) $order->tenant_id, $item->id, $warehouseId);
        $qtyBefore = (float) $row->quantity;
        $avgBefore = (float) $row->unit_cost;

        $cost = $this->valuation()->issue($item, $quantity, $sourceType, $sourceId, false, $warehouseId);

        $left = round($qtyBefore - $quantity, 4);
        $row->quantity = $left;
        if ($left > 0.00001) {
            // Average cost of what stays behind.
            $row->unit_cost = round(max(0, ($qtyBefore * $avgBefore - $cost) / $left), 4);
            $this->averageFromLayers($row);
        }
        $row->save();
        $this->history($order, $item->id, $warehouseId, -$quantity, $note);

        return $cost;
    }

    /**
     * Put goods into a warehouse as a cost layer worth exactly $cost.
     *
     * Layer costs are kept to 4 decimals, so 39 bags for ₦1,000,000.00
     * (₦25,641.0256... each) can't be one layer worth exactly ₦1,000,000.00.
     * Then all but one unit go in at the rounded cost and the last unit
     * carries the difference (a fraction of a kobo), so the two layers add
     * up to the cost exactly. Fractional quantities get one layer.
     */
    protected function putIn(AssemblyOrder $order, Item $item, float $quantity, float $cost, int $warehouseId, string $note): void
    {
        if ($quantity <= 0) {
            return;
        }
        $cost = round($cost, 2);
        $unit = round($cost / $quantity, 4);
        $whole = abs($quantity - round($quantity)) < 0.00001 && $quantity >= 2;

        if ($whole && abs($unit * $quantity - $cost) > 0.000001) {
            $first = round($quantity - 1, 4);
            $this->layer($order, $item, $first, $unit, $warehouseId);
            $this->layer($order, $item, 1, round($cost - $first * $unit, 4), $warehouseId);
        } else {
            $this->layer($order, $item, $quantity, $unit, $warehouseId);
        }

        $this->addOnHand((int) $order->tenant_id, $item->id, $warehouseId, $quantity, $cost);
        $this->history($order, $item->id, $warehouseId, $quantity, $note);
    }

    private function layer(AssemblyOrder $order, Item $item, float $quantity, float $unitCost, int $warehouseId): void
    {
        $layer = $this->valuation()->addLayer((int) $order->tenant_id, $item->id, $quantity, $unitCost, $warehouseId, 'assembly_order', $order->id);
        $layer->forceFill(['received_date' => $order->movementDate()])->save();
    }

    /** The cost layers an order put an item into a warehouse with. @return Collection<int, InventoryLayer> */
    protected function layersMadeBy(AssemblyOrder $order, int $itemId, int $warehouseId): Collection
    {
        return InventoryLayer::withoutGlobalScopes()->where('tenant_id', $order->tenant_id)
            ->where('reference_type', 'assembly_order')->where('reference_id', $order->id)
            ->where('item_id', $itemId)->where('warehouse_id', $warehouseId)
            ->lockForUpdate()->get();
    }

    /**
     * Whether what an order put in is all still there and free: none of its
     * layers touched (sold, used, reserved by an invoice) and enough free
     * stock in the warehouse.
     */
    protected function stillInStock(AssemblyOrder $order, int $itemId, int $warehouseId, float $quantity): bool
    {
        if ($quantity <= 0) {
            return true;
        }
        $layers = $this->layersMadeBy($order, $itemId, $warehouseId);
        $untouched = $layers->every(fn (InventoryLayer $l) => abs((float) $l->remaining_quantity - (float) $l->quantity) < 0.00001);
        if ($layers->isEmpty() || ! $untouched || abs((float) $layers->sum('quantity') - $quantity) > 0.0001) {
            return false;
        }
        $row = $this->valuation()->stockRow((int) $order->tenant_id, $itemId, $warehouseId, false);

        return $row && (float) $row->quantity - (float) $row->reserved_quantity - $quantity > -0.00001;
    }

    /** Take out what putIn() put in: its layers go and the stock record drops. */
    protected function removeMade(AssemblyOrder $order, int $itemId, int $warehouseId, float $quantity, float $cost, string $note): void
    {
        if ($quantity <= 0) {
            return;
        }
        foreach ($this->layersMadeBy($order, $itemId, $warehouseId) as $layer) {
            $layer->delete();
        }
        $row = $this->valuation()->stockRow((int) $order->tenant_id, $itemId, $warehouseId);
        $qtyBefore = (float) $row->quantity;
        $avgBefore = (float) $row->unit_cost;
        $left = round($qtyBefore - $quantity, 4);
        $row->quantity = $left;
        if ($left > 0.00001) {
            $row->unit_cost = round(max(0, ($qtyBefore * $avgBefore - $cost) / $left), 4);
            $this->averageFromLayers($row);
        }
        $row->save();
        $this->history($order, $itemId, $warehouseId, -$quantity, $note);
    }

    /**
     * Put back everything taken with takeOut() for one source, each piece in
     * the cost layer it came from (a new layer at the same cost if it came
     * from none). Returns the cost put back.
     */
    protected function putBack(AssemblyOrder $order, Item $item, string $sourceType, int $sourceId, string $note): float
    {
        $rows = InventoryLayerConsumption::withoutGlobalScopes()->where('tenant_id', $order->tenant_id)
            ->where('source_type', $sourceType)->where('source_id', $sourceId)->where('item_id', $item->id)
            ->orderBy('id')->lockForUpdate()->get();

        $byWarehouse = [];
        foreach ($rows as $row) {
            $warehouseId = (int) $row->warehouse_id;
            if ($row->inventory_layer_id && InventoryLayer::withoutGlobalScopes()->whereKey($row->inventory_layer_id)->exists()) {
                InventoryLayer::withoutGlobalScopes()->whereKey($row->inventory_layer_id)->increment('remaining_quantity', (float) $row->quantity);
            } else {
                $this->valuation()->addLayer((int) $order->tenant_id, $item->id, (float) $row->quantity, (float) $row->unit_cost,
                    $warehouseId, 'assembly_undo', $order->id); // not 'assembly_order': those are what the order made
            }
            $byWarehouse[$warehouseId]['qty'] = ($byWarehouse[$warehouseId]['qty'] ?? 0) + (float) $row->quantity;
            $byWarehouse[$warehouseId]['cost'] = ($byWarehouse[$warehouseId]['cost'] ?? 0) + (float) $row->quantity * (float) $row->unit_cost;
            $row->delete();
        }

        $total = 0.0;
        foreach ($byWarehouse as $warehouseId => $back) {
            $cost = round($back['cost'], 2);
            $this->addOnHand((int) $order->tenant_id, $item->id, $warehouseId, round($back['qty'], 4), $cost);
            $this->history($order, $item->id, $warehouseId, $back['qty'], $note);
            $total += $cost;
        }

        return round($total, 2);
    }

    /** Add goods to a warehouse's stock record, keeping its average cost right. */
    protected function addOnHand(int $tenantId, int $itemId, int $warehouseId, float $quantity, float $cost): void
    {
        $row = $this->valuation()->stockRow($tenantId, $itemId, $warehouseId);
        $this->valuation()->updateWeightedAverageCost($row, $quantity, $quantity > 0 ? $cost / $quantity : 0);
        $row->quantity = round((float) $row->quantity + $quantity, 4);
        $this->averageFromLayers($row);
        $row->save();
    }

    /** When the cost layers hold exactly the stock on hand, take the average from them. */
    protected function averageFromLayers(Inventory $row): void
    {
        $layers = InventoryLayer::withoutGlobalScopes()->where('tenant_id', $row->tenant_id)
            ->forItem((int) $row->item_id, (int) $row->warehouse_id)->where('remaining_quantity', '>', 0)
            ->selectRaw('SUM(remaining_quantity) as qty, SUM(remaining_quantity * unit_cost) as value')->toBase()->first();
        $qty = (float) ($layers->qty ?? 0);
        if ($qty > 0.00001 && abs($qty - (float) $row->quantity) < 0.0001) {
            $row->unit_cost = round((float) $layers->value / $qty, 4);
        }
    }

    protected function history(AssemblyOrder $order, int $itemId, int $warehouseId, float $quantity, string $note): void
    {
        $history = new InventoryHistory([
            'tenant_id' => $order->tenant_id,
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'type' => 'assembly',
            'quantity' => round($quantity, 4),
            'reference_type' => 'assembly_order',
            'reference_id' => $order->id,
            'notes' => $note,
            'created_by' => auth()->id(),
        ]);
        $history->skipTenantGuard = true;
        $history->save();
    }

    /** "700 kg" (with the item's unit when it has one), no trailing zeros. */
    protected function qty(float $value, ?Item $item = null): string
    {
        $number = rtrim(rtrim(number_format($value, 4, '.', ','), '0'), '.');

        return $item && $item->unit ? "{$number} {$item->unit}" : $number;
    }
}
