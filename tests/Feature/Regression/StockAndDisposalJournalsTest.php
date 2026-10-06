<?php

namespace Tests\Feature\Regression;

use App\Actions\Assembly\CompleteAssemblyOrder;
use App\Actions\Assembly\SaveAssemblyOrder;
use App\Actions\Assembly\SaveBillOfMaterial;
use App\Actions\Bills\SaveBill;
use App\Actions\Invoices\SaveInvoice;
use App\Actions\LockDates\UpdateLockDates;
use App\Actions\StockTransfers\ReceiveStockTransfer;
use App\Actions\StockTransfers\SaveStockTransfer;
use App\Actions\StockTransfers\ShipStockTransfer;
use App\Actions\StockTransfers\TransferNow;
use App\Console\Commands\PostMissingAdjustmentJournals;
use App\Livewire\Inventory\InventoryTable;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Import;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\InventoryLayer;
use App\Models\Item;
use App\Models\Journal;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\AccountCodeService;
use App\Services\ImportService;
use App\Services\StockValuationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * F1: stock adjustments, counts, the stock list's bulk reset, API
 * adjustments and imported opening stock post journals at cost, so the
 * Inventory account stays equal to the stock value (cost layers).
 * F2: the fixed-assets list's bulk dispose posts each asset's disposal
 * journal like the asset's own page. Plus the two catch-up commands.
 */
class StockAndDisposalJournalsTest extends TestCase
{
    private const PERMISSIONS = [
        'view invoices', 'create invoices', 'view customers', 'view bills', 'create bills',
        'view inventory', 'adjust inventory', 'view items', 'create items', 'edit items', 'delete items',
        'view reports', 'view journals', 'view fixed-assets', 'edit fixed-assets', 'delete fixed-assets', 'import data',
    ];

    private Customer $customer;

    private Vendor $vendor;

    private Warehouse $main;

    private Item $maize;

