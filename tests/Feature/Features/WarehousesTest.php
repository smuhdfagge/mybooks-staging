<?php

namespace Tests\Feature\Features;

use App\Actions\Bills\SaveBill;
use App\Actions\CreditNotes\SaveCreditNote;
use App\Actions\CreditNotes\VoidCreditNote;
use App\Actions\DeliveryNotes\SaveDeliveryNote;
use App\Actions\Invoices\SaveInvoice;
use App\Actions\LockDates\UpdateLockDates;
use App\Actions\Payments\RecordPaymentReceived;
use App\Actions\SalesOrders\SaveSalesOrder;
use App\Actions\SalesReceipts\SaveSalesReceipt;
use App\Actions\VendorCredits\SaveVendorCredit;
use App\Livewire\Inventory\InventoryTable;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\InventoryLayer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\Accounting\FinancialStatements;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Session 12: warehouses. Stock is kept per item per warehouse, every
 * stock document says which warehouse, and existing stock went into a
 * default "Main warehouse".
 */
class WarehousesTest extends TestCase
{
    private const PERMISSIONS = [
        'view invoices', 'create invoices', 'edit invoices', 'delete invoices', 'view customers',
        'create payments-received', 'view bills', 'create bills', 'edit bills', 'view reports',
        'delete bills', 'view inventory', 'adjust inventory', 'create items', 'edit items', 'delete items', 'view items',
        'view sales-receipts', 'create sales-receipts', 'view sales-orders', 'create sales-orders', 'edit sales-orders',
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
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Aminu Stores']);
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

    private function item(string $method = 'weighted_average', string $name = 'Rice bag'): Item
    {
        return Item::factory()->product()->create([
            'tenant_id' => $this->tenant->id, 'name' => $name, 'cost_price' => 0, 'selling_price' => 1500, 'valuation_method' => $method,
        ]);
    }

    private function buy(Item $item, float $qty, float $cost, ?Warehouse $warehouse = null, string $date = '2026-10-01'): Bill
    {
        return app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_date' => $date, 'due_date' => '2026-11-30',
            'warehouse_id' => $warehouse?->id,
            'items' => [['item_id' => $item->id, 'description' => $item->name, 'quantity' => $qty, 'unit_price' => $cost, 'tax_rate' => 0]],
        ], $this->user->id);
    }

    private function invoice(Item $item, float $qty, ?Warehouse $warehouse = null, float $price = 1500): Invoice
    {
        return app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_date' => '2026-10-05', 'due_date' => '2026-11-05', 'status' => 'unpaid',
            'warehouse_id' => $warehouse?->id,
            'items' => [['item_id' => $item->id, 'description' => $item->name, 'quantity' => $qty, 'unit_price' => $price, 'tax_rate' => 0]],
        ], $this->user->id);
    }

    /** Paid in full and released, so the goods leave the warehouse. */
    private function sellAndRelease(Item $item, float $qty, ?Warehouse $warehouse = null): Invoice
    {
        $invoice = $this->invoice($item, $qty, $warehouse);
        app(RecordPaymentReceived::class)->handle($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_id' => $invoice->id, 'payment_date' => '2026-10-06',
            'amount' => (float) $invoice->total, 'payment_method' => 'cash',
        ], $this->user->id);
        $this->post(route('invoices.release', $invoice))->assertSessionHas('success');

        return $invoice->fresh();
    }

    private function onHand(Item $item, ?Warehouse $warehouse = null): float
    {
        return (float) Inventory::where('item_id', $item->id)
            ->when($warehouse, fn ($q) => $q->where('warehouse_id', $warehouse->id))->sum('quantity');
    }

    private function reserved(Item $item, Warehouse $warehouse): float
    {
        return (float) Inventory::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->value('reserved_quantity');
    }

    private function layers(Item $item, ?Warehouse $warehouse = null): float
    {
        return (float) InventoryLayer::where('item_id', $item->id)
            ->when($warehouse, fn ($q) => $q->where('warehouse_id', $warehouse->id))->sum('remaining_quantity');
    }

    private function balance(string $code): float
    {
        return round((float) ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', $code)->value('current_balance'), 2);
    }

    /** Another business, made while signed out so its records aren't filed under ours. */
    private function otherTenant(): Tenant
    {
        auth()->forgetGuards();
        [$other] = $this->createTenantWithSubscription();
        $this->actingAs($this->user);

        return $other;
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_12_120001_add_warehouses_to_stock.php');
    }

    // ---- the data migration --------------------------------------------

    public function test_migration_puts_existing_stock_in_a_main_warehouse_and_changes_no_totals(): void
    {
        $this->kano->delete();
        $item = $this->item();
        $this->buy($item, 10, 1000);
        $this->buy($item, 5, 1300);
        app(SaveSalesReceipt::class)->create($this->tenant->id, [
            'receipt_date' => '2026-10-07', 'payment_method' => 'cash',
            'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 3, 'unit_price' => 1500, 'tax_rate' => 0]],
        ], $this->user->id);
        $invoice = $this->invoice($item, 2);

        $before = [
            'total' => (float) $item->fresh()->inventory->quantity,
            'reserved' => (float) $item->fresh()->inventory->reserved_quantity,
            'layers' => $this->layers($item),
            'value' => (float) InventoryLayer::where('item_id', $item->id)->selectRaw('SUM(remaining_quantity * unit_cost) as v')->value('v'),
            'books' => app(FinancialStatements::class)->balanceSheet($this->tenant->id, '2026-12-31')['inventory'],
            'histories' => InventoryHistory::where('item_id', $item->id)->count(),
        ];
        $this->assertSame(12.0, $before['total']);

        // As the data was before warehouses: nothing says where it is, and no warehouse exists.
        $tables = ['inventories', 'inventory_layers', 'inventory_histories', 'inventory_layer_consumptions', 'bills', 'invoices', 'sales_receipts'];
        foreach ($tables as $table) {
            DB::table($table)->where('tenant_id', $this->tenant->id)->update(['warehouse_id' => null]);
        }
        DB::table('warehouses')->where('tenant_id', $this->tenant->id)->delete();

        $this->migration()->up();
        $this->migration()->up(); // rerunnable

        $warehouses = Warehouse::where('tenant_id', $this->tenant->id)->get();
        $this->assertCount(1, $warehouses);
        $main = $warehouses->first();
        $this->assertSame(['Main warehouse', 'MAIN', true, true], [$main->name, $main->code, $main->is_default, $main->is_active]);

        foreach ($tables as $table) {
            $this->assertSame(0, DB::table($table)->where('tenant_id', $this->tenant->id)->whereNull('warehouse_id')->count(), "{$table} rows all have a warehouse");
            $this->assertSame(0, DB::table($table)->where('tenant_id', $this->tenant->id)->where('warehouse_id', '!=', $main->id)->count());
        }

        $item = $item->fresh();
        $this->assertSame($before['total'], (float) $item->inventory->quantity);
        $this->assertSame($before['reserved'], (float) $item->inventory->reserved_quantity);
        $this->assertSame($before['layers'], $this->layers($item, $main));
        $this->assertEqualsWithDelta($before['value'], (float) InventoryLayer::where('item_id', $item->id)->selectRaw('SUM(remaining_quantity * unit_cost) as v')->value('v'), 0.001);
        $this->assertSame($before['books'], app(FinancialStatements::class)->balanceSheet($this->tenant->id, '2026-12-31')['inventory'], 'inventory on the balance sheet is unchanged');
        $this->assertSame($before['histories'], InventoryHistory::where('item_id', $item->id)->count());

        // Business as usual afterwards: the reserved invoice can be released from the main warehouse.
        app(RecordPaymentReceived::class)->handle($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_id' => $invoice->id, 'payment_date' => '2026-10-08', 'amount' => 3000, 'payment_method' => 'cash',
        ], $this->user->id);
        $this->post(route('invoices.release', $invoice))->assertSessionHas('success');
        $this->assertSame(10.0, $this->onHand($item, $main));
        $this->assertSame(0.0, $this->reserved($item, $main));
    }

    public function test_migration_adds_a_loose_stock_record_to_the_existing_default_warehouse(): void
    {
        $item = $this->item();
        $this->buy($item, 10, 1000);
        DB::table('inventories')->insert([
            'tenant_id' => $this->tenant->id, 'item_id' => $item->id, 'warehouse_id' => null,
            'quantity' => 4, 'reserved_quantity' => 1, 'unit_cost' => 1500, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->migration()->up();

        $rows = Inventory::where('item_id', $item->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame($this->main->id, $rows->first()->warehouse_id);
        $this->assertSame(14.0, (float) $rows->first()->quantity);
        $this->assertSame(1.0, (float) $rows->first()->reserved_quantity);
        $this->assertEqualsWithDelta((10 * 1000 + 4 * 1500) / 14, (float) $rows->first()->unit_cost, 0.01);
    }

    public function test_a_new_business_gets_a_default_warehouse(): void
    {
        $tenant = $this->otherTenant();

        $warehouses = Warehouse::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();
        $this->assertCount(1, $warehouses);
        $this->assertTrue($warehouses->first()->is_default);
        $this->assertSame('Main warehouse', $warehouses->first()->name);
    }

    // ---- stock per warehouse ------------------------------------------

    public function test_buying_into_b_and_selling_from_b_moves_only_b(): void
    {
        $item = $this->item();
        $this->buy($item, 6, 1000, $this->main);
        $this->buy($item, 10, 1000, $this->kano);

        $invoice = $this->invoice($item, 3, $this->kano);
        $this->assertSame($this->kano->id, $invoice->warehouse_id);
        $this->assertSame(3.0, $this->reserved($item, $this->kano));
        $this->assertSame(0.0, $this->reserved($item, $this->main));

        $this->sellAndRelease($item, 0.0 + 4, $this->kano);

        $this->assertSame(6.0, $this->onHand($item, $this->main), 'the main warehouse is untouched');
        $this->assertSame(6.0, $this->onHand($item, $this->kano));
        $this->assertSame(6.0, $this->layers($item, $this->main));
        $this->assertSame(3.0, $this->layers($item, $this->kano), 'both invoices costed out of Kano');
        $this->assertSame(12.0, (float) $item->fresh()->inventory->quantity, 'item total = sum over warehouses');
        $this->assertSame(1, InventoryHistory::where('item_id', $item->id)->where('type', 'out')->where('warehouse_id', $this->kano->id)->count());
    }

    public function test_selling_from_a_does_not_touch_b_and_the_total_is_the_sum(): void
    {
        $item = $this->item();
        $this->buy($item, 5, 1000, $this->main);
        $this->buy($item, 7, 1000, $this->kano);

        $this->sellAndRelease($item, 2);
        app(SaveSalesReceipt::class)->create($this->tenant->id, [
            'receipt_date' => '2026-10-07', 'payment_method' => 'cash', 'warehouse_id' => $this->main->id,
            'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 1, 'unit_price' => 1500, 'tax_rate' => 0]],
        ], $this->user->id);

        $this->assertSame(2.0, $this->onHand($item, $this->main));
        $this->assertSame(7.0, $this->onHand($item, $this->kano));
        $item = $item->fresh();
        $this->assertSame(9.0, (float) $item->inventory->quantity);
        $this->assertSame(9.0, (float) $item->inventories()->sum('quantity'));
    }

    public function test_the_no_negative_stock_rule_applies_per_warehouse(): void
    {
        $item = $this->item();
        $this->buy($item, 5, 1000, $this->main);
        $this->buy($item, 10, 1000, $this->kano);

        try {
            $this->invoice($item, 6, $this->main);
            $this->fail('6 can\'t be sold from a warehouse holding 5, even with 15 in the business');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Main warehouse', implode(' ', $e->validator->errors()->all()));
        }
        try {
            app(SaveSalesReceipt::class)->create($this->tenant->id, [
                'receipt_date' => '2026-10-07', 'payment_method' => 'cash', 'warehouse_id' => $this->main->id,
                'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 6, 'unit_price' => 1500, 'tax_rate' => 0]],
            ], $this->user->id);
            $this->fail('cash sale beyond the warehouse stock');
        } catch (ValidationException) {
        }

        $this->invoice($item, 6, $this->kano);
        $this->assertSame(6.0, $this->reserved($item, $this->kano));
        $this->assertSame(0, Invoice::where('warehouse_id', $this->main->id)->count());
    }

    public function test_moving_an_unreleased_invoice_to_another_warehouse_moves_its_reservation_and_cost(): void
    {
        $item = $this->item();
        $this->buy($item, 5, 1000, $this->main);
        $this->buy($item, 5, 2000, $this->kano);
        $invoice = $this->invoice($item, 2, $this->main);
        $this->assertSame(2000.0, $this->balance('5000'));

        app(SaveInvoice::class)->update($invoice, ['warehouse_id' => $this->kano->id]);

        $this->assertSame(0.0, $this->reserved($item, $this->main));
        $this->assertSame(2.0, $this->reserved($item, $this->kano));
        $this->assertSame(5.0, $this->layers($item, $this->main));
        $this->assertSame(3.0, $this->layers($item, $this->kano));
        $this->assertSame(4000.0, $this->balance('5000'), 'cost of sales is Kano\'s cost now');
    }

    public function test_cost_of_sales_uses_the_selling_warehouses_cost(): void
    {
        foreach (['weighted_average', 'fifo'] as $method) {
            $item = $this->item($method, "Rice {$method}");
            $this->buy($item, 10, 1000, $this->main);
            $this->buy($item, 10, 1600, $this->kano);
            $cogsBefore = $this->balance('5000');

            $fromKano = $this->invoice($item, 2, $this->kano);
            $fromMain = $this->invoice($item, 3, $this->main);

            $this->assertEquals(1600, (float) $fromKano->items->first()->unit_cost, $method);
            $this->assertEquals(1000, (float) $fromMain->items->first()->unit_cost, $method);
            $this->assertSame(round($cogsBefore + 3200 + 3000, 2), $this->balance('5000'), $method);
        }

        $tb = app(FinancialStatements::class)->trialBalance($this->tenant->id, '2026-12-31');
        $this->assertEqualsWithDelta($tb->sum('total_debit'), $tb->sum('total_credit'), 0.001);
    }

    public function test_deleting_a_bill_takes_its_goods_out_of_its_own_warehouse(): void
    {
        $item = $this->item();
        $this->buy($item, 4, 1000, $this->main);
        $bill = $this->buy($item, 10, 1000, $this->kano);

        $this->delete(route('bills.destroy', $bill));

        $this->assertSame(4.0, $this->onHand($item, $this->main));
        $this->assertSame(0.0, $this->onHand($item, $this->kano));
    }

    // ---- returns --------------------------------------------------------

    public function test_a_credit_note_return_goes_back_to_the_warehouse_the_goods_left_from(): void
    {
        $item = $this->item();
        $this->buy($item, 10, 1000, $this->main);
        $this->buy($item, 10, 1200, $this->kano);
        $invoice = $this->sellAndRelease($item, 4, $this->kano);
        $this->assertSame(6.0, $this->onHand($item, $this->kano));

        $note = app(SaveCreditNote::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_id' => $invoice->id, 'credit_note_date' => '2026-10-10',
            'reason' => 'product_return', 'restock' => true, 'status' => 'open',
            'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 1, 'unit_price' => 1500, 'tax_rate' => 0]],
        ], $this->user->id);

        $this->assertSame($this->kano->id, $note->fresh()->warehouse_id);
        $this->assertSame(7.0, $this->onHand($item, $this->kano));
        $this->assertSame(10.0, $this->onHand($item, $this->main));

        // Or to a chosen warehouse.
        $chosen = app(SaveCreditNote::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'invoice_id' => $invoice->id, 'credit_note_date' => '2026-10-10',
            'reason' => 'product_return', 'restock' => true, 'status' => 'open', 'warehouse_id' => $this->main->id,
            'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 1, 'unit_price' => 1500, 'tax_rate' => 0]],
        ], $this->user->id);
        $this->assertSame(11.0, $this->onHand($item, $this->main));
        $this->assertSame(1, InventoryLayer::where('reference_type', CreditNote::class)->where('reference_id', $chosen->id)->where('warehouse_id', $this->main->id)->count());

        // Voiding takes them out of that same warehouse.
        app(VoidCreditNote::class)->handle($chosen);
        $this->assertSame(10.0, $this->onHand($item, $this->main));
        $this->assertSame(7.0, $this->onHand($item, $this->kano));
    }

    public function test_a_supplier_credit_sends_goods_back_from_the_bills_warehouse(): void
    {
        $item = $this->item();
        $this->buy($item, 10, 1000, $this->main);
        $bill = $this->buy($item, 10, 1000, $this->kano);

        $credit = app(SaveVendorCredit::class)->create($this->tenant->id, [
            'vendor_id' => $this->vendor->id, 'bill_id' => $bill->id, 'credit_date' => '2026-10-10',
            'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 3, 'unit_price' => 1000, 'tax_rate' => 0]],
        ], $this->user->id);

        $this->assertSame($this->kano->id, $credit->fresh()->warehouse_id);
        $this->assertSame(7.0, $this->onHand($item, $this->kano));
        $this->assertSame(10.0, $this->onHand($item, $this->main));
    }

    public function test_a_delivery_note_records_the_warehouse_it_is_sent_from_and_moves_no_stock(): void
    {
        $item = $this->item();
        $this->buy($item, 10, 1000, $this->kano);
        $order = app(SaveSalesOrder::class)->create($this->tenant->id, [
            'customer_id' => $this->customer->id, 'order_date' => '2026-10-10', 'status' => 'confirmed',
            'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 5, 'unit_price' => 1500, 'tax_rate' => 0]],
        ], $this->user->id);
        $line = $order->items()->first();

        $note = app(SaveDeliveryNote::class)->create($order, [
            'delivery_date' => '2026-10-12', 'warehouse_id' => $this->kano->id,
            'lines' => [['sales_order_item_id' => $line->id, 'quantity' => 2]],
        ], $this->user->id);
        $this->assertSame($this->kano->id, $note->warehouse_id);
        $this->assertSame(10.0, $this->onHand($item, $this->kano));

        $plain = app(SaveDeliveryNote::class)->create($order, [
            'delivery_date' => '2026-10-12', 'lines' => [['sales_order_item_id' => $line->id, 'quantity' => 1]],
        ], $this->user->id);
        $this->assertSame($this->main->id, $plain->warehouse_id, 'default warehouse when none is chosen');

        $this->get(route('delivery-notes.show', $note))->assertOk()->assertSee('Kano shop');
    }

    // ---- adjustments ------------------------------------------------------

    public function test_stock_adjustments_are_per_warehouse(): void
    {
        $item = $this->item();
        $this->buy($item, 5, 1000, $this->main);

        $this->post(route('inventory.adjust', $item), ['type' => 'in', 'quantity' => 8, 'unit_cost' => 900, 'warehouse_id' => $this->kano->id])
            ->assertSessionHas('success');
        $this->assertSame(8.0, $this->onHand($item, $this->kano));
        $this->assertSame(5.0, $this->onHand($item, $this->main));
        $this->assertSame(8.0, $this->layers($item, $this->kano));

        $this->post(route('inventory.adjust', $item), ['type' => 'out', 'quantity' => 2, 'warehouse_id' => $this->kano->id])->assertSessionHas('success');
        $this->assertSame(6.0, $this->onHand($item, $this->kano));
        $this->assertSame(6.0, $this->layers($item, $this->kano));
        $this->assertSame(5.0, $this->layers($item, $this->main));

        $this->post(route('inventory.adjust', $item), ['type' => 'adjustment', 'quantity' => 4, 'warehouse_id' => $this->kano->id])->assertSessionHas('success');
        $this->assertSame(4.0, $this->onHand($item, $this->kano));
        $history = InventoryHistory::where('item_id', $item->id)->where('warehouse_id', $this->kano->id)->latest('id')->first();
        $this->assertSame('adjustment', $history->type);
        $this->assertEquals(-2, (float) $history->quantity, 'a count records the change');

        // Without a warehouse: the default one.
        $this->post(route('inventory.adjust', $item), ['type' => 'in', 'quantity' => 1])->assertSessionHas('success');
        $this->assertSame(6.0, $this->onHand($item, $this->main));
    }

    /** Old code let "Stock out" take more than was there, leaving stock below zero. */
    public function test_regression_stock_out_cannot_take_more_than_is_free(): void
    {
        $item = $this->item();
        $this->buy($item, 10, 1000, $this->main);
        $this->invoice($item, 4, $this->main);

        $this->from(route('inventory.show', $item))
            ->post(route('inventory.adjust', $item), ['type' => 'out', 'quantity' => 7])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(10.0, $this->onHand($item, $this->main));
    }

    /** Old code counted stock down without taking it out of the cost layers. */
    public function test_regression_counting_stock_down_takes_it_out_of_the_cost_layers(): void
    {
        $item = $this->item();
        $this->buy($item, 10, 1000, $this->main);

        $this->post(route('inventory.adjust', $item), ['type' => 'adjustment', 'quantity' => 6])->assertSessionHas('success');

        $this->assertSame(6.0, $this->onHand($item, $this->main));
        $this->assertSame(6.0, $this->layers($item), 'the cost layers match the count');
    }

    // ---- reports ----------------------------------------------------------

    public function test_the_stock_report_can_be_filtered_by_warehouse(): void
    {
        $item = $this->item();
        $item->update(['cost_price' => 1000]);
        $this->buy($item, 4, 1000, $this->main);
        $this->buy($item, 10, 1000, $this->kano);

        $all = $this->get(route('reports.inventory-summary'))->assertOk()->assertSee('All warehouses');
        $this->assertEquals(14000, $all->viewData('totalValue'));

        $kano = $this->get(route('reports.inventory-summary', ['warehouse_id' => $this->kano->id]))->assertOk();
        $this->assertEquals(10000, $kano->viewData('totalValue'));
        $this->assertSame(10.0, $kano->viewData('items')->first()->stock_quantity);

        $this->get(route('reports.export.inventory-summary', ['warehouse_id' => $this->kano->id, 'format' => 'csv']))->assertOk();

        $history = $this->get(route('inventory.history', [$item, 'warehouse_id' => $this->kano->id]))->assertOk();
        $this->assertTrue($history->viewData('history')->every(fn ($h) => $h->warehouse_id === $this->kano->id));

        $api = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/reports/inventory-summary?warehouse_id='.$this->kano->id)->assertOk();
        $this->assertEquals(10000, $api->json('data.summary.total_value'));
    }

    public function test_the_stock_list_can_show_one_warehouse(): void
    {
        $rice = $this->item();
        $beans = $this->item('weighted_average', 'Beans');
        $this->buy($rice, 4, 1000, $this->main);
        $this->buy($rice, 6, 1000, $this->kano);
        $this->buy($beans, 2, 1000, $this->main);

        $all = Livewire::test(InventoryTable::class)->viewData('items');
        $this->assertSame([10.0, 2.0], [(float) $all->firstWhere('id', $rice->id)->inventory->quantity, (float) $all->firstWhere('id', $beans->id)->inventory->quantity]);

        $kano = Livewire::test(InventoryTable::class)->set('warehouseFilter', (string) $this->kano->id)->set('stockFilter', 'in_stock')->viewData('items');
        $this->assertSame([$rice->id], $kano->pluck('id')->all());
        $this->assertSame(6.0, (float) $kano->first()->inventory->quantity);

        $out = Livewire::test(InventoryTable::class)->set('warehouseFilter', (string) $this->kano->id)->set('stockFilter', 'out_of_stock')->viewData('items');
        $this->assertSame([$beans->id], $out->pluck('id')->all());
    }

    // ---- warehouse screens -------------------------------------------------

    public function test_warehouse_list_show_and_item_pages_show_stock_per_warehouse(): void
    {
        $item = $this->item();
        $this->buy($item, 4, 1000, $this->main);
        $this->buy($item, 10, 1500, $this->kano);

        $list = $this->get(route('warehouses.index'))->assertOk()->assertSee('Kano shop')->assertSee('Main warehouse');
        $this->assertEquals(15000, (float) $list->viewData('totals')->get($this->kano->id)->stock_value);

        $this->get(route('warehouses.show', $this->kano))->assertOk()->assertSee('Rice bag')->assertSee('15,000.00');
        $this->get(route('items.show', $item))->assertOk()->assertSee('Stock by warehouse')->assertSee('Kano shop');
        $this->get(route('inventory.show', $item))->assertOk()->assertSee('Stock by warehouse');
    }

    public function test_warehouses_can_be_added_and_edited(): void
    {
        $this->post(route('warehouses.store'), ['name' => 'Abuja depot', 'code' => 'ABJ', 'address' => 'Wuse 2', 'is_active' => 1])
            ->assertRedirect();
        $abuja = Warehouse::where('code', 'ABJ')->firstOrFail();
        $this->assertTrue($abuja->is_active);
        $this->assertFalse($abuja->is_default);

        $this->post(route('warehouses.store'), ['name' => 'Again', 'code' => 'ABJ'])->assertSessionHasErrors('code');

        $this->put(route('warehouses.update', $abuja), ['name' => 'Abuja depot', 'code' => 'ABJ', 'is_active' => 1, 'is_default' => 1])->assertRedirect();
        $this->assertTrue($abuja->fresh()->is_default);
        $this->assertFalse($this->main->fresh()->is_default);
        $this->assertSame(1, Warehouse::where('is_default', true)->count(), 'exactly one default');

        // The default can't be "un-defaulted" or switched off.
        $this->put(route('warehouses.update', $abuja), ['name' => 'Abuja depot', 'code' => 'ABJ', 'is_active' => 1, 'is_default' => 0])->assertSessionHasErrors('is_default');
        $this->put(route('warehouses.update', $abuja), ['name' => 'Abuja depot', 'code' => 'ABJ', 'is_active' => 0, 'is_default' => 1])->assertSessionHasErrors('is_active');
        $this->assertTrue($abuja->fresh()->is_default);
    }

    public function test_a_warehouse_with_stock_or_the_last_one_cannot_be_deleted_or_switched_off(): void
    {
        $item = $this->item();
        $this->buy($item, 3, 1000, $this->kano);

        $this->delete(route('warehouses.destroy', $this->kano))->assertSessionHas('error');
        $this->assertNotNull($this->kano->fresh());
        $this->put(route('warehouses.update', $this->kano), ['name' => 'Kano shop', 'code' => 'KANO', 'is_active' => 0])->assertSessionHasErrors('is_active');
        $this->assertTrue($this->kano->fresh()->is_active);

        $this->delete(route('warehouses.destroy', $this->main))->assertSessionHas('error');
        $this->assertNotNull($this->main->fresh(), 'the default can\'t be deleted');

        // An unused, empty warehouse can go; the last one can't.
        $spare = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Spare', 'code' => 'SPR']);
        $this->delete(route('warehouses.destroy', $spare))->assertSessionHas('success');
        $this->assertNull($spare->fresh());

        DB::table('inventories')->where('tenant_id', $this->tenant->id)->delete();
        DB::table('inventory_layers')->where('tenant_id', $this->tenant->id)->delete();
        DB::table('warehouses')->where('id', $this->kano->id)->delete();
        $this->delete(route('warehouses.destroy', $this->main))->assertSessionHas('error', 'This is your only warehouse, so it can\'t be deleted.');
    }

    public function test_the_warehouse_picker_is_hidden_with_one_warehouse(): void
    {
        $this->get(route('invoices.create'))->assertOk()->assertSee('Sell from warehouse')->assertSee('Kano shop');
        $this->get(route('bills.create'))->assertOk()->assertSee('Receive into warehouse');

        $this->kano->delete();
        $this->get(route('invoices.create'))->assertOk()->assertDontSee('Sell from warehouse');
        $this->get(route('bills.create'))->assertOk()->assertDontSee('Receive into warehouse');
    }

    public function test_the_picker_remembers_the_last_warehouse_used(): void
    {
        $item = $this->item();
        $this->buy($item, 10, 1000, $this->kano);

        $this->post(route('invoices.store'), [
            'customer_id' => $this->customer->id, 'invoice_date' => '2026-10-05', 'due_date' => '2026-11-05', 'warehouse_id' => $this->kano->id,
            'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 1, 'unit_price' => 1500, 'tax_rate' => 0]],
        ])->assertRedirect();

        $this->assertSame($this->kano->id, Invoice::latest('id')->first()->warehouse_id);
        $html = $this->get(route('invoices.create'))->getContent();
        $this->assertMatchesRegularExpression('/<option value="'.$this->kano->id.'"\s+selected/', $html);
    }

    // ---- API ----------------------------------------------------------------

    public function test_the_api_takes_and_returns_a_warehouse(): void
    {
        $item = $this->item();
        $api = $this->actingAs($this->user, 'sanctum');

        $bill = $api->postJson('/api/v1/bills', [
            'vendor_id' => $this->vendor->id, 'bill_date' => '2026-10-01', 'due_date' => '2026-11-01', 'warehouse_id' => $this->kano->id,
            'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 10, 'unit_price' => 1000, 'tax_rate' => 0]],
        ])->assertCreated();
        $this->assertSame($this->kano->id, $bill->json('data.warehouse_id'));
        $this->assertSame(10.0, $this->onHand($item, $this->kano));

        $invoice = $api->postJson('/api/v1/invoices', [
            'customer_id' => $this->customer->id, 'invoice_date' => '2026-10-05', 'due_date' => '2026-11-05', 'warehouse_id' => $this->kano->id,
            'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 2, 'unit_price' => 1500, 'tax_rate' => 0]],
        ])->assertCreated();
        $this->assertSame($this->kano->id, $invoice->json('data.warehouse_id'));
        $this->assertSame(2.0, $this->reserved($item, $this->kano));

        // Left out: the default warehouse.
        $plain = $api->postJson('/api/v1/bills', [
            'vendor_id' => $this->vendor->id, 'bill_date' => '2026-10-01', 'due_date' => '2026-11-01',
            'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0]],
        ])->assertCreated();
        $this->assertSame($this->main->id, $plain->json('data.warehouse_id'));

        $rows = $api->getJson('/api/v1/inventory?warehouse_id='.$this->kano->id)->assertOk();
        $this->assertCount(1, $rows->json('data'));
        $this->assertSame($this->kano->id, $rows->json('data.0.warehouse_id'));

        $list = $api->getJson('/api/v1/warehouses')->assertOk();
        $this->assertSame([$this->main->id, $this->kano->id], array_column($list->json('data'), 'id'));

        $this->assertSame(11.0, (float) $api->getJson('/api/v1/items/'.$item->id)->json('data.inventory.quantity'), 'the item shows its total');
    }

    // ---- tenant isolation ------------------------------------------------------

    public function test_another_businesss_warehouse_cannot_be_used_anywhere(): void
    {
        $other = $this->otherTenant();
        $theirs = Warehouse::withoutGlobalScopes()->where('tenant_id', $other->id)->firstOrFail();
        $item = $this->item();
        $this->buy($item, 10, 1000);

        $this->post(route('invoices.store'), [
            'customer_id' => $this->customer->id, 'invoice_date' => '2026-10-05', 'due_date' => '2026-11-05', 'warehouse_id' => $theirs->id,
            'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 1, 'unit_price' => 1500, 'tax_rate' => 0]],
        ])->assertSessionHasErrors('warehouse_id');
        $this->post(route('bills.store'), [
            'vendor_id' => $this->vendor->id, 'bill_date' => '2026-10-01', 'due_date' => '2026-11-01', 'warehouse_id' => $theirs->id,
            'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0]],
        ])->assertSessionHasErrors('warehouse_id');
        $this->post(route('inventory.adjust', $item), ['type' => 'in', 'quantity' => 1, 'warehouse_id' => $theirs->id])->assertSessionHasErrors('warehouse_id');

        $api = $this->actingAs($this->user, 'sanctum');
        $api->postJson('/api/v1/bills', [
            'vendor_id' => $this->vendor->id, 'bill_date' => '2026-10-01', 'due_date' => '2026-11-01', 'warehouse_id' => $theirs->id,
            'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0]],
        ])->assertStatus(422)->assertJsonValidationErrors('warehouse_id');
        $api->getJson('/api/v1/reports/inventory-summary?warehouse_id='.$theirs->id)->assertStatus(422);
        $this->assertSame([$this->main->id, $this->kano->id], array_column($api->getJson('/api/v1/warehouses')->json('data'), 'id'));

        // The actions refuse it too, whatever calls them.
        $this->expectException(ValidationException::class);
        app(SaveSalesReceipt::class)->create($this->tenant->id, [
            'receipt_date' => '2026-10-07', 'payment_method' => 'cash', 'warehouse_id' => $theirs->id,
            'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 1, 'unit_price' => 1500, 'tax_rate' => 0]],
        ], $this->user->id);
    }

    public function test_another_businesss_warehouse_pages_are_not_found(): void
    {
        $other = $this->otherTenant();
        $theirs = Warehouse::withoutGlobalScopes()->where('tenant_id', $other->id)->firstOrFail();

        $this->get(route('warehouses.show', $theirs))->assertNotFound();
        $this->get(route('warehouses.edit', $theirs))->assertNotFound();
        $this->put(route('warehouses.update', $theirs), ['name' => 'Mine', 'code' => 'X'])->assertNotFound();
        $this->delete(route('warehouses.destroy', $theirs))->assertNotFound();
        $this->assertSame(2, $this->get(route('warehouses.index'))->assertOk()->viewData('warehouses')->total(), 'only our own two');
        $this->assertSame('Main warehouse', $theirs->fresh()->name);
    }

    // ---- switched off, lock dates ------------------------------------------------

    public function test_with_the_module_switched_off_everything_uses_the_default_warehouse(): void
    {
        config(['mybooks.features.warehouses' => false]);
        $item = $this->item();

        $bill = $this->buy($item, 10, 1000, $this->kano);
        $this->assertSame($this->main->id, $bill->warehouse_id, 'a warehouse sent is ignored');
        $this->sellAndRelease($item, 3);
        $this->assertSame(7.0, $this->onHand($item, $this->main));
        $this->assertSame(0.0, $this->onHand($item, $this->kano));

        $this->get(route('warehouses.index'))->assertNotFound();
        $this->get(route('invoices.create'))->assertOk()->assertDontSee('Sell from warehouse');
        $this->get(route('items.show', $item))->assertOk()->assertDontSee('Stock by warehouse');
    }

    public function test_lock_dates_still_apply_to_stock_documents_in_any_warehouse(): void
    {
        app(UpdateLockDates::class)->handle($this->tenant, ['staff_lock_date' => '2026-09-30', 'all_users_lock_date' => null, 'reason' => null]);
        $item = $this->item();

        try {
            $this->buy($item, 5, 1000, $this->kano, '2026-09-15');
            $this->fail('a bill in a locked month');
        } catch (ValidationException) {
        }
        $this->assertSame(0.0, $this->onHand($item));

        $this->buy($item, 5, 1000, $this->kano, '2026-10-02');
        $this->assertSame(5.0, $this->onHand($item, $this->kano));
    }

    public function test_receipts_are_saved_with_their_warehouse(): void
    {
        $item = $this->item();
        $this->buy($item, 5, 1000, $this->kano);
        $receipt = app(SaveSalesReceipt::class)->create($this->tenant->id, [
            'receipt_date' => '2026-10-07', 'payment_method' => 'cash', 'warehouse_id' => $this->kano->id,
            'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 2, 'unit_price' => 1500, 'tax_rate' => 0]],
        ], $this->user->id);

        $this->assertSame($this->kano->id, $receipt->warehouse_id);
        $this->assertSame(3.0, $this->onHand($item, $this->kano));

        // Editing it puts the goods back where they came from first.
        app(SaveSalesReceipt::class)->update($receipt, [
            'receipt_date' => '2026-10-07', 'payment_method' => 'cash',
            'items' => [['item_id' => $item->id, 'description' => 'Rice', 'quantity' => 1, 'unit_price' => 1500, 'tax_rate' => 0]],
        ]);
        $this->assertSame(4.0, $this->onHand($item, $this->kano));
        $this->assertSame($this->kano->id, $receipt->fresh()->warehouse_id);
    }
}
