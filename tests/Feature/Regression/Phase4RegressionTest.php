<?php

namespace Tests\Feature\Regression;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryLayer;
use App\Models\InventoryLayerConsumption;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\PaymentReceived;
use App\Models\SalesReceipt;
use App\Models\Vendor;
use App\Services\StockValuationService;
use Tests\Support\AssertsLedger;
use Tests\TestCase;

/**
 * Phase 4: cost of goods sold is fixed once per line (M3) and FIFO really
 * uses up cost layers in order (N6).
 */
class Phase4RegressionTest extends TestCase
{
    use AssertsLedger;

    private Customer $customer;

    private int $invoiceSeq = 100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /** An item with two purchase lots: 5 @ 10 then 5 @ 20. */
    private function stockItem(string $method): Item
    {
        $item = Item::factory()->create([
            'tenant_id' => $this->tenant->id, 'type' => 'product', 'track_inventory' => true,
            'valuation_method' => $method, 'cost_price' => 99,
        ]);
        Inventory::create(['tenant_id' => $this->tenant->id, 'item_id' => $item->id, 'quantity' => 10, 'reserved_quantity' => 0]);

        $service = app(StockValuationService::class);
        $service->addLayer($this->tenant->id, $item->id, 5, 10);
        InventoryLayer::latest('id')->first()->update(['received_date' => now()->subDays(2)]);
        $service->addLayer($this->tenant->id, $item->id, 5, 20);

        return $item;
    }