    private Item $soya;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->createAuthenticatedUser(self::PERMISSIONS);
        $this->subscription->update(['ends_at' => '2030-12-31']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->main = Warehouse::getDefault($this->tenant->id);
        $this->maize = $this->item('Maize', 'fifo', 'kg');
        $this->soya = $this->item('Soya', 'weighted_average', 'kg');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- helpers -------------------------------------------------------

    private function item(string $name, string $method = 'fifo', string $unit = 'pcs', float $costPrice = 0): Item
    {
        return Item::factory()->product()->create([
            'tenant_id' => $this->tenant->id, 'name' => $name, 'unit' => $unit, 'cost_price' => $costPrice, 'selling_price' => 15000,
            'valuation_method' => $method, 'track_inventory' => true,
        ]);
    }

    private function buy(Item $item, float $qty, float $cost, ?Warehouse $warehouse = null, string $date = '2026-10-01'): void
    {
        app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_date' => $date, 'due_date' => '2026-11-30',
            'warehouse_id' => ($warehouse ?? $this->main)->id,
            'items' => [['item_id' => $item->id, 'description' => $item->name, 'quantity' => $qty, 'unit_price' => $cost, 'tax_rate' => 0]],
        ], $this->user->id);
    }

    private function sell(Item $item, float $qty, ?Warehouse $warehouse = null): void
    {
        app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_date' => '2026-10-15', 'due_date' => '2026-11-15', 'status' => 'unpaid',
            'warehouse_id' => ($warehouse ?? $this->main)->id,
            'items' => [['item_id' => $item->id, 'description' => $item->name, 'quantity' => $qty, 'unit_price' => 15000, 'tax_rate' => 0]],
        ], $this->user->id);
    }

    /** Maize in two lots (FIFO): 500 kg at 300, then 500 kg at 340. */
    private function stockUpMaize(): void
    {
        $this->buy($this->maize, 500, 300, null, '2026-10-01');
        $this->buy($this->maize, 500, 340, null, '2026-10-02');
    }

    private function onHand(Item $item, ?Warehouse $warehouse = null): float
    {
        return (float) Inventory::where('item_id', $item->id)->where('warehouse_id', ($warehouse ?? $this->main)->id)->value('quantity');
    }

    private function stockValue(?int $tenantId = null): float
    {
        return round((float) InventoryLayer::withoutGlobalScopes()->where('tenant_id', $tenantId ?? $this->tenant->id)
            ->selectRaw('SUM(remaining_quantity * unit_cost) as v')->value('v'), 2);
    }

    private function code(string $key, ?int $tenantId = null): string
    {
        return AccountCodeService::resolve($tenantId ?? $this->tenant->id, $key);
    }

    private function balance(string $code, ?int $tenantId = null): float
    {
        return round((float) ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId ?? $this->tenant->id)
            ->where('account_code', $code)->value('current_balance'), 2);
    }

    private function lastAdjustment(): InventoryHistory
    {
        return InventoryHistory::latest('id')->firstOrFail();
    }

    /** @return array<string, array{0: float, 1: float}> account code => [debit, credit] */
    private function lines(?Journal $journal): array
    {
        $this->assertNotNull($journal, 'a journal was posted');

        return $journal->entries()->with('account')->get()
            ->mapWithKeys(fn ($e) => [$e->account->account_code => [round((float) $e->debit, 2), round((float) $e->credit, 2)]])->all();
    }

    private function journalFor(InventoryHistory $row): ?Journal
    {
        return Journal::where('reference_type', InventoryHistory::class)->where('reference_id', $row->id)->first();
    }

    private function adjust(array $data, ?Item $item = null): TestResponse
    {
        return $this->post(route('inventory.adjust', $item ?? $this->maize), $data);
    }

    private function otherTenant(): Tenant
    {
        auth()->forgetGuards();
        [$other] = $this->createTenantWithSubscription();
        $this->actingAs($this->user);

        return $other;
    }

    private function lockUpTo(string $date): void
    {
        app(UpdateLockDates::class)->handle($this->tenant, ['staff_lock_date' => $date, 'all_users_lock_date' => null, 'reason' => null]);
    }

    // ---- F1: adjustments --------------------------------------------------

    public function test_f1_counting_down_posts_stock_losses_against_inventory_at_fifo_cost(): void
    {
        $this->stockUpMaize();

        $this->adjust(['type' => 'adjustment', 'quantity' => 980, 'notes' => 'Monthly count'])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(980.0, $this->onHand($this->maize));
        $row = $this->lastAdjustment();
        $journal = $this->journalFor($row);
        // 20 kg from the oldest lot at 300.
        $this->assertEquals([$this->code('stock_losses') => [6000.0, 0.0], $this->code('inventory') => [0.0, 6000.0]], $this->lines($journal));
        $this->assertStringContainsString('Stock count, '.$this->main->name.': Maize −20 kg', $journal->description);
        $this->assertStringContainsString('Monthly count', $journal->description);
        $this->assertSame('2026-10-20', $journal->journal_date->toDateString());
        $this->assertSame(314000.0, $this->stockValue());
        $this->assertSame($this->stockValue(), $this->balance($this->code('inventory')));
    }

    public function test_f1_stock_out_takes_the_oldest_layers_first_and_posts_their_cost(): void
    {
        $this->stockUpMaize();

        $this->adjust(['type' => 'out', 'quantity' => 520, 'notes' => 'Rats'])->assertSessionHasNoErrors();

        // 500 at 300 and 20 at 340.
        $this->assertEquals([$this->code('stock_losses') => [156800.0, 0.0], $this->code('inventory') => [0.0, 156800.0]],
            $this->lines($this->journalFor($this->lastAdjustment())));
        $this->assertSame($this->stockValue(), $this->balance($this->code('inventory')));
        $this->assertSame(156800.0, $this->balance($this->code('stock_losses')));
    }

    public function test_f1_stock_in_adds_a_layer_at_the_given_cost_and_posts_the_reverse(): void
    {
        $this->stockUpMaize();

        $this->adjust(['type' => 'in', 'quantity' => 5, 'unit_cost' => 320, 'notes' => 'Found in the back room'])->assertSessionHasNoErrors();

        $row = $this->lastAdjustment();
        $layer = InventoryLayer::where('reference_type', 'adjustment')->where('reference_id', $row->id)->sole();
        $this->assertSame([5.0, 320.0], [(float) $layer->remaining_quantity, (float) $layer->unit_cost]);
        $this->assertEquals([$this->code('inventory') => [1600.0, 0.0], $this->code('stock_losses') => [0.0, 1600.0]], $this->lines($this->journalFor($row)));
        $this->assertSame($this->stockValue(), $this->balance($this->code('inventory')));
    }

    public function test_f1_stock_in_without_a_cost_uses_the_warehouse_average_then_the_cost_price(): void
    {
        $this->buy($this->soya, 100, 600, null, '2026-10-01');
        $this->buy($this->soya, 100, 700, null, '2026-10-02');

        $this->adjust(['type' => 'in', 'quantity' => 10], $this->soya)->assertSessionHasNoErrors();
        $this->assertSame(6500.0, $this->lines($this->journalFor($this->lastAdjustment()))[$this->code('inventory')][0]);

        // Nothing in stock: the item's cost price.
        $beans = $this->item('Beans', 'fifo', 'kg', 450);
        $this->adjust(['type' => 'in', 'quantity' => 4], $beans)->assertSessionHasNoErrors();
        $this->assertSame(1800.0, $this->lines($this->journalFor($this->lastAdjustment()))[$this->code('inventory')][0]);
        $this->assertSame($this->stockValue(), $this->balance($this->code('inventory')));
    }

    public function test_f1_counting_up_adds_a_layer_and_found_stock_can_post_to_another_account(): void
    {
        $this->stockUpMaize();
        $cash = ChartOfAccount::where('account_code', $this->code('cash'))->sole();

        $this->adjust(['type' => 'adjustment', 'quantity' => 1010, 'unit_cost' => 350, 'account_id' => $cash->id])->assertSessionHasNoErrors();

        $row = $this->lastAdjustment();
        $this->assertSame(10.0, (float) $row->quantity);
        $this->assertEquals([$this->code('inventory') => [3500.0, 0.0], $this->code('cash') => [0.0, 3500.0]], $this->lines($this->journalFor($row)));
        $this->assertSame(1010.0, $this->onHand($this->maize));

        // A count that matches posts nothing.
        $this->adjust(['type' => 'adjustment', 'quantity' => 1010])->assertSessionHasNoErrors();
        $this->assertNull($this->journalFor($this->lastAdjustment()));
        $this->assertSame($this->stockValue(), $this->balance($this->code('inventory')));
    }

    public function test_f1_the_other_account_must_be_the_businesss_own_and_not_inventory(): void
    {
        $this->stockUpMaize();
        $inventory = ChartOfAccount::where('account_code', $this->code('inventory'))->sole();
        $other = $this->otherTenant();
        $theirs = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $other->id)->where('account_code', '1000')->sole();

        $this->adjust(['type' => 'in', 'quantity' => 1, 'account_id' => $inventory->id])->assertSessionHasErrors('account_id');
        $this->adjust(['type' => 'in', 'quantity' => 1, 'account_id' => $theirs->id])->assertSessionHasErrors('account_id');
        $this->assertSame(1000.0, $this->onHand($this->maize));
    }

    public function test_f1_the_stock_lists_bulk_reset_takes_stock_out_at_cost(): void
    {
        $this->stockUpMaize();
        $this->buy($this->soya, 100, 600);

        Livewire::test(InventoryTable::class)
            ->set('selectedItems', [(string) $this->maize->id, (string) $this->soya->id])
            ->set('bulkAction', 'reset_quantity')
            ->call('applyBulkAction')
            ->assertSet('errorMessage', '');

        $this->assertSame(0.0, $this->onHand($this->maize));
        $this->assertSame(0.0, $this->stockValue());
        $this->assertSame(0.0, $this->balance($this->code('inventory')));
        $this->assertSame(380000.0, $this->balance($this->code('stock_losses')));
        $this->assertSame(2, Journal::where('reference_type', InventoryHistory::class)->count());
    }

    public function test_f1_bulk_reset_leaves_stock_reserved_for_invoices(): void
    {
        $this->stockUpMaize();
        Inventory::where('item_id', $this->maize->id)->update(['reserved_quantity' => 30]);

        Livewire::test(InventoryTable::class)
            ->set('selectedItems', [(string) $this->maize->id])
            ->set('bulkAction', 'reset_quantity')
            ->call('applyBulkAction')
            ->assertSee('could not be reset');

        $this->assertSame(1000.0, $this->onHand($this->maize));
        $this->assertSame($this->stockValue(), $this->balance($this->code('inventory')));
    }

    public function test_f1_api_adjustments_post_journals_at_cost(): void
    {
        $this->stockUpMaize();
        $inventory = Inventory::where('item_id', $this->maize->id)->sole();
        $api = $this->actingAs($this->user, 'sanctum');

        $api->postJson("/api/v1/inventory/{$inventory->id}/adjust", ['type' => 'subtract', 'quantity' => 10, 'reason' => 'Damaged'])->assertOk();
        $this->assertEquals([$this->code('stock_losses') => [3000.0, 0.0], $this->code('inventory') => [0.0, 3000.0]], $this->lines($this->journalFor($this->lastAdjustment())));

        $api->postJson("/api/v1/inventory/{$inventory->id}/adjust", ['type' => 'add', 'quantity' => 2, 'unit_cost' => 310, 'reason' => 'Found'])->assertOk();
        $this->assertEquals([$this->code('inventory') => [620.0, 0.0], $this->code('stock_losses') => [0.0, 620.0]], $this->lines($this->journalFor($this->lastAdjustment())));

        $api->postJson("/api/v1/inventory/{$inventory->id}/adjust", ['type' => 'set', 'quantity' => 900, 'reason' => 'Count'])->assertOk();
        $this->assertSame(900.0, $this->onHand($this->maize));
        $this->assertSame($this->stockValue(), $this->balance($this->code('inventory')));
        $this->assertSame(3, Journal::where('reference_type', InventoryHistory::class)->count());
    }

    public function test_f1_imported_opening_stock_gets_a_layer_and_posts_to_opening_balance_equity(): void
    {
        Storage::fake('imports');
        $csv = "name,type,purchase_price,track_inventory,initial_stock,opening_stock_date\n"
            ."Groundnut,product,250,yes,100,2026-09-30\n"
            ."Sesame,product,400,yes,,\n";
        $path = $this->tenant->id.'/items.csv';
        Storage::disk('imports')->put($path, $csv);
        $import = Import::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => Import::TYPE_ITEMS,
            'format' => Import::FORMAT_CSV, 'status' => Import::STATUS_PROCESSING,
            'original_filename' => 'items.csv', 'file_path' => $path, 'file_size' => strlen($csv),
        ]);

        // As a queue worker would: nobody signed in.
        auth()->forgetGuards();
        $this->assertTrue(app(ImportService::class)->processImport($import));
        $this->actingAs($this->user);

        $item = Item::where('name', 'Groundnut')->sole();
        $layer = InventoryLayer::where('item_id', $item->id)->sole();
        $this->assertSame([100.0, 250.0, '2026-09-30'], [(float) $layer->remaining_quantity, (float) $layer->unit_cost, $layer->received_date->toDateString()]);
        $row = InventoryHistory::where('item_id', $item->id)->sole();
        $journal = $this->journalFor($row);
        $this->assertEquals([$this->code('inventory') => [25000.0, 0.0], $this->code('opening_balance_equity') => [0.0, 25000.0]], $this->lines($journal));
        $this->assertSame('2026-09-30', $journal->journal_date->toDateString());
        $this->assertSame('Opening Balance Equity', ChartOfAccount::where('account_code', $this->code('opening_balance_equity'))->value('name'));
        $this->assertSame(0.0, $this->onHand(Item::where('name', 'Sesame')->sole()));
        $this->assertSame($this->stockValue(), $this->balance($this->code('inventory')));
    }

    public function test_f1_inventory_ledger_equals_stock_value_after_bills_sales_transfers_assembly_and_adjustments(): void
    {
        $kano = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Kano shop', 'code' => 'KANO']);
        $feed = $this->item('Feed 25kg bag', 'fifo', 'bag');
        $this->stockUpMaize();
        $this->buy($this->soya, 300, 600);
        $this->buy($this->soya, 100, 640, null, '2026-10-03');

        $this->sell($this->maize, 120);
        $this->sell($this->soya, 50);

        app(TransferNow::class)->handle(app(SaveStockTransfer::class)->create($this->tenant->id, [
            'transfer_date' => '2026-10-10', 'from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $kano->id,
            'items' => [['item_id' => $this->maize->id, 'quantity' => 200]],
        ], $this->user->id));
        $shipped = app(ShipStockTransfer::class)->handle(app(SaveStockTransfer::class)->create($this->tenant->id, [
            'transfer_date' => '2026-10-11', 'from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $kano->id,
            'items' => [['item_id' => $this->soya->id, 'quantity' => 40]],
        ], $this->user->id));
        app(ReceiveStockTransfer::class)->handle($shipped, [$shipped->items->first()->id => 37], ReceiveStockTransfer::LOST, '2026-10-12');

        $bom = app(SaveBillOfMaterial::class)->create($this->tenant->id, [
            'item_id' => $feed->id, 'name' => 'Feed', 'output_quantity' => 10,
            'components' => [['item_id' => $this->maize->id, 'quantity' => 150, 'waste_percentage' => 0], ['item_id' => $this->soya->id, 'quantity' => 60, 'waste_percentage' => 0]],
            'costs' => [['description' => 'Labour', 'amount' => 5000, 'account_id' => null]],
        ]);
        app(CompleteAssemblyOrder::class)->handle(app(SaveAssemblyOrder::class)->create($this->tenant->id, [
            'kind' => 'build', 'bill_of_materials_id' => $bom->id, 'assembly_date' => '2026-10-13', 'quantity' => 10,
            'warehouse_id' => $this->main->id, 'to_warehouse_id' => $this->main->id,
        ], $this->user->id));

        $this->adjust(['type' => 'out', 'quantity' => 7, 'warehouse_id' => $kano->id])->assertSessionHasNoErrors();
        $this->adjust(['type' => 'adjustment', 'quantity' => 197.5, 'warehouse_id' => $this->main->id], $this->soya)->assertSessionHasNoErrors();
        $this->adjust(['type' => 'in', 'quantity' => 3, 'unit_cost' => 41000], $feed)->assertSessionHasNoErrors();
        $this->sell($feed, 4);
        Livewire::test(InventoryTable::class)->set('warehouseFilter', (string) $kano->id)
            ->set('selectedItems', [(string) $this->soya->id])->set('bulkAction', 'reset_quantity')->call('applyBulkAction');

        $this->assertGreaterThan(0, $this->stockValue());
        $this->assertSame($this->stockValue(), $this->balance($this->code('inventory')));
        $this->assertSame([$this->stockValue(), $this->stockValue()], PostMissingAdjustmentJournals::gap($this->tenant->id));
    }

    public function test_f1_lock_dates_block_adjustments_dated_behind_them(): void
    {
        $this->stockUpMaize();
        $this->lockUpTo('2026-10-15');

        $this->adjust(['type' => 'out', 'quantity' => 5, 'date' => '2026-10-10'])->assertSessionHasErrors('date');
        $this->assertSame(1000.0, $this->onHand($this->maize));
        $this->assertSame(0, InventoryHistory::whereNull('reference_type')->orWhere('reference_type', 'adjustment')->count());

        $this->adjust(['type' => 'out', 'quantity' => 5, 'date' => '2026-10-16'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-16', $this->journalFor($this->lastAdjustment())->journal_date->toDateString());
        $this->adjust(['type' => 'out', 'quantity' => 5, 'date' => '2026-10-25'])->assertSessionHasErrors('date');
    }

    // ---- F1: the catch-up command -----------------------------------------------

    /** Stock moves the way the old code left them: history rows and layers, no journals. */
    private function oldStyleAdjustments(int $tenantId, Item $wa, Item $groundnut): void
    {
        $warehouseId = Warehouse::defaultIdFor($tenantId);
        $valuation = app(StockValuationService::class);
        // Old web stock-out (layers taken, nothing recorded).
        $valuation->consumeStock($wa, 20, $warehouseId);
        Inventory::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('item_id', $wa->id)->decrement('quantity', 20);
        $this->history($tenantId, $wa->id, 'out', 20, null, 'Spoilt', '2026-10-05 09:00:00');
        // Old web stock-in with its layer (no reference id).
        $valuation->addLayer($tenantId, $wa->id, 5, 600, $warehouseId, 'adjustment')->forceFill(['received_date' => '2026-10-06'])->save();
        Inventory::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('item_id', $wa->id)->increment('quantity', 5);
        $this->history($tenantId, $wa->id, 'in', 5, null, 'Found', '2026-10-06 09:00:00');
        // Old item import: stock with no layer.
        $row = new Inventory(['tenant_id' => $tenantId, 'item_id' => $groundnut->id, 'warehouse_id' => $warehouseId, 'quantity' => 100, 'reserved_quantity' => 0, 'unit_cost' => 250]);
        $row->skipTenantGuard = true;
        $row->save();
        $this->history($tenantId, $groundnut->id, 'in', 100, null, 'Initial stock from import', '2026-10-01 09:00:00');
    }

    private function history(int $tenantId, int $itemId, string $type, float $qty, ?string $ref, string $notes, string $at): void
    {
        DB::table('inventory_histories')->insert([
            'tenant_id' => $tenantId, 'item_id' => $itemId, 'warehouse_id' => Warehouse::defaultIdFor($tenantId), 'type' => $type, 'quantity' => $qty,
            'reference_type' => $ref, 'notes' => $notes, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    public function test_f1_command_posts_missing_adjustment_journals_once_and_skips_locked_dates(): void
    {
        $this->buy($this->soya, 200, 600);
        $groundnut = $this->item('Groundnut', 'fifo', 'kg', 250);
        $this->oldStyleAdjustments($this->tenant->id, $this->soya, $groundnut);
        // An old API stock-out on a day that is now locked.
        $this->buy($this->maize, 10, 300);
        $this->history($this->tenant->id, $this->maize->id, 'adjustment', -1, 'api_adjustment', 'Old count', '2026-09-20 09:00:00');
        Inventory::where('item_id', $this->maize->id)->decrement('quantity', 1);
        app(StockValuationService::class)->consumeStock($this->maize, 1);
        $this->lockUpTo('2026-09-25');
        $before = Journal::count();
        auth()->forgetGuards();

        $this->artisan('stock:post-missing-adjustment-journals', ['--dry-run' => true])
            ->expectsOutputToContain('Stock adjustment')->doesntExpectOutputToContain('posted late')
            ->expectsOutputToContain('Would post 3 journal(s); 1 skipped')
            ->assertSuccessful();
        $this->assertSame($before, Journal::count());

        $this->artisan('stock:post-missing-adjustment-journals')->expectsOutputToContain('Posted 3 journal(s); 1 skipped')->assertSuccessful();
        $this->artisan('stock:post-missing-adjustment-journals')->expectsOutputToContain('Posted 0 journal(s); 1 skipped')->assertSuccessful();
        $this->actingAs($this->user);

        $journals = Journal::where('reference_type', InventoryHistory::class)->orderBy('journal_date')->get();
        $this->assertCount(3, $journals);
        $this->assertSame(['2026-10-01', '2026-10-05', '2026-10-06'], $journals->map(fn ($j) => $j->journal_date->toDateString())->all());
        $this->assertEquals([$this->code('inventory') => [25000.0, 0.0], $this->code('opening_balance_equity') => [0.0, 25000.0]], $this->lines($journals[0]));
        $this->assertEquals([$this->code('stock_losses') => [12000.0, 0.0], $this->code('inventory') => [0.0, 12000.0]], $this->lines($journals[1]));
        $this->assertEquals([$this->code('inventory') => [3000.0, 0.0], $this->code('stock_losses') => [0.0, 3000.0]], $this->lines($journals[2]));
        // The imported stock got its missing layer.
        $this->assertSame(100.0, (float) InventoryLayer::where('item_id', $groundnut->id)->sum('remaining_quantity'));
        // Only the locked API row (1 kg of maize at 300) is left unexplained.
        $this->assertSame(round($this->stockValue() + 300, 2), $this->balance($this->code('inventory')));
    }

    public function test_f1_command_keeps_each_business_to_its_own_rows(): void
    {
        $this->buy($this->soya, 200, 600);
        $groundnut = $this->item('Groundnut', 'fifo', 'kg', 250);
        $this->oldStyleAdjustments($this->tenant->id, $this->soya, $groundnut);

        $other = $this->otherTenant();
        [$theirSoya, $theirGroundnut] = Item::withoutTenantGuard(fn () => [
            Item::factory()->product()->create(['tenant_id' => $other->id, 'name' => 'Their soya', 'valuation_method' => 'weighted_average', 'track_inventory' => true, 'cost_price' => 500]),
            Item::factory()->product()->create(['tenant_id' => $other->id, 'name' => 'Their groundnut', 'track_inventory' => true, 'cost_price' => 200]),
        ]);
        auth()->forgetGuards();
        app(StockValuationService::class)->addLayer($other->id, $theirSoya->id, 50, 500, Warehouse::defaultIdFor($other->id), 'bill');
        $row = new Inventory(['tenant_id' => $other->id, 'item_id' => $theirSoya->id, 'warehouse_id' => Warehouse::defaultIdFor($other->id), 'quantity' => 50, 'reserved_quantity' => 0, 'unit_cost' => 500]);
        $row->skipTenantGuard = true;
        $row->save();
        $this->oldStyleAdjustments($other->id, $theirSoya, $theirGroundnut);

        $this->artisan('stock:post-missing-adjustment-journals', ['--tenant' => $other->id])->expectsOutputToContain('Posted 3 journal(s)')->assertSuccessful();
        $this->assertSame(0, Journal::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('reference_type', InventoryHistory::class)->count());
        $theirs = Journal::withoutGlobalScopes()->where('reference_type', InventoryHistory::class)->get();
        $this->assertCount(3, $theirs);
        $this->assertSame([$other->id], $theirs->pluck('tenant_id')->unique()->values()->all());
        $theirRows = InventoryHistory::withoutGlobalScopes()->where('tenant_id', $other->id)->pluck('id')->all();
        $this->assertEqualsCanonicalizing($theirRows, $theirs->pluck('reference_id')->all());
        $this->assertSame(100.0, (float) InventoryLayer::withoutGlobalScopes()->where('item_id', $theirGroundnut->id)->sum('remaining_quantity'));
        $this->assertSame(0.0, (float) InventoryLayer::withoutGlobalScopes()->where('item_id', $groundnut->id)->sum('remaining_quantity'));

        $this->artisan('stock:post-missing-adjustment-journals')->expectsOutputToContain('Posted 3 journal(s)')->assertSuccessful();
        $this->assertSame(3, Journal::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('reference_type', InventoryHistory::class)->count());
    }

    public function test_f1_migration_adds_opening_balance_equity_and_reruns(): void
    {
        $migration = require database_path('migrations/2026_10_15_150001_add_opening_balance_equity_account.php');
        ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '3900')->forceDelete();
        $other = $this->otherTenant();
        ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $other->id)->where('account_code', '3900')->update(['name' => 'Building fund']);

        $migration->up();
        $migration->up();

        $this->assertSame(1, ChartOfAccount::where('tenant_id', $this->tenant->id)->where('name', 'Opening Balance Equity')->count());
        $this->assertSame('3900', $this->code('opening_balance_equity'));
        $this->assertSame('Building fund', ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $other->id)->where('account_code', '3900')->value('name'));
        $this->assertSame('3910', AccountCodeService::resolve($other->id, 'opening_balance_equity'));
    }
}
