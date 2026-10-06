<?php

namespace App\Console\Commands;

use App\Models\ChartOfAccount;
use App\Models\InventoryHistory;
use App\Models\InventoryLayer;
use App\Models\Item;
use App\Models\Journal;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\AccountCodeService;
use App\Services\Accounting\LockDates;
use App\Services\JournalService;
use App\Services\StockValuationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Stock adjustments and imported opening stock used to post no journal
 * (F1). Run once after deploying, per business:
 *
 * - finds past adjustments (stock in / out / counts, web and API) and
 *   imported opening stock with no journal;
 * - posts each one dated the day it was made: stock out Dr Stock Losses,
 *   Cr Inventory; stock in the reverse; opening stock Dr Inventory,
 *   Cr Opening Balance Equity. Days behind a lock date or in a closed
 *   period are listed and skipped;
 * - imported opening stock also gets the cost layer it never had (only
 *   for what is still in stock without a layer);
 * - prints the Inventory account against the stock value (cost layers)
 *   before and after, so you can see whether anything is left to explain.
 *
 * Costs: stock in at the cost of the layer it made (else the item's cost
 * price); opening stock at the item's cost price. Stock taken out is
 * estimated at the warehouse's current average cost, because the old code
 * didn't record what it took. --dry-run shows everything without posting.
 */
class PostMissingAdjustmentJournals extends Command
{
    protected $signature = 'stock:post-missing-adjustment-journals
        {--dry-run : Show what would be posted without changing anything}
        {--tenant= : Only this business (id)}';

    protected $description = 'Post the missing journals for old stock adjustments and imported opening stock';

    /** History rows written by adjustments and imports (all other stock movements have their own reference). */
    private const REFERENCES = ['adjustment', 'api_adjustment', 'opening_stock'];

    /** Layer ids already matched to a row in this run. @var array<int, bool> */
    private array $usedLayers = [];

    public function handle(JournalService $journals, StockValuationService $valuation): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->usedLayers = [];
        $tenants = Tenant::query()->when($this->option('tenant'), fn ($q, $id) => $q->whereKey((int) $id))->orderBy('id')->get();

        $posted = 0;
        $skipped = 0;
        foreach ($tenants as $tenant) {
            [$p, $s] = $this->tenant($tenant, $journals, $valuation, $dry);
            $posted += $p;
            $skipped += $s;
        }

        $this->newLine();
        $this->info(($dry ? 'Would post' : 'Posted')." {$posted} journal(s); {$skipped} skipped (locked or closed dates).");

