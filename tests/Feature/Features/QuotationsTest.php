<?php

namespace Tests\Feature\Features;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Notifications\QuotationSentNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Quotations (estimates): create, edit, email with a PDF, accept/reject,
 * expiry, and conversion to a sales order or an invoice.
 */
class QuotationsTest extends TestCase
{
    private const PERMISSIONS = ['view invoices', 'create invoices', 'edit invoices', 'delete invoices', 'send invoices',
        'create sales-orders', 'view sales-orders', 'view customers'];

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser(self::PERMISSIONS);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'buyer@example.com']);
    }

    /** 100,000 of goods, 10% discount, 7.5% VAT: 96,750 (the DocumentTotals example). */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'quotation_date' => now()->toDateString(),
            'expiry_date' => now()->addDays(30)->toDateString(),
            'reference' => 'RFQ-7',
            'discount_type' => 'percentage',
            'discount_amount' => 10,
            'items' => [
                ['description' => 'Bags of cement', 'quantity' => 20, 'unit_price' => 4000, 'tax_rate' => 7.5],
                ['description' => 'Delivery', 'quantity' => 1, 'unit_price' => 20000, 'tax_rate' => 7.5],
            ],
        ], $overrides);
    }

    private function quotation(array $overrides = []): Quotation
    {
        $this->post(route('quotations.store'), $this->payload($overrides))->assertSessionHasNoErrors();

        return Quotation::latest('id')->firstOrFail();
    }

    public function test_quotation_is_saved_with_invoice_style_totals_and_its_pages_open(): void
    {
        $quotation = $this->quotation();

        $this->assertSame('draft', $quotation->status);
        $this->assertSame('QTN-000001', $quotation->quotation_number);
        $this->assertSame(100000.0, (float) $quotation->subtotal);
        $this->assertSame(10000.0, (float) $quotation->discount_amount);
        $this->assertSame(6750.0, (float) $quotation->tax_amount);
        $this->assertSame(96750.0, (float) $quotation->total);
        $this->assertCount(2, $quotation->items);

        $this->get(route('quotations.index'))->assertOk()->assertSee('Quotations');
        $this->get(route('quotations.create'))->assertOk()->assertSee('New quotation');
        $this->get(route('quotations.show', $quotation))->assertOk()->assertSee('QTN-000001')->assertSee('Convert to invoice');
        $this->get(route('quotations.edit', $quotation))->assertOk()->assertSee('Bags of cement');
        $this->get(route('quotations.print', $quotation))->assertOk()->assertSee('QUOTATION')->assertSee('96,750.00');
        $this->get(route('quotations.pdf', $quotation))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_editing_changes_the_lines_and_reopens_a_rejected_quotation(): void
    {
        $quotation = $this->quotation();
        $this->post(route('quotations.reject', $quotation))->assertSessionHas('success');
        $this->assertSame('rejected', $quotation->fresh()->status);

        $this->put(route('quotations.update', $quotation), $this->payload([
            'discount_type' => '', 'discount_amount' => 0,
            'items' => [['description' => 'Bags of cement', 'quantity' => 10, 'unit_price' => 4000, 'tax_rate' => 7.5]],
        ]))->assertSessionHasNoErrors();

        $quotation->refresh();
        $this->assertSame('draft', $quotation->status);
        $this->assertSame(43000.0, (float) $quotation->total);
        $this->assertCount(1, $quotation->items);
    }

    public function test_quotation_is_emailed_with_a_pdf_and_marked_sent(): void
    {
        Notification::fake();
        $quotation = $this->quotation();

        $this->post(route('quotations.send', $quotation), ['message' => 'As discussed'])->assertSessionHas('success');

        $quotation->refresh();
        $this->assertSame('sent', $quotation->status);
        $this->assertNotNull($quotation->sent_at);
        Notification::assertSentTo($this->customer, QuotationSentNotification::class, function ($notification) {
            $mail = $notification->toMail($this->customer);
            $this->assertSame('quotation-QTN-000001.pdf', $mail->rawAttachments[0]['name']);
            $this->assertStringStartsWith('%PDF', $mail->rawAttachments[0]['data']);

            return true;
        });
    }

    public function test_a_customer_without_email_is_refused_but_can_be_marked_sent(): void
    {
        Notification::fake();
        $this->customer->update(['email' => null]);
        $quotation = $this->quotation();

        $this->post(route('quotations.send', $quotation))->assertSessionHas('error');
        $this->assertSame('draft', $quotation->fresh()->status);
        Notification::assertNothingSent();

        $this->post(route('quotations.mark-sent', $quotation))->assertSessionHas('success');
        $this->assertSame('sent', $quotation->fresh()->status);
    }

    public function test_expired_quotations_are_marked_by_the_daily_command(): void
    {
        $old = $this->quotation(['quotation_date' => now()->subDays(40)->toDateString(), 'expiry_date' => now()->subDay()->toDateString()]);
        $current = $this->quotation();
        $accepted = $this->quotation(['quotation_date' => now()->subDays(40)->toDateString(), 'expiry_date' => now()->subDay()->toDateString()]);
        $this->post(route('quotations.accept', $accepted));

        $this->artisan('quotations:expire')->assertSuccessful();

        $this->assertSame('expired', $old->fresh()->status);
        $this->assertSame('draft', $current->fresh()->status);
        $this->assertSame('accepted', $accepted->fresh()->status);

        // An expired quotation is not converted until it is renewed or accepted
        $this->post(route('quotations.convert', $old))->assertSessionHas('error');
        $this->assertSame(0, SalesOrder::count());
    }

    public function test_converting_to_a_sales_order_keeps_the_figures(): void
    {
        $quotation = $this->quotation();
        $this->post(route('quotations.accept', $quotation));

        $this->post(route('quotations.convert', $quotation))->assertRedirect();

        $order = SalesOrder::firstOrFail();
        $quotation->refresh();
        $this->assertSame('converted', $quotation->status);
        $this->assertSame($order->id, $quotation->converted_to_so_id);
        $this->assertSame(96750.0, (float) $order->total);
        $this->assertSame(6750.0, (float) $order->tax_amount);

        // Converted is final
        $this->post(route('quotations.reject', $quotation))->assertSessionHas('error');
        $this->get(route('quotations.edit', $quotation))->assertRedirect(route('quotations.show', $quotation));
        $this->delete(route('quotations.destroy', $quotation))->assertSessionHas('error');
        $this->assertNotSoftDeleted($quotation);
    }

    public function test_converting_to_an_invoice_goes_through_the_invoice_rules(): void
    {
        $item = Item::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'product', 'track_inventory' => true]);
        Inventory::create(['tenant_id' => $this->tenant->id, 'item_id' => $item->id, 'quantity' => 5, 'reserved_quantity' => 0]);

        // Asking for more than is in stock is refused, and nothing changes
        $short = $this->quotation(['items' => [['item_id' => $item->id, 'description' => 'Pump', 'quantity' => 8, 'unit_price' => 1000, 'tax_rate' => 0]], 'discount_type' => '', 'discount_amount' => 0]);
        $this->post(route('quotations.convert-invoice', $short))->assertSessionHas('error');
        $this->assertSame('draft', $short->fresh()->status);
        $this->assertSame(0, Invoice::count());

        $quotation = $this->quotation(['items' => [
            ['item_id' => $item->id, 'description' => 'Pump', 'quantity' => 2, 'unit_price' => 50000, 'tax_rate' => 7.5],
        ]]);
        $this->post(route('quotations.convert-invoice', $quotation))->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame('draft', $invoice->status);
        $this->assertSame((float) $quotation->total, (float) $invoice->total);
        $this->assertSame(96750.0, (float) $invoice->total);
        $this->assertSame($invoice->id, $quotation->fresh()->converted_to_invoice_id);
        $this->assertSame('converted', $quotation->fresh()->status);
        $this->assertSame(2.0, (float) Inventory::where('item_id', $item->id)->value('reserved_quantity'));
    }

    public function test_another_business_cannot_see_or_touch_a_quotation(): void
    {
        $quotation = $this->quotation();

        auth()->logout();
        $this->createAuthenticatedUser(self::PERMISSIONS);

        $this->get(route('quotations.show', $quotation))->assertNotFound();
        $this->get(route('quotations.edit', $quotation))->assertNotFound();
        $this->post(route('quotations.convert-invoice', $quotation))->assertNotFound();
        $this->delete(route('quotations.destroy', $quotation))->assertNotFound();
        $this->assertNotSoftDeleted($quotation);

        // and can't quote to the first business's customer
        $this->post(route('quotations.store'), $this->payload())->assertSessionHasErrors('customer_id');
    }

    public function test_permissions_are_checked(): void
    {
        $quotation = $this->quotation();
        $viewer = $this->createUserForTenant($this->tenant, ['view invoices']);
        $this->actingAs($viewer);

        $this->get(route('quotations.show', $quotation))->assertOk()->assertDontSee('Convert to invoice');
        $this->post(route('quotations.store'), $this->payload())->assertForbidden();
        $this->post(route('quotations.send', $quotation))->assertForbidden();
        $this->post(route('quotations.convert-invoice', $quotation))->assertForbidden();
        $this->delete(route('quotations.destroy', $quotation))->assertForbidden();
    }

    public function test_quotations_can_be_switched_off(): void
    {
        config(['mybooks.features.quotations' => false]);

        $this->get(route('quotations.index'))->assertNotFound();
    }
}
