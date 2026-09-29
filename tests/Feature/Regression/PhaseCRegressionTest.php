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
}