        return self::SUCCESS;
    }

    /** @return array{0: int, 1: int} posted, skipped */
    private function tenant(Tenant $tenant, JournalService $journals, StockValuationService $valuation, bool $dry): array
    {
        $t = (int) $tenant->id;
        $rows = InventoryHistory::withoutGlobalScopes()->where('tenant_id', $t)
            ->whereIn('type', ['in', 'out', 'adjustment'])
            ->where(fn ($q) => $q->whereNull('reference_type')->orWhereIn('reference_type', self::REFERENCES))
            ->where('quantity', '!=', 0)
            ->orderBy('created_at')->orderBy('id')->get();
        $done = Journal::withoutGlobalScopes()->where('tenant_id', $t)->where('reference_type', InventoryHistory::class)
            ->whereIn('reference_id', $rows->pluck('id'))->pluck('reference_id')->flip();
        $rows = $rows->reject(fn ($r) => $done->has($r->id));

        [$ledgerBefore, $stockBefore] = $this->gap($t);
        if ($rows->isEmpty() && abs($ledgerBefore - $stockBefore) < 0.005) {
            return [0, 0];
        }

        $this->newLine();
        $this->line("<options=bold>Business {$t}: {$tenant->name}</>");
        $this->line('  Inventory account '.$this->money($ledgerBefore).', stock value '.$this->money($stockBefore)
            .', difference '.$this->money($ledgerBefore - $stockBefore));
        if ($rows->isEmpty()) {
            $this->line('  No adjustments without a journal. The difference comes from something else (e.g. a manual journal or stock reset in the old stock list).');

            return [0, 0];
        }

        $lossCode = AccountCodeService::resolve($t, 'stock_losses');
        $equityCode = AccountCodeService::resolve($t, 'opening_balance_equity');
        $inventoryCode = AccountCodeService::resolve($t, 'inventory');
        $missing = array_filter([$lossCode, $equityCode, $inventoryCode],
            fn ($code) => ! ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $t)->where('account_code', $code)->exists());
        if ($missing) {
            $this->warn('  Skipped: account(s) '.implode(', ', $missing).' missing from the chart of accounts.');

            return [0, $rows->count()];
        }

        $table = [];
        $posted = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $item = Item::withoutGlobalScopes()->withTrashed()->find($row->item_id);
            if (! $item) {
                continue;
            }
            $warehouseId = (int) ($row->warehouse_id ?: Warehouse::defaultIdFor($t));
            $date = Carbon::parse($row->created_at)->toDateString();
            $qty = abs((float) $row->quantity);
            $in = match ($row->type) {
                'in' => true,
                'out' => false,
                default => (float) $row->quantity > 0,
            };
            $opening = $row->reference_type === 'opening_stock' || str_starts_with((string) $row->notes, 'Initial stock from import');

            $layer = null;
            if ($opening) {
                $unitCost = (float) ($item->cost_price ?? 0);
                $basis = 'cost price';
            } elseif ($in) {
                $layer = $this->matchLayer($t, $item->id, $warehouseId, $qty, $date);
                $unitCost = $layer ? (float) $layer->unit_cost : (float) ($item->cost_price ?? 0);
                $basis = $layer ? 'its cost layer' : 'cost price';
            } else {
                $unitCost = $valuation->getWeightedAverageCost($item, $warehouseId);
                $basis = 'estimate: average cost now';
            }
            $value = round($qty * $unitCost, 2);
            $label = $opening ? 'Opening stock' : ($row->type === 'adjustment' ? 'Stock count' : 'Stock adjustment');
            $offset = $opening ? $equityCode : $lossCode;
            $blocked = LockDates::instance()->blockReason($date, $t);

            $status = match (true) {
                $value < 0.01 => 'nothing to post (no cost)',
                $blocked !== null => 'SKIPPED: date is locked or closed',
                $dry => 'would post',
                default => 'posted',
            };
            $table[] = [$date, $row->id, $label, $item->name, ($in ? '+' : '−').$this->qty($qty), $this->money($value), $basis, $status];

            if ($value < 0.01) {
                continue;
            }
            if ($blocked !== null) {
                $skipped++;

                continue;
            }
            $posted++;
            if ($dry) {
                continue;
            }

            DB::transaction(function () use ($row, $item, $t, $warehouseId, $qty, $in, $opening, $layer, $unitCost, $value, $label, $offset, $inventoryCode, $date, $journals) {
                if ($opening) {
                    $this->addOpeningLayer($row, $t, $item->id, $warehouseId, $qty, $unitCost, $date);
                } elseif ($layer) {
                    $layer->forceFill(['reference_id' => $row->id])->save();
                }
                $warehouse = Warehouse::withoutGlobalScopes()->whereKey($warehouseId)->value('name') ?? 'Main warehouse';
                $what = "{$item->name} ".($in ? '+' : '−').$this->qty($qty).($item->unit ? " {$item->unit}" : '');
                $text = mb_substr("{$label}: {$what}", 0, 190);
                $lines = $in
                    ? [[$inventoryCode, $value, 0.0, $text], [$offset, 0.0, $value, $text]]
                    : [[$offset, $value, 0.0, $text], [$inventoryCode, 0.0, $value, $text]];
                $journals->postLines($row, 'STK-'.$row->id, $date, mb_substr("{$label}, {$warehouse}: {$what} (posted late)", 0, 250),
                    $lines, $row->created_by, 'stock_adjustment');
            });
        }

        $this->table(['Date', 'Row', 'What', 'Item', 'Qty', 'Value', 'Cost used', 'Result'], $table);

        if (! $dry && $posted) {
            [$ledger, $stock] = $this->gap($t);
            $this->line('  After: Inventory account '.$this->money($ledger).', stock value '.$this->money($stock)
                .', difference '.$this->money($ledger - $stock));
        }

        return [$posted, $skipped];
    }

    /** An old stock-in adjustment's own cost layer, matched by item, warehouse, quantity and day. */
    private function matchLayer(int $t, int $itemId, int $warehouseId, float $qty, string $date): ?InventoryLayer
    {
        $layer = InventoryLayer::withoutGlobalScopes()->where('tenant_id', $t)->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)->where('reference_type', 'adjustment')->whereNull('reference_id')
            ->whereDate('received_date', $date)->whereBetween('quantity', [$qty - 0.0001, $qty + 0.0001])
            ->whereNotIn('id', array_keys($this->usedLayers))->orderBy('id')->first();
        if ($layer) {
            $this->usedLayers[$layer->id] = true;
        }

        return $layer;
    }

    /** Imported opening stock never got a layer: add one for what is still there without a layer. */
    private function addOpeningLayer(InventoryHistory $row, int $t, int $itemId, int $warehouseId, float $qty, float $unitCost, string $date): void
    {
        $onHand = (float) DB::table('inventories')->where('tenant_id', $t)->where('item_id', $itemId)->where('warehouse_id', $warehouseId)->value('quantity');
        $layered = (float) InventoryLayer::withoutGlobalScopes()->where('tenant_id', $t)->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)->sum('remaining_quantity');
        $add = round(min($qty, max(0, $onHand - $layered)), 4);
        if ($add <= 0) {
            return;
        }

        $layer = new InventoryLayer([
            'tenant_id' => $t, 'item_id' => $itemId, 'warehouse_id' => $warehouseId, 'quantity' => $add, 'remaining_quantity' => $add,
            'unit_cost' => $unitCost, 'reference_type' => 'opening_stock', 'reference_id' => $row->id, 'received_date' => $date,
        ]);
        $layer->skipTenantGuard = true;
        $layer->save();
    }

    /**
     * The Inventory account's balance (posted journals plus its opening
     * balance) and the stock value in the cost layers.
     *
     * @return array{0: float, 1: float}
     */
    public static function gap(int $tenantId): array
    {
        $account = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where('account_code', AccountCodeService::resolve($tenantId, 'inventory'))->first();
        $ledger = 0.0;
        if ($account) {
            $ledger = (float) $account->opening_balance + (float) DB::table('journal_entries as je')
                ->join('journals as j', 'j.id', '=', 'je.journal_id')
                ->where('j.tenant_id', $tenantId)->where('je.account_id', $account->id)
                ->where('j.is_posted', true)->whereNull('j.deleted_at')
                ->selectRaw('COALESCE(SUM(je.debit - je.credit), 0) as v')->value('v');
        }
        $stock = (float) InventoryLayer::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where('remaining_quantity', '>', 0)->selectRaw('SUM(remaining_quantity * unit_cost) as v')->value('v');

        return [round($ledger, 2), round($stock, 2)];
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2);
    }

    private function qty(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.');
    }
}
