<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\UncostedStock;
use Illuminate\Console\Command;

/**
 * Stock on hand with no cost layer behind it (audit MB-31): stock recorded
 * before cost layers were kept, or written past the app. Taking it out is
 * refused (StockValuationService::issue) rather than costed at a price the
 * Inventory account never held, so this records what it cost.
 *
 * The work is in App\Services\UncostedStock, which the Missing Stock Costs
 * page (Items & Stock) also uses.
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

    public function handle(UncostedStock $uncosted): int
    {
        $apply = (bool) $this->option('apply');
        $tenants = Tenant::query()->when($this->option('tenant'), fn ($q, $id) => $q->whereKey((int) $id))->orderBy('id')->get();
        $found = 0;
        $fixed = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            [$f, $x, $e] = Tenant::run($tenant, fn () => $this->tenant($tenant, $uncosted, $apply));
            $found += $f;
            $fixed += $x;
            $failed += $e;
        }

        $this->newLine();
        $this->line($apply
            ? "{$fixed} cost layer(s) added, {$found} difference(s) found.".($failed ? " {$failed} failed: see the table." : '')
            : "{$found} difference(s) found. Dry run: nothing was changed. Run again with --apply once the owner agrees.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @return array{0: int, 1: int, 2: int} found, fixed, failed */
    private function tenant(Tenant $tenant, UncostedStock $uncosted, bool $apply): array
    {
        $t = (int) $tenant->id;
        $rows = [];
        $found = 0;
        $fixed = 0;
        $failed = 0;
        foreach ($uncosted->find($t) as $row) {
            $found++;
            $missing = $row['missing'];
            $cost = $row['suggested_cost'];

            $result = match (true) {
                $missing < 0 => 'layers exceed on hand: check with a stock count',
                $cost <= 0 => 'no cost to use: set the item\'s cost price first',
                ! $apply => 'would add',
                default => null,
            };
            if ($result === null) {
                try {
                    $uncosted->record($t, $row['item_id'], $row['warehouse_id'], $cost);
                    $fixed++;
                    $result = 'added';
                } catch (\Throwable $e) {
                    $failed++;
                    $result = 'failed: '.mb_substr($e->getMessage(), 0, 120);
                }
            }

            $rows[] = [$row['item_name'], $row['warehouse_name'],
                UncostedStock::quantity($row['on_hand']), UncostedStock::quantity($row['awaiting']), UncostedStock::quantity($row['with_cost']),
                UncostedStock::quantity($missing), number_format($cost, 2), number_format(max(0, $missing) * $cost, 2), $result];
        }

        if ($rows) {
            $this->newLine();
            $this->info("Business {$t}: {$tenant->name}");
            $this->table(['Item', 'Warehouse', 'On hand', 'Sold, not released', 'With a cost', 'Missing', 'Unit cost', 'Value', 'Result'], $rows);
        }

        return [$found, $fixed, $failed];
    }
}
