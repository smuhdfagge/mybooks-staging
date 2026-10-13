<?php

namespace Tests\Feature\Features;

use App\Actions\Bills\SaveBill;
use App\Actions\Invoices\SaveInvoice;
use App\Actions\LockDates\UpdateLockDates;
use App\Actions\StockTransfers\CancelStockTransfer;
use App\Actions\StockTransfers\DeleteStockTransfer;
use App\Actions\StockTransfers\ReceiveStockTransfer;
use App\Actions\StockTransfers\SaveStockTransfer;
use App\Actions\StockTransfers\ShipStockTransfer;
use App\Actions\StockTransfers\TransferNow;
use App\Livewire\StockTransfers\StockTransfersTable;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\InventoryLayer;
use App\Models\InventoryLayerConsumption;
use App\Models\Item;
use App\Models\Journal;
use App\Models\StockTransfer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\AccountCodeService;
use App\Services\Accounting\FinancialStatements;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Session 13: stock transfers between warehouses. Shipping takes the goods
 * and their cost out of the source warehouse, receiving puts the same cost
 * into the destination; in between they are "in transit". Only goods lost
 * on the way post a journal.
 */
class StockTransfersTest extends TestCase
{
    private const PERMISSIONS = [
        'view invoices', 'create invoices', 'edit invoices', 'view customers', 'view bills', 'create bills',
        'view inventory', 'adjust inventory', 'view items', 'create items', 'edit items', 'view reports', 'view journals',
    ];

    private Customer $customer;

    private Vendor $vendor;

    private Warehouse $main;

