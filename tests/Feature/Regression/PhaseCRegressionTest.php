<?php

namespace Tests\Feature\Regression;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Journal;
use Tests\TestCase;

/**
 * Round 3, Phase C: one set of rules.
 */
class PhaseCRegressionTest extends TestCase
{
    // ── R2: document numbers ────────────────────────────────────

    public function test_r2_two_saves_at_the_same_moment_get_different_numbers(): void
    {
        [$tenant] = $this->createTenantWithSubscription();

        // Both are asked for a number before either is saved, as when two
        // people press Save together. "Last number + 1" gave both the same.
        $first = Invoice::generateNumber($tenant->id);
        $second = Invoice::generateNumber($tenant->id);
        $this->assertNotSame($first, $second);

        $j1 = Journal::generateNumber($tenant->id);
        $j2 = Journal::generateNumber($tenant->id);
        $this->assertNotSame($j1, $j2);
    }

    public function test_r2_numbering_carries_on_from_existing_numbers_and_survives_odd_imports(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $make = fn (string $number) => Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'invoice_number' => $number,
        ]));

        $make('INV-000041');
        $make('2024/001'); // an imported number in another shape, saved last

        $this->assertSame('INV-000042', Invoice::generateNumber($tenant->id));

        // A number someone typed in by hand is skipped, not reused.
        $make('INV-000043');
        $this->assertSame('INV-000044', Invoice::generateNumber($tenant->id));
    }

    public function test_r2_each_business_has_its_own_sequence(): void
    {
        [$a] = $this->createTenantWithSubscription();
        [$b] = $this->createTenantWithSubscription();

        $this->assertSame('INV-000001', Invoice::generateNumber($a->id));
        $this->assertSame('INV-000001', Invoice::generateNumber($b->id));
        $this->assertSame('INV-000002', Invoice::generateNumber($a->id));
    }

    // ── R3: one set of invoice rules ────────────────────────────

    private function stockedItem(float $onHand): \App\Models\Item
    {
        $item = \App\Models\Item::factory()->create([
            'tenant_id' => $this->tenant->id, 'type' => 'product', 'track_inventory' => true, 'selling_price' => 10000,
        ]);
        \App\Models\Inventory::create(['tenant_id' => $this->tenant->id, 'item_id' => $item->id, 'quantity' => $onHand, 'reserved_quantity' => 0]);

        return $item;
    }

    /** @return array<string, mixed> */
    private function sameSale(Customer $customer, \App\Models\Item $item): array
    {
        return [
            'customer_id' => $customer->id, 'invoice_date' => '2026-09-01', 'due_date' => '2026-10-01',
            'discount_type' => 'fixed', 'discount_amount' => 2000,
            'items' => [
                ['item_id' => $item->id, 'description' => 'Goods', 'quantity' => 2, 'unit_price' => 10000, 'tax_rate' => 7.5],
                ['description' => 'Delivery', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0],
            ],
        ];
    }

    private function reserved(\App\Models\Item $item): float
    {
        return (float) \App\Models\Inventory::where('item_id', $item->id)->value('reserved_quantity');
    }

    public function test_r3_the_same_sale_gives_the_same_invoice_from_every_path(): void
    {
        $this->createAuthenticatedUser(['create invoices', 'view invoices', 'edit sales-orders', 'view sales-orders']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $item = $this->stockedItem(100);
        $sale = $this->sameSale($customer, $item);

        // Web form
        $this->post(route('invoices.store'), $sale)->assertSessionHasNoErrors();
        // API
        $this->postJson('/api/v1/invoices', $sale)->assertCreated();
        // Sales order conversion
        $order = \App\Models\SalesOrder::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'order_number' => 'SO-000001',
            'order_date' => '2026-09-01', 'status' => 'confirmed', 'subtotal' => 25000, 'discount_amount' => 2000, 'total' => 24500,
        ]);
        foreach ($sale['items'] as $line) {
            $order->items()->create($line + ['item_id' => null, 'tax_amount' => 0, 'total' => 0]);
        }
        $order->items()->where('description', 'Goods')->update(['item_id' => $item->id]);
        $this->post(route('sales-orders.convert', $order))->assertSessionHasNoErrors();
        // Recurring invoice
        $profile = \App\Models\RecurrentInvoice::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'profile_name' => 'Monthly', 'frequency' => 'monthly',
            'start_date' => '2026-09-01', 'next_invoice_date' => now()->subDay()->toDateString(), 'payment_terms' => 30,
            'discount_type' => 'fixed', 'discount_amount' => 2000, 'status' => 'active', 'created_by' => $this->user->id,
            'subtotal' => 999, 'tax_amount' => 999, 'total' => 999, // stale figures on the profile are ignored
        ]);
        foreach ($sale['items'] as $line) {
            $profile->items()->create($line + ['item_id' => null, 'tax_amount' => 0, 'total' => 0]);
        }
        $profile->items()->where('description', 'Goods')->update(['item_id' => $item->id]);
        $this->artisan('transactions:process-recurring')->assertSuccessful();

        $invoices = Invoice::orderBy('id')->get();
        $this->assertCount(4, $invoices);
        foreach ($invoices as $invoice) {
            // 25,000 less 2,000 shared 20:5 -> VAT on 18,400 of goods = 1,380.
            $this->assertEqualsWithDelta(25000, (float) $invoice->subtotal, 0.001, "subtotal #{$invoice->id}");
            $this->assertEqualsWithDelta(2000, (float) $invoice->discount_amount, 0.001, "discount #{$invoice->id}");
            $this->assertEqualsWithDelta(1380, (float) $invoice->tax_amount, 0.001, "VAT #{$invoice->id}");
            $this->assertEqualsWithDelta(24380, (float) $invoice->total, 0.001, "total #{$invoice->id}");
        }
        // Every path reserved its 2 units.
        $this->assertEqualsWithDelta(8, $this->reserved($item), 0.001);
    }

    public function test_r3_conversion_and_recurring_invoices_respect_stock(): void
    {
        $this->createAuthenticatedUser(['create invoices', 'view invoices', 'edit sales-orders', 'view sales-orders']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $item = $this->stockedItem(1); // only one on hand, the sale needs two

        $order = \App\Models\SalesOrder::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'order_number' => 'SO-000002',
            'order_date' => '2026-09-01', 'status' => 'confirmed', 'subtotal' => 20000, 'total' => 20000,
        ]);
        $order->items()->create(['item_id' => $item->id, 'description' => 'Goods', 'quantity' => 2, 'unit_price' => 10000, 'tax_rate' => 0, 'tax_amount' => 0, 'total' => 20000]);

        $this->post(route('sales-orders.convert', $order))->assertSessionHasErrors();
        $this->assertSame(0, Invoice::count());
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_r3_delete_rules_are_the_same_on_web_and_api(): void
    {
        $this->createAuthenticatedUser(['create invoices', 'view invoices', 'delete invoices']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::withoutEvents(fn () => Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'status' => 'unpaid']));
        // A refund with no payment record (the old web check looked only at amount paid).
        \App\Models\InvoiceRefund::withoutEvents(fn () => \App\Models\InvoiceRefund::create([
            'tenant_id' => $this->tenant->id, 'invoice_id' => $invoice->id, 'customer_id' => $customer->id,
            'refund_number' => 'REF-1', 'refund_date' => '2026-09-02', 'amount' => 10, 'refund_method' => 'cash', 'status' => 'completed',
        ]));

        $this->delete(route('invoices.destroy', $invoice))->assertSessionHas('error');
        $this->deleteJson('/api/v1/invoices/'.$invoice->id)->assertStatus(422);
        $this->assertNotSoftDeleted($invoice);
    }

    public function test_r3_api_cannot_create_an_invoice_already_paid(): void
    {
        $this->createAuthenticatedUser(['create invoices', 'view invoices']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $sale = $this->sameSale($customer, $this->stockedItem(10));

        $this->postJson('/api/v1/invoices', $sale + ['status' => 'paid'])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    // ── R3: one set of bill rules ───────────────────────────────

    /** @return array<string, mixed> */
    private function samePurchase(\App\Models\Vendor $vendor, \App\Models\Item $item): array
    {
        return [
            'vendor_id' => $vendor->id, 'bill_date' => '2026-09-01', 'due_date' => '2026-10-01',
            'discount_amount' => 2400,
            'items' => [
                ['item_id' => $item->id, 'description' => 'Goods', 'quantity' => 2, 'unit_price' => 10000, 'discount' => 2000, 'tax_rate' => 7.5],
                ['description' => 'Freight', 'quantity' => 1, 'unit_price' => 6000, 'tax_rate' => 0],
            ],
        ];
    }

    private function onHand(\App\Models\Item $item): float
    {
        return (float) \App\Models\Inventory::where('item_id', $item->id)->value('quantity');
    }

    public function test_r3_the_same_purchase_gives_the_same_bill_on_web_and_api(): void
    {
        $this->createAuthenticatedUser(['create bills', 'view bills']);
        $vendor = \App\Models\Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $item = $this->stockedItem(0);
        $purchase = $this->samePurchase($vendor, $item);

        $this->post(route('bills.store'), $purchase)->assertSessionHasNoErrors();
        $this->postJson('/api/v1/bills', $purchase)->assertCreated();

        $bills = \App\Models\Bill::orderBy('id')->get();
        $this->assertCount(2, $bills);
        foreach ($bills as $bill) {
            // Goods 20,000 less 2,000 = 18,000; freight 6,000. The 2,400 bill
            // discount is shared 3:1, so goods cost 16,200 and VAT is 1,215.
            $this->assertEqualsWithDelta(26000, (float) $bill->subtotal, 0.001, "subtotal #{$bill->id}");
            $this->assertEqualsWithDelta(4400, (float) $bill->discount_amount, 0.001, "discount #{$bill->id}");
            $this->assertEqualsWithDelta(1215, (float) $bill->tax_amount, 0.001, "VAT #{$bill->id}");
            $this->assertEqualsWithDelta(22815, (float) $bill->total, 0.001, "total #{$bill->id}");
            $this->assertEqualsWithDelta(2000, (float) $bill->items()->where('description', 'Goods')->value('discount'), 0.001);

            $journal = Journal::where('reference_type', \App\Models\Bill::class)->where('reference_id', $bill->id)->firstOrFail();
            $this->assertEqualsWithDelta(22815, (float) $journal->entries()->sum('credit'), 0.001, "journal #{$bill->id}");
            $this->assertEqualsWithDelta((float) $journal->entries()->sum('debit'), (float) $journal->entries()->sum('credit'), 0.001);
        }
        // Both bills received their 2 units.
        $this->assertEqualsWithDelta(4, $this->onHand($item), 0.001);
    }

    public function test_r3_api_cannot_create_a_bill_already_paid(): void
    {
        $this->createAuthenticatedUser(['create bills', 'view bills']);
        $vendor = \App\Models\Vendor::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson('/api/v1/bills', $this->samePurchase($vendor, $this->stockedItem(0)) + ['status' => 'paid'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
        $this->assertSame(0, \App\Models\Bill::count());
    }

    public function test_r3_deleting_a_bill_takes_its_unsold_goods_back_out_of_stock(): void
    {
        $this->createAuthenticatedUser(['create bills', 'view bills', 'delete bills']);
        $vendor = \App\Models\Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $item = $this->stockedItem(0);

        $this->postJson('/api/v1/bills', $this->samePurchase($vendor, $item))->assertCreated();
        $bill = \App\Models\Bill::firstOrFail();
        $this->assertEqualsWithDelta(2, $this->onHand($item), 0.001);

        $this->delete(route('bills.destroy', $bill))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($bill);
        $this->assertEqualsWithDelta(0, $this->onHand($item), 0.001);
    }

    public function test_r3_a_bill_whose_goods_were_sold_cannot_be_deleted(): void
    {
        $this->createAuthenticatedUser(['create bills', 'view bills', 'delete bills']);
        $vendor = \App\Models\Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $item = $this->stockedItem(0);

        $this->postJson('/api/v1/bills', $this->samePurchase($vendor, $item))->assertCreated();
        $bill = \App\Models\Bill::firstOrFail();
        // One of the two units has since been sold.
        \App\Models\InventoryLayer::where('reference_type', 'bill')->where('reference_id', $bill->id)->update(['remaining_quantity' => 1]);

        $this->deleteJson('/api/v1/bills/'.$bill->id)->assertStatus(422);
        $this->delete(route('bills.destroy', $bill))->assertSessionHas('error');
        $this->assertNotSoftDeleted($bill);
    }

    public function test_r3_goods_already_in_stock_cannot_be_changed_on_the_bill(): void
    {
        $this->createAuthenticatedUser(['create bills', 'view bills', 'edit bills']);
        $vendor = \App\Models\Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $item = $this->stockedItem(0);
        $purchase = $this->samePurchase($vendor, $item);

        $this->postJson('/api/v1/bills', $purchase)->assertCreated();
        $bill = \App\Models\Bill::firstOrFail();

        $purchase['items'][0]['quantity'] = 3;
        $this->putJson('/api/v1/bills/'.$bill->id, $purchase)->assertStatus(422);
        $this->assertEqualsWithDelta(2, (float) $bill->items()->where('item_id', $item->id)->value('quantity'), 0.001);

        // A price change on the same goods is fine.
        $purchase['items'][0]['quantity'] = 2;
        $purchase['items'][1]['unit_price'] = 7000;
        $this->putJson('/api/v1/bills/'.$bill->id, $purchase)->assertOk();
        // 2,400 now shared 18,000:7,000, so goods cost 16,272 and VAT is 1,220.40.
        $this->assertEqualsWithDelta(27000 - 4400 + 1220.40, (float) $bill->fresh()->total, 0.001);
    }

    public function test_r3_recurring_bills_work_out_totals_from_their_lines(): void
    {
        $this->createAuthenticatedUser();
        $vendor = \App\Models\Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $item = $this->stockedItem(0);
        $profile = \App\Models\RecurrentBill::create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'profile_name' => 'Monthly stock', 'frequency' => 'monthly',
            'start_date' => '2026-09-01', 'next_bill_date' => now()->subDay()->toDateString(), 'status' => 'active',
            'created_by' => $this->user->id, 'subtotal' => 999, 'tax_amount' => 999, 'total' => 999, // stale
        ]);
        $profile->items()->create(['item_id' => $item->id, 'description' => 'Goods', 'quantity' => 2, 'unit_price' => 10000, 'tax_rate' => 7.5, 'tax_amount' => 0, 'total' => 0]);

        $this->artisan('transactions:process-recurring')->assertSuccessful();

        $bill = \App\Models\Bill::firstOrFail();
        $this->assertEqualsWithDelta(21500, (float) $bill->total, 0.001);
        $this->assertEqualsWithDelta(1500, (float) $bill->items()->value('tax_amount'), 0.001);
        $this->assertEqualsWithDelta(2, $this->onHand($item), 0.001);
    }

    // ── R3: one set of sales order rules ────────────────────────

    /** @return array<string, mixed> */
    private function sameOrder(Customer $customer): array
    {
        return [
            'customer_id' => $customer->id, 'order_date' => '2026-09-01',
            'discount_type' => 'fixed', 'discount_amount' => 2400,
            'items' => [
                ['description' => 'Goods', 'quantity' => 2, 'unit_price' => 10000, 'discount' => 2000, 'tax_rate' => 7.5],
                ['description' => 'Delivery', 'quantity' => 1, 'unit_price' => 6000, 'tax_rate' => 0],
            ],
        ];
    }

    private function assertOrderFigures(object $doc, string $label): void
    {
        // Goods 18,000 after the line discount, delivery 6,000; the 2,400
        // discount is shared 3:1, so VAT is 7.5% of 16,200 = 1,215.
        $this->assertEqualsWithDelta(24000, (float) $doc->subtotal, 0.001, "subtotal {$label}");
        $this->assertEqualsWithDelta(2400, (float) $doc->discount_amount, 0.001, "discount {$label}");
        $this->assertEqualsWithDelta(1215, (float) $doc->tax_amount, 0.001, "VAT {$label}");
        $this->assertEqualsWithDelta(22815, (float) $doc->total, 0.001, "total {$label}");
    }

    public function test_r3_the_same_order_gives_the_same_sales_order_on_web_and_api(): void
    {
        $this->createAuthenticatedUser(['create sales-orders', 'view sales-orders']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->post(route('sales-orders.store'), $this->sameOrder($customer))->assertSessionHasNoErrors();
        $this->postJson('/api/v1/sales-orders', $this->sameOrder($customer))->assertCreated();

        $orders = \App\Models\SalesOrder::orderBy('id')->get();
        $this->assertCount(2, $orders);
        foreach ($orders as $order) {
            $this->assertOrderFigures($order, "#{$order->id}");
        }
    }

    public function test_r3_saving_the_sales_order_edit_form_saves_the_changes(): void
    {
        $this->createAuthenticatedUser(['create sales-orders', 'edit sales-orders', 'view sales-orders']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->post(route('sales-orders.store'), $this->sameOrder($customer));
        $order = \App\Models\SalesOrder::firstOrFail();

        $changed = $this->sameOrder($customer);
        $changed['items'][1]['quantity'] = 2;
        $changed['notes'] = 'Two deliveries';
        $this->put(route('sales-orders.update', $order), $changed)->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('Two deliveries', $order->notes);
        $this->assertEqualsWithDelta(2, (float) $order->items()->where('description', 'Delivery')->value('quantity'), 0.001);
    }

    public function test_r3_api_cannot_create_or_jump_a_sales_order_to_completed(): void
    {
        $this->createAuthenticatedUser(['create sales-orders', 'edit sales-orders', 'view sales-orders']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->postJson('/api/v1/sales-orders', $this->sameOrder($customer) + ['status' => 'completed'])
            ->assertStatus(422)->assertJsonValidationErrors('status');

        $this->postJson('/api/v1/sales-orders', $this->sameOrder($customer))->assertCreated();
        $order = \App\Models\SalesOrder::firstOrFail();
        $this->putJson('/api/v1/sales-orders/'.$order->id, ['status' => 'completed'])->assertStatus(422);
        $this->putJson('/api/v1/sales-orders/'.$order->id, ['status' => 'confirmed'])->assertOk();
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_r3_an_invoiced_sales_order_cannot_be_deleted_from_the_web(): void
    {
        $this->createAuthenticatedUser(['create sales-orders', 'view sales-orders', 'delete sales-orders']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->post(route('sales-orders.store'), $this->sameOrder($customer));
        $order = \App\Models\SalesOrder::firstOrFail();
        Invoice::withoutEvents(fn () => Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'sales_order_id' => $order->id]));

        $this->delete(route('sales-orders.destroy', $order))->assertSessionHas('error');
        $this->assertNotSoftDeleted($order);
    }

    public function test_r3_quotation_to_order_to_invoice_keeps_the_same_figures(): void
    {
        config(['mybooks.features.quotations' => true]);
        $this->createAuthenticatedUser(['edit invoices', 'create sales-orders', 'edit sales-orders', 'view sales-orders', 'create invoices', 'view invoices']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $quote = $this->sameOrder($customer);
        unset($quote['order_date']);
        $quote['quotation_date'] = '2026-09-01';

        $this->post(route('quotations.store'), $quote)->assertSessionHasNoErrors();
        $quotation = \App\Models\Quotation::firstOrFail();
        $this->assertOrderFigures($quotation, 'quotation');

        $this->post(route('quotations.convert', $quotation))->assertSessionHasNoErrors();
        $order = \App\Models\SalesOrder::firstOrFail();
        $this->assertOrderFigures($order, 'order');

        $order->update(['status' => 'confirmed']);
        $this->post(route('sales-orders.convert', $order))->assertSessionHasNoErrors();
        $this->assertOrderFigures(Invoice::firstOrFail(), 'invoice');
    }

    public function test_r3_billing_a_purchase_order_keeps_its_line_discounts(): void
    {
        $this->createAuthenticatedUser(['create bills', 'view bills']);
        $vendor = \App\Models\Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = \App\Models\PurchaseOrder::create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'order_number' => 'PO-000001',
            'order_date' => '2026-09-01', 'status' => 'confirmed', 'subtotal' => 20000, 'discount_amount' => 1000, 'total' => 19000,
        ]);
        $order->items()->create(['item_id' => $this->stockedItem(0)->id, 'description' => 'Paper', 'quantity' => 4, 'unit_price' => 5000, 'discount' => 1000, 'tax_rate' => 0, 'tax_amount' => 0, 'total' => 19000]);

        $prefill = $this->get(route('bills.create', ['purchase_order_id' => $order->id]))->assertOk()->viewData('prefillItems');
        $this->assertEqualsWithDelta(1000, $prefill[0]['discount'], 0.001);
        $this->assertStringContainsString('items[${index}][discount]', file_get_contents(resource_path('views/bills/create.blade.php')));
    }

    // ── R3: cash sales ──────────────────────────────────────────

    public function test_r3_a_cash_sale_charges_vat_like_an_invoice(): void
    {
        $this->createAuthenticatedUser(['create sales-receipts', 'edit sales-receipts', 'view sales-receipts']);
        $item = $this->stockedItem(10);
        $sale = [
            'receipt_date' => '2026-09-01', 'payment_method' => 'cash',
            'items' => [['item_id' => $item->id, 'description' => 'Goods', 'quantity' => 2, 'unit_price' => 10000, 'tax_rate' => 7.5]],
        ];

        $this->post(route('sales-receipts.store'), $sale)->assertSessionHasNoErrors();

        $receipt = \App\Models\SalesReceipt::firstOrFail();
        $this->assertEqualsWithDelta(1500, (float) $receipt->tax_amount, 0.001);
        $this->assertEqualsWithDelta(21500, (float) $receipt->total, 0.001);
        $journal = Journal::where('reference_type', \App\Models\SalesReceipt::class)->where('reference_id', $receipt->id)->firstOrFail();
        $vat = \App\Services\AccountCodeService::resolve($this->tenant->id, 'sales_tax_payable');
        $this->assertEqualsWithDelta(1500, (float) $journal->entries()->whereHas('account', fn ($q) => $q->where('account_code', $vat))->sum('credit'), 0.001);
        $this->assertEqualsWithDelta(8, $this->onHand($item), 0.001);

        // Editing keeps VAT and moves stock by the difference only.
        $sale['items'][0]['quantity'] = 3;
        $this->put(route('sales-receipts.update', $receipt), $sale)->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(32250, (float) $receipt->fresh()->total, 0.001);
        $this->assertEqualsWithDelta(7, $this->onHand($item), 0.001);
    }

    // ── R3: payments ────────────────────────────────────────────

    public function test_r3_api_payments_move_the_bank_balance_like_the_web(): void
    {
        $this->createAuthenticatedUser(['create payments-received', 'delete payments-received', 'create payments-made', 'delete payments-made']);
        $bank = \App\Models\Bank::factory()->create(['tenant_id' => $this->tenant->id, 'current_balance' => 100000]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $vendor = \App\Models\Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $balance = fn () => (float) $bank->fresh()->current_balance;

        $this->postJson('/api/v1/payments-received', [
            'customer_id' => $customer->id, 'payment_date' => '2026-09-01', 'amount' => 5000,
            'payment_method' => 'bank_transfer', 'bank_id' => $bank->id, 'is_deposit' => true,
        ])->assertCreated();
        $this->assertEqualsWithDelta(105000, $balance(), 0.001);

        $this->postJson('/api/v1/payments-made', [
            'vendor_id' => $vendor->id, 'payment_date' => '2026-09-01', 'amount' => 2000,
            'payment_method' => 'bank_transfer', 'bank_id' => $bank->id,
        ])->assertCreated();
        $this->assertEqualsWithDelta(103000, $balance(), 0.001);

        $this->deleteJson('/api/v1/payments-made/'.\App\Models\PaymentMade::firstOrFail()->id)->assertOk();
        $this->assertEqualsWithDelta(105000, $balance(), 0.001);

        $this->deleteJson('/api/v1/payments-received/'.\App\Models\PaymentReceived::firstOrFail()->id)->assertOk();
        $this->assertEqualsWithDelta(100000, $balance(), 0.001);
    }

    public function test_r3_api_cannot_delete_a_deposit_already_applied(): void
    {
        $this->createAuthenticatedUser(['create payments-received', 'delete payments-received']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'status' => 'unpaid', 'subtotal' => 3000, 'tax_amount' => 0, 'discount_amount' => 0, 'total' => 3000, 'balance_due' => 3000, 'amount_paid' => 0]);

        $this->postJson('/api/v1/payments-received', [
            'customer_id' => $customer->id, 'payment_date' => '2026-09-01', 'amount' => 5000, 'payment_method' => 'cash', 'is_deposit' => true,
        ])->assertCreated();
        $deposit = \App\Models\PaymentReceived::firstOrFail();
        $deposit->applyToInvoice($invoice, 3000);

        $this->deleteJson('/api/v1/payments-received/'.$deposit->id)->assertStatus(422);
        $this->assertNotSoftDeleted($deposit);
    }

    // ── Q2: money ───────────────────────────────────────────────

    public function test_q2_money_rounds_and_adds_in_whole_kobo(): void
    {
        $this->assertSame(1.01, \App\Support\Money::round(1.005));
        $this->assertSame(0.6, \App\Support\Money::sum([0.1, 0.2, 0.3]));
        $this->assertSame([33.33, 33.33, 33.34], \App\Support\Money::allocate(100, [1, 1, 1]));
        $this->assertTrue(\App\Support\Money::equals(0.1 + 0.2, 0.3));
    }

    public function test_q2_every_document_keeps_its_total_equal_to_its_parts(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $vendor = \App\Models\Vendor::factory()->create(['tenant_id' => $tenant->id]);
        // 3.71 + 0.28 stored as 3.98: a kobo out, as unrounded sums used to give.
        $figures = ['subtotal' => 3.71, 'tax_amount' => 0.28, 'total' => 3.98];

        $docs = [
            \App\Models\SalesOrder::create($figures + ['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'order_number' => 'SO-9', 'order_date' => '2026-09-01', 'status' => 'draft']),
            \App\Models\Quotation::create($figures + ['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'quotation_number' => 'QT-9', 'quotation_date' => '2026-09-01', 'status' => 'draft']),
            \App\Models\PurchaseOrder::create($figures + ['tenant_id' => $tenant->id, 'vendor_id' => $vendor->id, 'order_number' => 'PO-9', 'order_date' => '2026-09-01', 'status' => 'draft']),
            \App\Models\CreditNote::create($figures + ['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'credit_note_number' => 'CN-9', 'credit_note_date' => '2026-09-01', 'status' => 'draft']),
            \App\Models\RecurrentBill::create($figures + ['tenant_id' => $tenant->id, 'vendor_id' => $vendor->id, 'profile_name' => 'R', 'frequency' => 'monthly', 'start_date' => '2026-09-01', 'next_bill_date' => '2026-10-01', 'status' => 'active']),
        ];

        foreach ($docs as $doc) {
            $this->assertEqualsWithDelta(3.99, (float) $doc->fresh()->total, 0.0001, class_basename($doc));
        }
    }
}
