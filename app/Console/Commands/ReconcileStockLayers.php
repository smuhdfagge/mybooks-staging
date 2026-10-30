<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\AccountCodeService;
use App\Services\JournalService;
use App\Services\StockValuationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Stock on hand with no cost layer behind it (audit MB-31): stock recorded
 * before cost layers were kept, or written past the app. Taking it out is
 * refused (StockValuationService::issue) rather than costed at a price the
 * Inventory account never held, so this records what it cost.
 *
 * Without --apply it only lists, per business, item and warehouse, the
 * stock on hand, the stock with a cost layer, and what would be added.
 * Stock on a posted invoice not yet released is still on hand but already
 * out of the layers (the invoice took its cost when posted), so it is not
 * missing a cost.
 * With --apply each shortfall gets a cost layer at the warehouse's average
 * cost (else the item's cost price) and a journal: Dr Inventory,
 * Cr Opening Balance Equity, dated today (or the first open date). An item
 * with no cost to use, or with more in its layers than on hand, is listed
 * and left alone: those need a person (a cost price, a stock count). A
 * row that can't be recorded (an account missing, a closed month) is
 * listed as failed and the rest carry on.
 *
 *   php artisan stock:reconcile-layers [--tenant=ID] [--apply]
 */
class ReconcileStockLayers extends Command
{
    protected $signature = 'stock:reconcile-layers
        {--apply : Add the missing cost layers and post their journals (needs the owner\'s go-ahead)}
        {--tenant= : One business only (id)}';

    protected $description = 'List stock on hand with no recorded cost, and with --apply record it';

    public function handle(JournalService $journals, StockValuationService $valuation): int
    {
        $apply = (bool) $this->option('apply');
        $tenants = Tenant::query()->when($this->option('tenant'), fn ($q, $id) => $q->whereKey((int) $id))->orderBy('id')->get();
        $found = 0;
        $fixed = 0;

        foreach ($tenants as $tenant) {
            [$f, $x] = Tenant::run($tenant, fn () => $this->tenant($tenant, $journals, $valuation, $apply));
            $found += $f;
            $fixed += $x;
        }

        $this->newLine();
        $this->line($apply
            ? "{$fixed} cost layer(s) added, {$found} difference(s) found."
            : "{$found} difference(s) found. Dry run: nothing was changed. Run again with --apply once the owner agrees.");

        return self::SUCCESS;
    }

    /** @return array{0: int, 1: int} found, fixed */
    private function tenant(Tenant $tenant, JournalService $journals, StockValuationService $valuation, bool $apply): array
    {
        $t = (int) $tenant->id;
        $layered = DB::table('inventory_layers')->where('tenant_id', $t)
            ->selectRaw('item_id, warehouse_id, SUM(remaining_quantity) as qty')->groupBy('item_id', 'warehouse_id')->get()
            ->keyBy(fn ($row) => $row->item_id.':'.$row->warehouse_id);
        $unreleased = DB::table('inventory_layer_consumptions as c')
            ->join('invoices as i', 'i.id', '=', 'c.source_id')
            ->where('c.tenant_id', $t)->where('c.source_type', Invoice::class)->where('c.reduced_on_hand', false)
            ->whereNull('i.released_at')
            ->selectRaw('c.item_id, c.warehouse_id, SUM(c.quantity) as qty')->groupBy('c.item_id', 'c.warehouse_id')->get()
            ->keyBy(fn ($row) => $row->item_id.':'.$row->warehouse_id);
        $stock = DB::table('inventories')->join('items', 'items.id', '=', 'inventories.item_id')
            ->where('inventories.tenant_id', $t)->where('items.track_inventory', true)->whereNull('items.deleted_at')
            ->orderBy('items.name')->orderBy('inventories.warehouse_id')
            ->get(['inventories.item_id', 'inventories.warehouse_id', 'inventories.quantity', 'inventories.unit_cost', 'items.cost_price', 'items.name']);

        $rows = [];
        $found = 0;
        $fixed = 0;
        foreach ($stock as $row) {
            $warehouseId = (int) ($row->warehouse_id ?: Warehouse::defaultIdFor($t));
            $withCost = (float) ($layered->get($row->item_id.':'.$warehouseId)->qty ?? 0);
            $awaiting = (float) ($unreleased->get($row->item_id.':'.$warehouseId)->qty ?? 0);
            $onHand = (float) $row->quantity;
            $missing = round($onHand - $awaiting - $withCost, 4);
            if (abs($missing) <= 0.0001) {
                continue;
            }
            $found++;
            $cost = round((float) $row->unit_cost > 0 ? (float) $row->unit_cost : (float) $row->cost_price, 4);

            $result = match (true) {
                $missing < 0 => 'layers exceed on hand: check with a stock count',
                $cost <= 0 => 'no cost to use: set the item\'s cost price first',
                ! $apply => 'would add',
                default => null,
            };
            if ($result === null) {
                try {
                    $this->record($t, (int) $row->item_id, $warehouseId, $missing, $cost, $journals, $valuation);
                    $fixed++;
                    $result = 'added';
                } catch (\Throwable $e) {
                    $result = 'failed: '.mb_substr($e->getMessage(), 0, 120);
                }
            }

            $rows[] = [$row->name, Warehouse::withoutGlobalScopes()->whereKey($warehouseId)->value('name') ?? '#'.$warehouseId,
                $this->qty($onHand), $this->qty($awaiting), $this->qty($withCost), $this->qty($missing), number_format($cost, 2), number_format(max(0, $missing) * $cost, 2), $result];
        }

        if ($rows) {
            $this->newLine();
            $this->info("Business {$t}: {$tenant->name}");
            $this->table(['Item', 'Warehouse', 'On hand', 'Sold, not released', 'With a cost', 'Missing', 'Unit cost', 'Value', 'Result'], $rows);
        }

        return [$found, $fixed];
    }

    /** The missing layer, and its journal: Dr Inventory, Cr Opening Balance Equity. */
    private function record(int $t, int $itemId, int $warehouseId, float $quantity, float $cost, JournalService $journals, StockValuationService $valuation): void
    {
        DB::transaction(function () use ($t, $itemId, $warehouseId, $quantity, $cost, $journals, $valuation) {
            $item = Item::query()->findOrFail($itemId);
            $layer = $valuation->addLayer($t, $itemId, $quantity, $cost, $warehouseId, 'opening_stock');
            $date = $journals->firstOpenDate($t, today());
            $value = round($quantity * $cost, 2);
            $text = mb_substr("Opening stock with no recorded cost: {$item->name} {$this->qty($quantity)}", 0, 190);
            $journals->postLines($layer, 'STK-L'.$layer->id, $date, $text, [
                [AccountCodeService::resolve($t, 'inventory'), $value, 0.0, $text],
                [AccountCodeService::resolve($t, 'opening_balance_equity'), 0.0, $value, $text],
            ], null, 'stock_adjustment');
        });
    }

    private function qty(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.');
    }
}