    private Warehouse $kano;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->createAuthenticatedUser(self::PERMISSIONS);
        $this->subscription->update(['ends_at' => '2030-12-31']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->main = Warehouse::getDefault($this->tenant->id);
        $this->kano = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Kano shop', 'code' => 'KANO']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- helpers -------------------------------------------------------

    private function item(string $method = 'fifo', string $name = 'Cement bag', float $costPrice = 0): Item
    {
        return Item::factory()->product()->create([
            'tenant_id' => $this->tenant->id, 'name' => $name, 'cost_price' => $costPrice, 'selling_price' => 40000, 'valuation_method' => $method,
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

    /** @param array<int, array{0: Item, 1: float}> $lines */
    private function draft(array $lines, ?Warehouse $from = null, ?Warehouse $to = null, string $date = '2026-10-10'): StockTransfer
    {
        return app(SaveStockTransfer::class)->create($this->tenant->id, [
            'transfer_date' => $date,
            'from_warehouse_id' => ($from ?? $this->main)->id,
            'to_warehouse_id' => ($to ?? $this->kano)->id,
            'items' => array_map(fn ($l) => ['item_id' => $l[0]->id, 'quantity' => $l[1]], $lines),
        ], $this->user->id);
    }

    private function onHand(Item $item, Warehouse $warehouse): float
    {
        return (float) Inventory::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->value('quantity');
    }

    /** @return array<int, array{0: float, 1: float}> [remaining, unit cost] of the item's layers in a warehouse */
    private function layers(Item $item, Warehouse $warehouse): array
    {
        return InventoryLayer::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->where('remaining_quantity', '>', 0)
            ->orderBy('received_date')->orderBy('id')->get()
            ->map(fn ($l) => [(float) $l->remaining_quantity, (float) $l->unit_cost])->all();
    }

    /** Value of all cost layers plus goods in transit. */
    private function stockValue(): float
    {
        $layers = (float) InventoryLayer::where('tenant_id', $this->tenant->id)->selectRaw('SUM(remaining_quantity * unit_cost) as v')->value('v');
        $transit = array_sum(array_column(StockTransfer::inTransitByItem($this->tenant->id), 'cost'));

        return round($layers + $transit, 2);
    }

    /** Value of the stock records (quantity at each warehouse's average cost). */
    private function recordsValue(): float
    {
        return round((float) Inventory::where('tenant_id', $this->tenant->id)->selectRaw('SUM(quantity * unit_cost) as v')->value('v'), 2);
    }

    private function balance(string $code): float
    {
        return round((float) ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', $code)->value('current_balance'), 2);
    }

    private function otherTenant(): Tenant
    {
        auth()->forgetGuards();
        [$other] = $this->createTenantWithSubscription();
        $this->actingAs($this->user);

        return $other;
    }

    // ---- moving stock and cost ----------------------------------------

    public function test_transfer_now_moves_fifo_layers_at_their_own_cost(): void
    {
        $item = $this->item('fifo');
        $this->buy($item, 10, 30000, $this->main, '2026-10-01');
        $this->buy($item, 5, 32000, $this->main, '2026-10-02');
        $value = $this->stockValue();
        $records = $this->recordsValue();
        $ledger = $this->balance('1300');
        $journals = Journal::count();

        $transfer = app(TransferNow::class)->handle($this->draft([[$item, 12]]));

        $this->assertSame(StockTransfer::STATUS_RECEIVED, $transfer->status);
        $this->assertSame(3.0, $this->onHand($item, $this->main));
        $this->assertSame(12.0, $this->onHand($item, $this->kano));
        $this->assertSame([[10.0, 30000.0], [2.0, 32000.0]], $this->layers($item, $this->kano), 'oldest layer first, same costs');
        $this->assertSame([[3.0, 32000.0]], $this->layers($item, $this->main));
        $this->assertSame(364000.0, (float) $transfer->items->first()->shipped_cost);
        $this->assertSame(364000.0, (float) $transfer->items->first()->received_cost);

        $this->assertSame($value, $this->stockValue(), 'total stock value unchanged');
        // Stock records keep an average cost in kobo, so allow for rounding.
        $this->assertEqualsWithDelta($records, $this->recordsValue(), 0.5);
        $this->assertSame($ledger, $this->balance('1300'), 'Inventory account unchanged');
        $this->assertSame($journals, Journal::count(), 'a move between warehouses posts nothing');
        $this->assertEqualsWithDelta(32000, (float) Inventory::where('item_id', $item->id)->where('warehouse_id', $this->main->id)->value('unit_cost'), 0.05);
    }

    public function test_transfer_now_moves_weighted_average_cost(): void
    {
        $item = $this->item('weighted_average');
        $this->buy($item, 10, 1000);
        $this->buy($item, 10, 1600);
        $value = $this->stockValue();
        $ledger = $this->balance('1300');

        $transfer = app(TransferNow::class)->handle($this->draft([[$item, 5]]));

        $this->assertSame(6500.0, (float) $transfer->items->first()->shipped_cost);
        $this->assertSame([[5.0, 1300.0]], $this->layers($item, $this->kano));
        $this->assertEquals(1300, (float) Inventory::where('item_id', $item->id)->where('warehouse_id', $this->kano->id)->value('unit_cost'));
        $this->assertEquals(1300, (float) Inventory::where('item_id', $item->id)->where('warehouse_id', $this->main->id)->value('unit_cost'));
        $this->assertSame(15.0, $this->onHand($item, $this->main));
        $this->assertSame($value, $this->stockValue());
        $this->assertSame($ledger, $this->balance('1300'));
    }

    public function test_stock_history_says_where_the_goods_went_and_links_to_the_transfer(): void
    {
        $item = $this->item();
        $this->buy($item, 4, 1000);
        $transfer = app(TransferNow::class)->handle($this->draft([[$item, 3]]));

        $out = InventoryHistory::where('item_id', $item->id)->where('warehouse_id', $this->main->id)->where('type', 'transfer')->first();
        $in = InventoryHistory::where('item_id', $item->id)->where('warehouse_id', $this->kano->id)->where('type', 'transfer')->first();
        $this->assertSame("Transfer {$transfer->transfer_number} to Kano shop", $out->notes);
        $this->assertSame(-3.0, (float) $out->quantity);
        $this->assertSame("Transfer {$transfer->transfer_number} from {$this->main->name}", $in->notes);

        $this->get(route('inventory.history', $item))->assertOk()
            ->assertSee(route('stock-transfers.show', $transfer), false)
            ->assertSee("Transfer {$transfer->transfer_number} to Kano shop");
    }

    public function test_a_later_sale_from_the_destination_costs_the_transferred_goods(): void
    {
        $item = $this->item('fifo');
        $this->buy($item, 5, 1000, $this->main);
        $this->buy($item, 5, 1800, $this->kano, '2026-10-05');
        app(TransferNow::class)->handle($this->draft([[$item, 5]]));
        $cogs = $this->balance('5000');

        // FIFO in Kano: the transferred goods (bought 1 Oct) go before Kano's own (5 Oct).
        $invoice = app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_date' => '2026-10-12', 'due_date' => '2026-11-12', 'status' => 'unpaid',
            'warehouse_id' => $this->kano->id,
            'items' => [['item_id' => $item->id, 'description' => 'Cement', 'quantity' => 2, 'unit_price' => 40000, 'tax_rate' => 0]],
        ], $this->user->id);

        $this->assertEquals(1000, (float) $invoice->items->first()->unit_cost);
        $this->assertSame(round($cogs + 2000, 2), $this->balance('5000'));
    }

    // ---- free stock -----------------------------------------------------

    public function test_shipping_refuses_more_than_is_free_and_reserved_stock_is_not_free(): void
    {
        $item = $this->item('fifo', 'Rice bag');
        $this->buy($item, 10, 1000);
        app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_date' => '2026-10-05', 'due_date' => '2026-11-05', 'status' => 'unpaid',
            'warehouse_id' => $this->main->id,
            'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 4, 'unit_price' => 1500, 'tax_rate' => 0]],
        ], $this->user->id);

        $this->post(route('stock-transfers.store'), [
            'transfer_date' => '2026-10-10', 'from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $this->kano->id,
            'action' => 'transfer_now', 'items' => [['item_id' => $item->id, 'quantity' => 7]],
        ])->assertSessionHasErrors(['items' => "Only 6 Rice bag free in {$this->main->name} (10 on hand, 4 reserved for invoices), so you can't send 7."]);

        $this->assertSame(0, StockTransfer::count(), 'nothing is kept when shipping fails');
        $this->assertSame(10.0, $this->onHand($item, $this->main));
        $this->assertSame(0.0, $this->onHand($item, $this->kano));

        $this->post(route('stock-transfers.store'), [
            'transfer_date' => '2026-10-10', 'from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $this->kano->id,
            'action' => 'transfer_now', 'items' => [['item_id' => $item->id, 'quantity' => 6]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(4.0, $this->onHand($item, $this->main));
    }

    public function test_the_same_item_on_two_lines_is_checked_together(): void
    {
        $item = $this->item();
        $this->buy($item, 5, 1000);
        $transfer = $this->draft([[$item, 3], [$item, 3]]);

        $this->assertCount(1, $transfer->items, 'merged onto one line');
        $this->expectException(ValidationException::class);
        app(ShipStockTransfer::class)->handle($transfer);
    }

    // ---- in transit -----------------------------------------------------

    public function test_goods_in_transit_show_on_the_item_warehouse_pages_and_valuation_report(): void
    {
        $item = $this->item('fifo', 'Cement bag', 30000);
        $this->buy($item, 10, 30000);
        $this->get(route('reports.inventory-summary'))->assertOk()->assertSee('300,000.00');

        $transfer = app(ShipStockTransfer::class)->handle($this->draft([[$item, 4]]));

        $this->assertSame(StockTransfer::STATUS_IN_TRANSIT, $transfer->status);
        $this->assertSame(6.0, $this->onHand($item, $this->main));
        $this->assertSame(0.0, $this->onHand($item, $this->kano), 'not in either warehouse');
        $this->assertSame(4.0, StockTransfer::inTransitQuantity($item));
        $this->assertSame(300000.0, $this->stockValue(), 'still our stock');

        $this->get(route('items.show', $item))->assertOk()->assertSee('In transit')->assertSee('Total stock including goods in transit');
        $this->get(route('warehouses.show', $this->kano))->assertOk()->assertSee($transfer->transfer_number)->assertSee('coming from');
        $this->get(route('warehouses.show', $this->main))->assertOk()->assertSee('going to Kano shop');
        $this->get(route('warehouses.index'))->assertOk()->assertSee('in transit');
        $this->get(route('reports.inventory-summary'))->assertOk()
            ->assertSee('Goods in transit')->assertSee('120,000.00')
            ->assertSee('300,000.00', false); // the total doesn't dip

        app(ReceiveStockTransfer::class)->handle($transfer);
        $this->assertSame(0.0, StockTransfer::inTransitQuantity($item));
        $this->get(route('reports.inventory-summary'))->assertOk()->assertDontSee('Goods in transit');
    }

    public function test_cancelling_puts_the_goods_back_at_exactly_the_cost_they_left_at(): void
    {
        $item = $this->item('fifo');
        $this->buy($item, 10, 30000, $this->main, '2026-10-01');
        $this->buy($item, 5, 32000, $this->main, '2026-10-02');
        $before = $this->layers($item, $this->main);
        $records = $this->recordsValue();

        $transfer = app(ShipStockTransfer::class)->handle($this->draft([[$item, 12]]));
        $this->assertSame([[3.0, 32000.0]], $this->layers($item, $this->main));

        app(CancelStockTransfer::class)->handle($transfer);

        $this->assertSame(StockTransfer::STATUS_CANCELLED, $transfer->fresh()->status);
        $this->assertSame($before, $this->layers($item, $this->main));
        $this->assertSame(15.0, $this->onHand($item, $this->main));
        $this->assertSame([], $this->layers($item, $this->kano));
        $this->assertEqualsWithDelta($records, $this->recordsValue(), 0.5);
        $this->assertSame(0, InventoryLayerConsumption::where('source_id', $transfer->items->first()->id)->count());
        $this->assertSame(0, Journal::where('reference_type', StockTransfer::class)->count());
    }

    public function test_a_shortfall_goes_back_to_the_source_by_default(): void
    {
        $item = $this->item('fifo');
        $this->buy($item, 10, 30000, $this->main, '2026-10-01');
        $this->buy($item, 5, 32000, $this->main, '2026-10-02');
        $value = $this->stockValue();
        $transfer = app(ShipStockTransfer::class)->handle($this->draft([[$item, 12]]));
        $line = $transfer->items->first();

        $this->post(route('stock-transfers.receive', $transfer), [
            'received_date' => '2026-10-12',
            'items' => [['id' => $line->id, 'quantity_received' => 9]],
        ])->assertSessionHasNoErrors();

        $line->refresh();
        $this->assertSame(StockTransfer::STATUS_RECEIVED, $transfer->fresh()->status);
        $this->assertSame('2026-10-12', $transfer->fresh()->received_date->toDateString());
        $this->assertSame([9.0, 3.0, 0.0], [(float) $line->quantity_received, (float) $line->quantity_returned, (float) $line->quantity_lost]);
        $this->assertSame(9.0, $this->onHand($item, $this->kano));
        $this->assertSame(6.0, $this->onHand($item, $this->main));
        $this->assertSame([[9.0, 30000.0]], $this->layers($item, $this->kano));
        $this->assertSame([[1.0, 30000.0], [5.0, 32000.0]], $this->layers($item, $this->main), 'back in the layers they left');
        $this->assertSame($value, $this->stockValue());
        $this->assertSame(0, Journal::where('reference_type', StockTransfer::class)->count());
    }

    public function test_a_shortfall_recorded_as_lost_posts_stock_losses_at_cost(): void
    {
        $item = $this->item('fifo');
        $this->buy($item, 10, 30000, $this->main, '2026-10-01');
        $this->buy($item, 5, 32000, $this->main, '2026-10-02');
        $ledger = $this->balance('1300');
        $value = $this->stockValue();
        $transfer = app(ShipStockTransfer::class)->handle($this->draft([[$item, 12]]));
        $line = $transfer->items->first();

        app(ReceiveStockTransfer::class)->handle($transfer, [$line->id => 11], ReceiveStockTransfer::LOST, '2026-10-12');

        $line->refresh();
        $this->assertSame([11.0, 0.0, 1.0], [(float) $line->quantity_received, (float) $line->quantity_returned, (float) $line->quantity_lost]);
        $this->assertSame(32000.0, (float) $line->lost_cost, 'the last piece sent was from the 32,000 layer');
        $this->assertSame(11.0, $this->onHand($item, $this->kano));
        $this->assertSame(3.0, $this->onHand($item, $this->main));

        $journal = Journal::with('entries.account')->where('reference_type', StockTransfer::class)->where('reference_id', $transfer->id)->sole();
        $this->assertSame('2026-10-12', Carbon::parse($journal->journal_date)->toDateString());
        $this->assertEqualsWithDelta((float) $journal->entries->sum('debit'), (float) $journal->entries->sum('credit'), 0.001);
        $this->assertSame(32000.0, round((float) $journal->entries->firstWhere('account.account_code', '5400')->debit, 2));
        $this->assertSame(32000.0, round((float) $journal->entries->firstWhere('account.account_code', '1300')->credit, 2));
        $this->assertSame(round($ledger - 32000, 2), $this->balance('1300'));
        $this->assertSame(32000.0, $this->balance('5400'));
        $this->assertSame(round($value - 32000, 2), $this->stockValue(), 'ledger and stock value fall together');

        $tb = app(FinancialStatements::class)->trialBalance($this->tenant->id, '2026-12-31');
        $this->assertEqualsWithDelta($tb->sum('total_debit'), $tb->sum('total_credit'), 0.001);

        $this->get(route('stock-transfers.show', $transfer))->assertOk()->assertSee('Loss posted')->assertSee($journal->journal_number);
    }

    public function test_receiving_more_than_was_sent_or_before_it_was_sent_is_refused(): void
    {
        $item = $this->item();
        $this->buy($item, 5, 1000);
        $transfer = app(ShipStockTransfer::class)->handle($this->draft([[$item, 3]]));
        $line = $transfer->items->first();

        foreach ([[[$line->id => 4], '2026-10-12'], [[], '2026-10-09']] as [$qty, $date]) {
            try {
                app(ReceiveStockTransfer::class)->handle($transfer, $qty, ReceiveStockTransfer::RETURN, $date);
                $this->fail('should be refused');
            } catch (ValidationException) {
            }
        }
        $this->assertSame(StockTransfer::STATUS_IN_TRANSIT, $transfer->fresh()->status);
        $this->assertSame(0.0, $this->onHand($item, $this->kano));
    }

    // ---- rules ----------------------------------------------------------

    public function test_warehouses_must_differ_be_in_use_and_belong_to_the_business(): void
    {
        $item = $this->item();
        $closed = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Old store', 'code' => 'OLD', 'is_active' => false]);
        $other = $this->otherTenant();
        $theirs = Warehouse::withoutTenantGuard(fn () => Warehouse::create(['tenant_id' => $other->id, 'name' => 'Theirs', 'code' => 'THR']));

        foreach ([[$this->main, $this->main, 'to_warehouse_id'], [$this->main, $closed, 'to_warehouse_id'], [$closed, $this->kano, 'from_warehouse_id'], [$this->main, $theirs, 'to_warehouse_id']] as [$from, $to, $key]) {
            try {
                $this->draft([[$item, 1]], $from, $to);
                $this->fail("{$from->name} to {$to->name} should be refused");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($key, $e->errors());
            }
        }

        $this->post(route('stock-transfers.store'), [
            'transfer_date' => '2026-10-10', 'from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $theirs->id,
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('to_warehouse_id');
        $this->assertSame(0, StockTransfer::count());
    }

    public function test_items_that_keep_no_stock_cannot_be_transferred(): void
    {
        $service = Item::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'service', 'track_inventory' => false, 'name' => 'Delivery']);
        try {
            $this->draft([[$service, 1]]);
            $this->fail('a service');
        } catch (ValidationException $e) {
            $this->assertStringContainsString("doesn't keep stock", $e->getMessage());
        }

        // An item switched off stock after the draft was made can't be shipped either.
        $item = $this->item();
        $this->buy($item, 5, 1000);
        $transfer = $this->draft([[$item, 2]]);
        $item->update(['track_inventory' => false]);
        $this->expectException(ValidationException::class);
        app(ShipStockTransfer::class)->handle($transfer);
    }

    public function test_only_a_draft_can_be_deleted_and_a_received_transfer_cannot_be_cancelled(): void
    {
        $item = $this->item();
        $this->buy($item, 10, 1000);

        $draft = $this->draft([[$item, 1]]);
        $this->assertNotNull(app(CancelStockTransfer::class)->blockedBecause($draft));
        $this->delete(route('stock-transfers.destroy', $draft))->assertRedirect(route('stock-transfers.index'));
        $this->assertNull(StockTransfer::find($draft->id));

        $shipped = app(ShipStockTransfer::class)->handle($this->draft([[$item, 1]]));
        $this->delete(route('stock-transfers.destroy', $shipped))->assertSessionHas('error');
        $this->assertNotNull(StockTransfer::find($shipped->id));

        $received = app(TransferNow::class)->handle($this->draft([[$item, 1]]));
        $this->post(route('stock-transfers.cancel', $received))->assertSessionHas('error');
        $this->assertSame(StockTransfer::STATUS_RECEIVED, $received->fresh()->status);
        try {
            app(CancelStockTransfer::class)->handle($received);
            $this->fail('received is final');
        } catch (ValidationException) {
        }
        try {
            app(DeleteStockTransfer::class)->handle($received);
            $this->fail('received is final');
        } catch (ValidationException) {
        }
        $this->assertSame(1.0, $this->onHand($item, $this->kano));
    }

    public function test_lock_dates_apply_to_shipping_receiving_and_deleting(): void
    {
        $item = $this->item();
        $this->buy($item, 10, 1000, $this->main, '2026-10-01');
        app(UpdateLockDates::class)->handle($this->tenant, ['staff_lock_date' => '2026-10-05', 'all_users_lock_date' => null, 'reason' => null]);

        $locked = $this->draft([[$item, 2]], null, null, '2026-10-03');
        try {
            app(ShipStockTransfer::class)->handle($locked);
            $this->fail('shipping into a locked date');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('transfer_date', $e->errors());
        }
        $this->assertSame(10.0, $this->onHand($item, $this->main));
        try {
            app(DeleteStockTransfer::class)->handle($locked);
            $this->fail('deleting a transfer dated in a locked month');
        } catch (ValidationException) {
        }
        $this->assertSame(StockTransfer::STATUS_DRAFT, $locked->fresh()->status);

        // Cancelling undoes the shipment, so its date is checked too.
        $shipped = app(ShipStockTransfer::class)->handle($this->draft([[$item, 2]], null, null, '2026-10-06'));
        app(UpdateLockDates::class)->handle($this->tenant, ['staff_lock_date' => '2026-10-07', 'all_users_lock_date' => null, 'reason' => null]);
        try {
            app(CancelStockTransfer::class)->handle($shipped);
            $this->fail('cancelling a shipment in a locked month');
        } catch (ValidationException) {
        }
        $this->assertSame(StockTransfer::STATUS_IN_TRANSIT, $shipped->fresh()->status);
    }

    public function test_receiving_on_a_locked_date_is_refused(): void
    {
        $item = $this->item();
        $this->buy($item, 10, 1000, $this->main, '2026-10-01');
        $transfer = app(ShipStockTransfer::class)->handle($this->draft([[$item, 2]], null, null, '2026-10-02'));
        app(UpdateLockDates::class)->handle($this->tenant, ['staff_lock_date' => '2026-10-05', 'all_users_lock_date' => null, 'reason' => null]);

        $this->post(route('stock-transfers.receive', $transfer), ['received_date' => '2026-10-04'])->assertSessionHasErrors('received_date');
        $this->assertSame(StockTransfer::STATUS_IN_TRANSIT, $transfer->fresh()->status);
        $this->post(route('stock-transfers.receive', $transfer), ['received_date' => '2026-10-06'])->assertSessionHasNoErrors();
        $this->assertSame(2.0, $this->onHand($item, $this->kano));
    }

    // ---- screens ---------------------------------------------------------

    public function test_the_screens_work_through_the_whole_flow(): void
    {
        $item = $this->item('fifo', 'Cement bag');
        $this->buy($item, 10, 30000);

        $this->get(route('stock-transfers.create'))->assertOk()->assertSee('Transfer now')->assertSee('Ship only');
        $this->getJson(route('stock-transfers.items', ['warehouse_id' => $this->main->id, 'q' => 'Cem']))->assertOk()
            ->assertJsonPath('data.0.name', 'Cement bag')->assertJsonPath('data.0.free', 10);

        $this->post(route('stock-transfers.store'), [
            'transfer_date' => '2026-10-10', 'from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $this->kano->id,
            'reference' => 'Truck KN 123', 'action' => 'draft', 'items' => [['item_id' => $item->id, 'quantity' => 4]],
        ])->assertSessionHasNoErrors();
        $transfer = StockTransfer::sole();
        $this->assertSame(StockTransfer::STATUS_DRAFT, $transfer->status);
        $this->assertSame(10.0, $this->onHand($item, $this->main), 'a draft moves nothing');

        $this->get(route('stock-transfers.edit', $transfer))->assertOk()->assertSee('Cement bag');
        $this->put(route('stock-transfers.update', $transfer), [
            'transfer_date' => '2026-10-10', 'from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $this->kano->id,
            'items' => [['item_id' => $item->id, 'quantity' => 5]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(5.0, (float) $transfer->items()->value('quantity'));

        $this->get(route('stock-transfers.show', $transfer))->assertOk()->assertSee('Transfer now')->assertSee('Delete');
        $this->post(route('stock-transfers.ship', $transfer))->assertSessionHas('success');
        $this->get(route('stock-transfers.show', $transfer))->assertOk()->assertSee('In transit')->assertSee('Receive goods')->assertSee('Cancel transfer');
        $this->get(route('stock-transfers.print', $transfer))->assertOk()->assertSee('STOCK TRANSFER NOTE')->assertSee('Kano shop')->assertSee('Cement bag');
        $this->post(route('stock-transfers.receive', $transfer), ['received_date' => '2026-10-11'])->assertSessionHas('success');
        $this->get(route('stock-transfers.show', $transfer))->assertOk()->assertSee('Received')->assertSee('Transfer goods back');
        $this->get(route('stock-transfers.index'))->assertOk()->assertSee('Stock transfers');

        Livewire::test(StockTransfersTable::class)->assertSee($transfer->transfer_number)
            ->set('tab', 'draft')->assertDontSee($transfer->transfer_number)
            ->set('tab', '')->set('warehouse', (string) $this->kano->id)->assertSee($transfer->transfer_number);
    }

    public function test_the_create_page_says_to_add_a_second_warehouse_when_there_is_one(): void
    {
        $this->kano->update(['is_active' => false]);

        $this->get(route('stock-transfers.create'))->assertOk()
            ->assertSee('Add a second warehouse to move stock between them')
            ->assertDontSee('Transfer now');
    }

    public function test_numbers_follow_on(): void
    {
        $item = $this->item();
        $a = $this->draft([[$item, 1]]);
        $b = $this->draft([[$item, 1]]);

        $this->assertSame('TRF-000001', $a->transfer_number);
        $this->assertSame('TRF-000002', $b->transfer_number);
    }

    public function test_a_warehouse_with_transfers_cannot_be_deleted_or_switched_off_while_goods_are_on_the_road(): void
    {
        $item = $this->item();
        $this->buy($item, 5, 1000);
        $transfer = app(ShipStockTransfer::class)->handle($this->draft([[$item, 5]]));
        $this->user->givePermissionTo(Permission::findOrCreate('delete items', 'web'));

        $this->assertTrue($this->kano->holdsStock());
        $this->delete(route('warehouses.destroy', $this->kano))->assertSessionHas('error');
        app(ReceiveStockTransfer::class)->handle($transfer);
        app(TransferNow::class)->handle($this->draft([[$item, 5]], $this->kano, $this->main));
        $this->assertFalse($this->kano->fresh()->holdsStock());
        $this->delete(route('warehouses.destroy', $this->kano))->assertSessionHas('error');
        $this->assertNotNull(Warehouse::find($this->kano->id), 'its transfers would be lost');
    }

    // ---- isolation, permissions, switch -------------------------------------

    public function test_another_businesss_transfers_cannot_be_seen_or_touched(): void
    {
        $other = $this->otherTenant();
        $theirs = StockTransfer::withoutTenantGuard(function () use ($other) {
            $a = Warehouse::create(['tenant_id' => $other->id, 'name' => 'A', 'code' => 'A']);
            $b = Warehouse::create(['tenant_id' => $other->id, 'name' => 'B', 'code' => 'B']);

            return StockTransfer::create(['tenant_id' => $other->id, 'transfer_number' => 'TRF-000001', 'transfer_date' => '2026-10-10',
                'from_warehouse_id' => $a->id, 'to_warehouse_id' => $b->id, 'status' => 'draft']);
        });

        $this->get(route('stock-transfers.show', $theirs))->assertNotFound();
        $this->post(route('stock-transfers.ship', $theirs))->assertNotFound();
        $this->delete(route('stock-transfers.destroy', $theirs))->assertNotFound();
        $this->getJson('/api/v1/stock-transfers/'.$theirs->id)->assertNotFound();
        Livewire::test(StockTransfersTable::class)->assertDontSee('TRF-000001');
        $this->assertNotNull(StockTransfer::withoutGlobalScopes()->find($theirs->id));
    }

    public function test_an_item_of_another_business_cannot_be_sent(): void
    {
        $other = $this->otherTenant();
        $theirItem = Item::withoutTenantGuard(fn () => Item::factory()->product()->create(['tenant_id' => $other->id]));

        $this->post(route('stock-transfers.store'), [
            'transfer_date' => '2026-10-10', 'from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $this->kano->id,
            'items' => [['item_id' => $theirItem->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items.0.item_id');
    }

    public function test_transfers_need_the_adjust_inventory_permission(): void
    {
        $item = $this->item();
        $transfer = $this->draft([[$item, 1]]);
        $viewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $viewer->givePermissionTo(['view inventory', 'view items']);
        $this->actingAs($viewer);

        $this->get(route('stock-transfers.index'))->assertForbidden();
        $this->get(route('stock-transfers.show', $transfer))->assertForbidden();
        $this->post(route('stock-transfers.ship', $transfer))->assertForbidden();
        $this->postJson('/api/v1/stock-transfers', [])->assertForbidden();
        $this->get(route('items.index'))->assertOk()->assertDontSee('">Stock transfers</a>', false);
    }

    public function test_switched_off_the_module_is_hidden(): void
    {
        $this->get(route('items.index'))->assertSee('">Stock transfers</a>', false);

        config(['mybooks.features.stock_transfers' => false]);
        $this->get(route('stock-transfers.index'))->assertNotFound();
        $this->get(route('items.index'))->assertDontSee('">Stock transfers</a>', false);
        $this->getJson('/api/v1/stock-transfers')->assertNotFound();

        config(['mybooks.features.stock_transfers' => true, 'mybooks.features.warehouses' => false]);
        $this->get(route('stock-transfers.index'))->assertNotFound();
    }

    public function test_the_api_can_list_read_and_create_with_ship_and_receive(): void
    {
        $item = $this->item();
        $this->buy($item, 10, 1000);

        $this->postJson('/api/v1/stock-transfers', [
            'transfer_date' => '2026-10-10', 'from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $this->kano->id,
            'items' => [['item_id' => $item->id, 'quantity' => 3]], 'receive' => true,
        ])->assertCreated()->assertJsonPath('data.status', 'received')->assertJsonPath('data.shipped_cost', 3000);
        $this->assertSame(3.0, $this->onHand($item, $this->kano));

        $this->postJson('/api/v1/stock-transfers', [
            'transfer_date' => '2026-10-10', 'from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $this->kano->id,
            'items' => [['item_id' => $item->id, 'quantity' => 2]], 'ship' => true,
        ])->assertCreated()->assertJsonPath('data.status', 'in_transit');

        $this->postJson('/api/v1/stock-transfers', [
            'transfer_date' => '2026-10-10', 'from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $this->kano->id,
            'items' => [['item_id' => $item->id, 'quantity' => 50]], 'ship' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->getJson('/api/v1/stock-transfers?status=in_transit')->assertOk()->assertJsonCount(1, 'data');
        $id = StockTransfer::where('status', 'received')->value('id');
        $this->getJson('/api/v1/stock-transfers/'.$id)->assertOk()->assertJsonPath('data.items.0.quantity_received', 3);
    }

    // ---- the migration ---------------------------------------------------

    public function test_migration_adds_the_stock_losses_account_without_clashing_and_reruns(): void
    {
        $migration = require database_path('migrations/2026_10_13_130001_rework_stock_transfers.php');
        ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '5400')->forceDelete();
        // Another business already uses 5400 for something else.
        $other = $this->otherTenant();
        ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $other->id)->where('account_code', '5400')->update(['name' => 'Packaging']);

        $migration->up();
        $migration->up();

        $this->assertSame('Stock Losses', ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', '5400')->value('name'));
        $this->assertSame(1, ChartOfAccount::where('tenant_id', $this->tenant->id)->where('name', 'Stock Losses')->count());
        $this->assertSame('Packaging', ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $other->id)->where('account_code', '5400')->value('name'));
        $this->assertSame('Stock Losses', ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $other->id)->where('account_code', '5410')->value('name'));
        $this->assertSame('5410', AccountCodeService::resolve($other->id, 'stock_losses'));
        $this->assertSame(1, ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $other->id)->where('name', 'Stock Losses')->count());
    }

    public function test_migration_turns_old_completed_transfers_into_received(): void
    {
        $migration = require database_path('migrations/2026_10_13_130001_rework_stock_transfers.php');
        DB::table('stock_transfers')->insert([
            'tenant_id' => $this->tenant->id, 'transfer_number' => 'ST-00001', 'from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $this->kano->id,
            'status' => 'completed', 'created_at' => '2026-05-04 09:00:00', 'updated_at' => '2026-05-04 09:00:00',
        ]);

        $migration->up();

        $row = DB::table('stock_transfers')->where('transfer_number', 'ST-00001')->first();
        $this->assertSame('received', $row->status);
        $this->assertSame('2026-05-04', substr((string) $row->transfer_date, 0, 10));
    }

    // ---- regressions in the old transfer code --------------------------------

    /** Made straight in the table, the way the old screens would have. */
    private function oldStyleTransfer(Item $item, float $qty): StockTransfer
    {
        $transfer = StockTransfer::create([
            'tenant_id' => $this->tenant->id, 'transfer_number' => 'ST-'.uniqid(), 'transfer_date' => '2026-10-10',
            'from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $this->kano->id, 'status' => 'draft',
        ]);
        $transfer->items()->create(['item_id' => $item->id, 'quantity' => $qty, 'quantity_received' => 0]);

        return $transfer;
    }

    public function test_regression_shipping_can_not_take_more_than_is_on_hand(): void
    {
        config(['mybooks.features.stock_transfers' => true]);
        $item = $this->item();
        $this->buy($item, 3, 1000);
        $transfer = $this->oldStyleTransfer($item, 5);

        $this->post(route('stock-transfers.ship', $transfer));

        $this->assertSame(3.0, $this->onHand($item, $this->main), 'the old code took 5 and left -2');
        $this->assertSame('draft', $transfer->fresh()->status);
    }

    public function test_regression_shipping_takes_the_cost_out_of_the_source_warehouse(): void
    {
        config(['mybooks.features.stock_transfers' => true]);
        $item = $this->item();
        $this->buy($item, 10, 1000);
        $transfer = $this->oldStyleTransfer($item, 4);

        $this->post(route('stock-transfers.ship', $transfer));

        $this->assertSame([[6.0, 1000.0]], $this->layers($item, $this->main), 'the old code left all 10 in the cost layers');
    }

    public function test_regression_receiving_fewer_does_not_make_the_rest_disappear(): void
    {
        config(['mybooks.features.stock_transfers' => true]);
        $item = $this->item();
        $this->buy($item, 10, 1000);
        $transfer = $this->oldStyleTransfer($item, 5);
        $this->post(route('stock-transfers.ship', $transfer));
        $line = $transfer->items()->first();

        $this->post(route('stock-transfers.receive', $transfer), ['items' => [['id' => $line->id, 'quantity_received' => 3]]]);

        $this->assertSame(3.0, $this->onHand($item, $this->kano));
        $this->assertSame(7.0, $this->onHand($item, $this->main), 'the old code lost the 2 that did not arrive');
    }
}
