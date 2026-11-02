<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Stock on hand with no cost layer behind it (audit MB-31): stock recorded
 * before cost layers were kept, or written past the app. Taking it out is
 * refused (StockValuationService::issue) rather than costed at a price the
 * Inventory account never held, so a person records what it cost: on the
 * Missing Stock Costs page, or with stock:reconcile-layers.
 *
 * Stock on a posted invoice not yet released is still on hand but already
 * out of the layers (the invoice took its cost when posted), so it is not
 * missing a cost.
 */
class UncostedStock
{
    public function __construct(
        protected JournalService $journals,
        protected StockValuationService $valuation,
    ) {}

    /**
     * Each item and warehouse whose stock on hand and cost layers differ.
     * 'missing' is on hand with no cost (negative: more in the layers than
     * on hand, which needs a stock count); 'suggested_cost' is the
     * warehouse's average cost, else the item's cost price, else 0.
     *
     * @return list<array{item_id: int, item_name: string, warehouse_id: int, warehouse_name: string, on_hand: float, awaiting: float, with_cost: float, missing: float, suggested_cost: float}>
     */
    public function find(int $tenantId, ?int $itemId = null, ?int $warehouseId = null, bool $lock = false): array
    {
        $defaultWarehouse = null;
        $warehouseOf = function ($id) use ($tenantId, &$defaultWarehouse) {
            return (int) ($id ?: ($defaultWarehouse ??= Warehouse::defaultIdFor($tenantId)));
        };

        $stockQuery = DB::table('inventories')->join('items', 'items.id', '=', 'inventories.item_id')
            ->where('inventories.tenant_id', $tenantId)->where('items.track_inventory', true)->whereNull('items.deleted_at')
            ->when($itemId, fn ($q) => $q->where('inventories.item_id', $itemId))
            ->orderBy('items.name')->orderBy('inventories.warehouse_id');
        if ($lock) {
            // Two people recording the same stock at once: the second waits, then finds nothing missing.
            $stockQuery->lockForUpdate();
        }
        $stock = $stockQuery->get(['inventories.item_id', 'inventories.warehouse_id', 'inventories.quantity', 'inventories.unit_cost', 'items.cost_price', 'items.name']);
        if ($stock->isEmpty()) {
            return [];
        }

        $layered = DB::table('inventory_layers')->where('tenant_id', $tenantId)
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId))
            ->selectRaw('item_id, warehouse_id, SUM(remaining_quantity) as qty')->groupBy('item_id', 'warehouse_id')->get()
            ->keyBy(fn ($row) => $row->item_id.':'.$row->warehouse_id);
        $unreleased = DB::table('inventory_layer_consumptions as c')
            ->join('invoices as i', 'i.id', '=', 'c.source_id')
            ->where('c.tenant_id', $tenantId)->where('c.source_type', Invoice::class)->where('c.reduced_on_hand', false)
            ->whereNull('i.released_at')
            ->when($itemId, fn ($q) => $q->where('c.item_id', $itemId))
            ->selectRaw('c.item_id, c.warehouse_id, SUM(c.quantity) as qty')->groupBy('c.item_id', 'c.warehouse_id')->get()
            ->keyBy(fn ($row) => $row->item_id.':'.$row->warehouse_id);
        $warehouseNames = Warehouse::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('name', 'id');

        $rows = [];
        foreach ($stock as $row) {
            $warehouse = $warehouseOf($row->warehouse_id);
            if ($warehouseId && $warehouse !== $warehouseId) {
                continue;
            }
            $withCost = (float) ($layered->get($row->item_id.':'.$warehouse)->qty ?? 0);
            $awaiting = (float) ($unreleased->get($row->item_id.':'.$warehouse)->qty ?? 0);
            $onHand = (float) $row->quantity;
            $missing = round($onHand - $awaiting - $withCost, 4);
            if (abs($missing) <= 0.0001) {
                continue;
            }

            $rows[] = [
                'item_id' => (int) $row->item_id,
                'item_name' => (string) $row->name,
                'warehouse_id' => $warehouse,
                'warehouse_name' => (string) ($warehouseNames[$warehouse] ?? '#'.$warehouse),
                'on_hand' => $onHand,
                'awaiting' => $awaiting,
                'with_cost' => $withCost,
                'missing' => $missing,
                'suggested_cost' => round((float) $row->unit_cost > 0 ? (float) $row->unit_cost : (float) $row->cost_price, 4),
            ];
        }

        return $rows;
    }

    /**
     * Record the cost of an item's stock with none in one warehouse: a cost
     * layer for what is missing, and its journal (Dr Inventory, Cr Opening
     * Balance Equity) dated today, or the first open date. What is missing
     * is worked out again here, not taken from the page.
     *
     * @return array{quantity: float, value: float}
     */
    public function record(int $tenantId, int $itemId, int $warehouseId, float $unitCost): array
    {
        $unitCost = round($unitCost, 4);
        if ($unitCost <= 0) {
            throw new BusinessRuleException('Enter a unit cost above zero.');
        }

        return DB::transaction(function () use ($tenantId, $itemId, $warehouseId, $unitCost) {
            $row = $this->find($tenantId, $itemId, $warehouseId, lock: true)[0] ?? null;
            if (! $row || $row['missing'] <= 0) {
                throw new BusinessRuleException($row
                    ? 'This stock has more with a cost than is on hand, so there is no cost to record. Count the stock instead.'
                    : 'This stock already has a recorded cost.');
            }

            $quantity = $row['missing'];
            $item = Item::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($itemId);
            $layer = $this->valuation->addLayer($tenantId, $itemId, $quantity, $unitCost, $warehouseId, 'opening_stock');
            $date = $this->journals->firstOpenDate($tenantId, today());
            $value = round($quantity * $unitCost, 2);
            $text = mb_substr("Opening stock with no recorded cost: {$item->name} ".self::quantity($quantity), 0, 190);
            $this->journals->postLines($layer, 'STK-L'.$layer->id, $date, $text, [
                [AccountCodeService::resolve($tenantId, 'inventory'), $value, 0.0, $text],
                [AccountCodeService::resolve($tenantId, 'opening_balance_equity'), 0.0, $value, $text],
            ], null, 'stock_adjustment');

            return ['quantity' => $quantity, 'value' => $value];
        });
    }

    public static function quantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.');
    }
}
