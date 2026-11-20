<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\Inventory;
use App\Models\InventoryLayer;
use App\Models\InventoryLayerConsumption;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Journal;
use App\Services\DepreciationService;
use App\Services\StockValuationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 22 (accounting edge cases):
 * - MB-30: nothing stopped two saves of one document at once both posting
 *   its journal: the document's row is now locked while it posts;
 * - MB-31: stock taken out beyond its cost layers went at a cost made up
 *   from the item's cost price: it is refused, and stock:reconcile-layers
 *   records the missing cost;
 * - MB-32: depreciation checked for a duplicate month outside its
 *   transaction, and counted reversed months as periods.
 */
class Session22AccountingEdgesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
        $this->subscription->update(['ends_at' => '2030-12-31']);
    }

    /** Statements run inside $run, for checking which take a row lock. */
    private function sqlDuring(callable $run): array
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite has no row locks; CI runs this on MariaDB.');
        }
        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = strtolower($query->sql);
        });
        $run();

        return $sql;
    }

    // ---- MB-31: no cost is made up ---------------------------------------

    /** 5 on hand, but only 2 with a recorded cost (stock from before layers were kept). */
    private function driftedItem(float $costPrice = 400): Item
    {
        $item = Item::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Groundnut oil', 'type' => 'product',
            'track_inventory' => true, 'valuation_method' => 'fifo', 'cost_price' => $costPrice]);
        Inventory::create(['tenant_id' => $this->tenant->id, 'item_id' => $item->id, 'quantity' => 5, 'reserved_quantity' => 0, 'unit_cost' => 0]);
        app(StockValuationService::class)->addLayer($this->tenant->id, $item->id, 2, 350);

        return $item;
    }

    public function test_taking_out_more_than_has_a_recorded_cost_is_refused_and_changes_nothing(): void
    {
        $item = $this->driftedItem();

        try {
            app(StockValuationService::class)->issue($item, 5, Invoice::class, 1);
            $this->fail('3 went out at a cost nobody recorded');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Groundnut oil: only 2 of the 5 to take out have a recorded cost', $e->getMessage());
        }

        $this->assertSame(0, InventoryLayerConsumption::count());
        $this->assertEqualsWithDelta(2.0, (float) InventoryLayer::where('item_id', $item->id)->sum('remaining_quantity'), 0.0001);

        // What has a cost still goes out, at that cost.
        $this->assertSame(700.0, app(StockValuationService::class)->issue($item, 2, Invoice::class, 1));
    }

    public function test_the_repair_command_lists_then_records_the_missing_cost(): void
    {
        $item = $this->driftedItem();
        $noCost = Item::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Free sample', 'type' => 'product', 'track_inventory' => true, 'cost_price' => 0]);
        Inventory::create(['tenant_id' => $this->tenant->id, 'item_id' => $noCost->id, 'quantity' => 4, 'reserved_quantity' => 0, 'unit_cost' => 0]);

        $this->artisan('stock:reconcile-layers')
            // One line can meet one expectation: the row is checked by its result.
            ->expectsOutputToContain('| 3       | 400.00    | 1,200.00 | would add')
            ->expectsOutputToContain("no cost to use: set the item's cost price first")
            ->expectsOutputToContain('2 difference(s) found. Dry run')
            ->assertSuccessful();
        $this->assertSame(1, InventoryLayer::count());
        $this->assertSame(0, Journal::where('journal_type', 'stock_adjustment')->count());

        $this->artisan('stock:reconcile-layers', ['--apply' => true])->expectsOutputToContain('1 cost layer(s) added')->assertSuccessful();

        $layer = InventoryLayer::where('item_id', $item->id)->where('reference_type', 'opening_stock')->sole();
        $this->assertEqualsWithDelta(3.0, (float) $layer->remaining_quantity, 0.0001);
        $this->assertEqualsWithDelta(400.0, (float) $layer->unit_cost, 0.0001);
        // Dr Inventory 1,200, Cr Opening Balance Equity 1,200.
        $journal = Journal::where('reference_type', InventoryLayer::class)->where('reference_id', $layer->id)->sole();
        $lines = $journal->entries()->get()->mapWithKeys(fn ($e) => [ChartOfAccount::find($e->account_id)->account_code => [(float) $e->debit, (float) $e->credit]])->all();
        $this->assertSame(['1300' => [1200.0, 0.0], '3900' => [0.0, 1200.0]], $lines);

        // Nothing left to do for it; the item without a cost price is still listed.
        $this->artisan('stock:reconcile-layers')->expectsOutputToContain('1 difference(s) found')->doesntExpectOutputToContain('Groundnut oil')->assertSuccessful();

        // And now all 5 go out at their recorded costs: 2@350 + 3@400.
        $this->assertSame(1900.0, app(StockValuationService::class)->issue($item, 5, Invoice::class, 1));
    }

    // ---- MB-32: depreciation ----------------------------------------------

    private function asset(): FixedAsset
    {
        return FixedAsset::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Delivery van', 'purchase_date' => '2026-01-31', 'in_service_date' => '2026-01-31',
            'purchase_cost' => 60000, 'salvage_value' => 0, 'depreciable_amount' => 60000, 'useful_life' => 5,
            'depreciation_method' => 'straight_line', 'accumulated_depreciation' => 0, 'book_value' => 60000, 'status' => 'active',
        ]);
    }

    public function test_a_month_is_depreciated_once_and_reversed_months_do_not_count_as_periods(): void
    {
        $asset = $this->asset();
        $service = app(DepreciationService::class);

        // A month posted and then reversed is no longer a period.
        $service->recordDepreciation($asset, Carbon::parse('2026-01-31'));
        FixedAssetDepreciation::query()->update(['status' => FixedAssetDepreciation::STATUS_REVERSED]);

        // Straight line hides the period, so check the period handed to the
        // calculation: the first posted month again, not the second.
        $spy = new class extends DepreciationService
        {
            public array $periods = [];

            public function calculateMonthlyDepreciation(FixedAsset $asset, int $periodNumber = 1): float
            {
                $this->periods[] = $periodNumber;

                return parent::calculateMonthlyDepreciation($asset, $periodNumber);
            }
        };
        $redone = $spy->recordDepreciation($asset, Carbon::parse('2026-01-31'));
        $this->assertSame([1], $spy->periods);
        $this->assertSame(2, (int) $redone->period_number, 'the row still takes the next number');

        try {
            $service->recordDepreciation($asset, Carbon::parse('2026-01-31'));
            $this->fail('the same month was posted twice');
        } catch (Exception $e) {
            $this->assertStringContainsString('has already been depreciated for January 2026', $e->getMessage());
        }
        $this->assertSame(1, $asset->depreciations()->where('status', FixedAssetDepreciation::STATUS_POSTED)->count());
        // The caller's copy of the asset follows what was posted.
        $this->assertEqualsWithDelta(2000.0, (float) $asset->accumulated_depreciation, 0.001);
    }

    public function test_depreciation_locks_the_asset_before_checking_for_the_month(): void
    {
        $asset = $this->asset();
        $sql = $this->sqlDuring(fn () => app(DepreciationService::class)->recordDepreciation($asset, Carbon::parse('2026-01-31')));

        $lock = collect($sql)->search(fn ($q) => str_contains($q, 'from `fixed_assets`') && str_contains($q, 'for update'));
        $check = collect($sql)->search(fn ($q) => str_contains($q, 'from `fixed_asset_depreciations`'));
        $this->assertNotFalse($lock, 'the asset row is locked');
        $this->assertLessThan($check, $lock, 'before the duplicate check');
    }

    // ---- MB-30: one live journal per document -------------------------------

    private function invoice(): Invoice
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'status' => 'draft']);
        $invoice->items()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0, 'tax_amount' => 0, 'total' => 5000]);

        return $invoice->fresh();
    }

    public function test_posting_a_document_journal_locks_the_document_first(): void
    {
        $invoice = $this->invoice();
        $sql = $this->sqlDuring(fn () => $invoice->update(['status' => 'sent']));

        $lock = collect($sql)->search(fn ($q) => str_contains($q, 'from `invoices`') && str_contains($q, 'for update'));
        $lookup = collect($sql)->search(fn ($q) => str_contains($q, 'from `journals`') && str_contains($q, '`reference_type`') && str_contains($q, 'for update'));
        $this->assertNotFalse($lock, 'the invoice row is locked');
        $this->assertNotFalse($lookup, 'the current journal is read with a locking read');
        $this->assertLessThan($lookup, $lock);
    }

    public function test_saving_a_document_again_keeps_one_live_journal(): void
    {
        $invoice = $this->invoice();
        $invoice->update(['status' => 'sent']);
        $invoice->fresh()->update(['notes' => 'Thank you']);
        $invoice->fresh()->update(['status' => 'sent']);

        $live = Journal::query()->where('reference_type', Invoice::class)->where('reference_id', $invoice->id)
            ->originals()->where('status', 'posted')->count();
        $this->assertSame(1, $live);
    }
}