    /** A posted (sent) invoice selling $quantity of $item. */
    private function sell(Item $item, float $quantity, string $status = 'sent'): Invoice
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-'.str_pad((string) $this->invoiceSeq++, 6, '0', STR_PAD_LEFT),
        ]);
        $invoice->items()->create([
            'item_id' => $item->id, 'description' => $item->name, 'quantity' => $quantity,
            'unit_price' => 50, 'tax_rate' => 0, 'tax_amount' => 0, 'total' => 50 * $quantity,
        ]);
        $invoice->update(['status' => $status]);

        return $invoice->fresh();
    }

    private function remainingLayers(Item $item): array
    {
        return InventoryLayer::where('item_id', $item->id)->orderBy('received_date')->orderBy('id')
            ->pluck('remaining_quantity')->map(fn ($q) => (float) $q)->all();
    }

    private function cogs(): float
    {
        return $this->accountBalance($this->tenant->id, '5000');
    }

    // ── N6: FIFO really uses up layers ──────────────────────────

    public function test_n6_fifo_sales_use_the_oldest_lots_first(): void
    {
        $item = $this->stockItem('fifo');

        $this->sell($item, 4);                      // 4 @ 10
        $this->assertSame(40.0, $this->cogs());
        $this->assertSame([1.0, 5.0], $this->remainingLayers($item));

        $this->sell($item, 3);                      // 1 @ 10 + 2 @ 20
        $this->assertSame(90.0, $this->cogs());
        $this->assertSame([0.0, 3.0], $this->remainingLayers($item));

        $this->assertStoredBalancesMatchLedger($this->tenant->id);
        $this->assertAllJournalsBalance($this->tenant->id);
    }

    public function test_n6_fifo_beyond_recorded_lots_uses_the_items_cost_price(): void
    {
        $item = $this->stockItem('fifo');

        $this->sell($item, 12);                     // 5@10 + 5@20 + 2@99 (cost price)

        $this->assertSame(348.0, $this->cogs());
        $this->assertSame([0.0, 0.0], $this->remainingLayers($item));
    }

    public function test_weighted_average_uses_the_average_and_keeps_it(): void
    {
        $item = $this->stockItem('weighted_average');
        $service = app(StockValuationService::class);

        $this->sell($item, 4);                      // average 15

        $this->assertSame(60.0, $this->cogs());
        $this->assertEqualsWithDelta(6.0, array_sum($this->remainingLayers($item)), 0.0001);
        $this->assertEqualsWithDelta(15.0, $service->getWeightedAverageCost($item), 0.0001);
    }

    // ── M3: cost is decided once ────────────────────────────────

    public function test_m3_later_price_changes_do_not_rewrite_past_cogs(): void
    {
        $item = $this->stockItem('fifo');
        $invoice = $this->sell($item, 4);
        $this->assertSame(40.0, $this->cogs());
        $this->assertEquals(10.0, (float) $invoice->items()->first()->unit_cost);

        // Cost price changes and a dearer lot arrives, then a payment rebuilds the journal
        $item->update(['cost_price' => 500]);
        app(StockValuationService::class)->addLayer($this->tenant->id, $item->id, 10, 70);
        PaymentReceived::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id, 'invoice_id' => $invoice->id,
            'payment_number' => 'PR-000100', 'payment_date' => now(), 'amount' => 100, 'payment_method' => 'cash',
            'is_deposit' => false, 'unused_amount' => 0, 'created_by' => $this->user->id,
        ]);

        $this->assertSame(40.0, $this->cogs());
        $this->assertSame([1.0, 5.0, 10.0], $this->remainingLayers($item));
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }

    public function test_m3_editing_the_lines_returns_the_old_stock_before_recosting(): void
    {
        $item = $this->stockItem('fifo');
        $invoice = $this->sell($item, 4);

        // Same pattern as the invoice edit screen: delete and recreate lines, then save totals
        $invoice->items()->delete();
        $invoice->items()->create([
            'item_id' => $item->id, 'description' => $item->name, 'quantity' => 2,
            'unit_price' => 50, 'tax_rate' => 0, 'tax_amount' => 0, 'total' => 100,
        ]);
        $invoice->update(['subtotal' => 100, 'tax_amount' => 0, 'total' => 100, 'balance_due' => 100]);

        $this->assertSame(20.0, $this->cogs());
        $this->assertSame([3.0, 5.0], $this->remainingLayers($item));
        $this->assertSame(1, InventoryLayerConsumption::where('source_id', $invoice->id)->count());
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }

    public function test_draft_invoices_take_no_stock(): void
    {
        $item = $this->stockItem('fifo');

        $this->sell($item, 4, 'draft');

        $this->assertSame([5.0, 5.0], $this->remainingLayers($item));
        $this->assertSame(0, InventoryLayerConsumption::count());
    }

    public function test_cancelling_an_invoice_puts_its_stock_back(): void
    {
        $item = $this->stockItem('fifo');
        $invoice = $this->sell($item, 7);
        $this->assertSame([0.0, 3.0], $this->remainingLayers($item));

        $invoice->update(['status' => 'cancelled']);

        $this->assertSame([5.0, 5.0], $this->remainingLayers($item));
        $this->assertSame(0.0, $this->cogs());
        $this->assertSame(0, InventoryLayerConsumption::count());
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }

    public function test_deleting_an_invoice_puts_its_stock_back(): void
    {
        $item = $this->stockItem('fifo');
        $invoice = $this->sell($item, 7);

        $invoice->delete();

        $this->assertSame([5.0, 5.0], $this->remainingLayers($item));
        $this->assertSame(0.0, $this->cogs());
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }

    // ── Cash sales lower stock on hand ──────────────────────────

    private function cashSale(Item $item, float $quantity): SalesReceipt
    {
        $receipt = SalesReceipt::create([
            'tenant_id' => $this->tenant->id, 'receipt_number' => 'SR-'.random_int(100000, 999999),
            'receipt_date' => now(), 'payment_method' => 'cash', 'created_by' => $this->user->id,
        ]);
        $receipt->items()->create([
            'item_id' => $item->id, 'description' => $item->name, 'quantity' => $quantity,
            'unit_price' => 50, 'total' => 50 * $quantity,
        ]);
        $receipt->update(['subtotal' => 50 * $quantity, 'total' => 50 * $quantity]);

        return $receipt->fresh();
    }

    public function test_a_cash_sale_lowers_stock_on_hand_and_uses_the_lots(): void
    {
        $item = $this->stockItem('fifo');

        $receipt = $this->cashSale($item, 6);        // 5 @ 10 + 1 @ 20

        $this->assertEquals(4, (float) Inventory::where('item_id', $item->id)->value('quantity'));
        $this->assertSame([0.0, 4.0], $this->remainingLayers($item));
        $this->assertSame(70.0, $this->cogs());
        $this->assertStoredBalancesMatchLedger($this->tenant->id);

        $receipt->delete();

        $this->assertEquals(10, (float) Inventory::where('item_id', $item->id)->value('quantity'));
        $this->assertSame([5.0, 5.0], $this->remainingLayers($item));
        $this->assertSame(0.0, $this->cogs());
    }

    // ── Bill lots are costed without VAT ────────────────────────

    public function test_bill_lots_are_costed_without_vat(): void
    {
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $item = Item::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'product', 'track_inventory' => true]);
        $bill = Bill::factory()->create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'status' => 'unpaid', 'bill_number' => 'BIL-000100',
            'subtotal' => 1000, 'tax_amount' => 75, 'discount_amount' => 0, 'total' => 1075, 'balance_due' => 1075,
        ]);
        $bill->items()->create([
            'item_id' => $item->id, 'description' => 'Stock', 'quantity' => 10,
            'unit_price' => 100, 'tax_rate' => 7.5, 'tax_amount' => 75, 'total' => 1075,
        ]);

        $bill->updateInventory();

        $this->assertEquals(100.0, (float) InventoryLayer::where('item_id', $item->id)->value('unit_cost'));
    }

    public function test_a_cash_sale_cannot_sell_more_than_is_available(): void
    {
        $this->user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('create sales-receipts', 'web'));
        $item = $this->stockItem('fifo');
        $form = fn ($qty) => [
            'receipt_date' => now()->toDateString(), 'payment_method' => 'cash',
            'items' => [['item_id' => $item->id, 'description' => $item->name, 'quantity' => $qty, 'unit_price' => 50]],
        ];

        $this->post(route('sales-receipts.store'), $form(11))->assertSessionHasErrors('items.0.quantity');
        $this->assertSame(0, SalesReceipt::count());

        $this->post(route('sales-receipts.store'), $form(10))->assertSessionHasNoErrors();
        $this->assertEquals(0, (float) Inventory::where('item_id', $item->id)->value('quantity'));
    }

    public function test_editing_a_cash_sale_can_reuse_its_own_stock(): void
    {
        $this->user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('edit sales-receipts', 'web'));
        $item = $this->stockItem('fifo');
        $receipt = $this->cashSale($item, 10);
        $this->assertEquals(0, (float) Inventory::where('item_id', $item->id)->value('quantity'));

        $this->put(route('sales-receipts.update', $receipt), [
            'receipt_date' => now()->toDateString(), 'payment_method' => 'cash',
            'items' => [['item_id' => $item->id, 'description' => $item->name, 'quantity' => 8, 'unit_price' => 50]],
        ])->assertSessionHasNoErrors();

        $this->assertEquals(2, (float) Inventory::where('item_id', $item->id)->value('quantity'));
        $this->assertSame([0.0, 2.0], $this->remainingLayers($item));
        $this->assertSame(110.0, $this->cogs());   // 5 @ 10 + 3 @ 20
        $this->assertStoredBalancesMatchLedger($this->tenant->id);
    }

    public function test_status_values_added_for_mysql_also_exist_on_other_drivers(): void
    {
        $item = $this->stockItem('fifo');
        foreach (['reserved', 'unreserved'] as $type) {
            \App\Models\InventoryHistory::create([
                'tenant_id' => $this->tenant->id, 'item_id' => $item->id, 'type' => $type, 'quantity' => 1,
            ]);
        }

        \Illuminate\Support\Facades\DB::table('sales_orders')->insert([
            'tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id, 'order_number' => 'SO-000001',
            'order_date' => now()->toDateString(), 'status' => 'invoiced', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(2, \App\Models\InventoryHistory::whereIn('type', ['reserved', 'unreserved'])->count());
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('sales_orders')->where('status', 'invoiced')->count());
    }
}
