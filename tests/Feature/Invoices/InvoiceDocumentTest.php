<?php

namespace Tests\Feature\Invoices;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTemplate;
use App\Support\InvoiceDocument;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Invoice rebuild: the invoice page shows the document itself and prints
 * it in place; the list, the print page and the PDF use the same document;
 * nothing about an invoice opens in a new tab.
 */
class InvoiceDocumentTest extends TestCase
{
    private int $no = 140;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-19 10:00:00'));
        $this->createAuthenticatedUser(['view invoices', 'create invoices', 'edit invoices', 'send invoices', 'create payments-received', 'view settings']);
        $this->tenant->update(['name' => 'Kano Traders Ltd', 'currency' => 'NGN', 'tax_number' => '12345678-0001']);
    }

    private function invoice(array $attributes = []): Invoice
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Danjuma Foods']);
        $invoice = Invoice::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000'.(++$this->no),
            'invoice_date' => '2026-09-01', 'due_date' => '2026-10-01', 'status' => 'unpaid',
            'subtotal' => 100000, 'tax_amount' => 7500, 'discount_amount' => 0, 'total' => 107500,
            'amount_paid' => 0, 'balance_due' => 107500,
        ], $attributes));
        InvoiceItem::query()->where('invoice_id', $invoice->id)->delete();
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Rice 50kg', 'quantity' => 2, 'unit_price' => 50000,
            'tax_rate' => 7.5, 'tax_amount' => 7500, 'total' => 107500,
        ]);

        return $invoice->fresh();
    }

    public function test_invoice_page_shows_the_document_and_prints_in_place(): void
    {
        $invoice = $this->invoice();

        $html = $this->get(route('invoices.show', $invoice))->assertOk()
            ->assertSee('data-invoice-document', false)
            ->assertSee('Kano Traders Ltd')
            ->assertSee('TIN: 12345678-0001')
            ->assertSee('Danjuma Foods')
            ->assertSee('Rice 50kg')
            ->assertSee('data-testid="print-invoice"', false)
            ->assertSee('Balance due')
            ->assertSee('18 days late')
            ->assertSee('Record payment')
            ->getContent();

        $this->assertStringNotContainsString('target="_blank"', $html, 'Nothing on the invoice page opens a new tab.');
        // The line shows its amount before VAT, so the lines add up to the subtotal.
        $this->assertMatchesRegularExpression('/Rice 50kg.*?100,000\.00/s', $html);
    }

    public function test_invoice_list_print_opens_the_invoice_and_prints_in_the_same_tab(): void
    {
        $invoice = $this->invoice();

        $html = $this->get(route('invoices.index'))->assertOk()->getContent();

        $this->assertStringContainsString(e(route('invoices.show', ['invoice' => $invoice, 'print' => 1])), $html);
        $this->assertStringNotContainsString('target="_blank"', $html);
        $this->get(route('invoices.show', ['invoice' => $invoice, 'print' => 1]))->assertOk()->assertSee("searchParams.get('print') !== '1'", false);
    }

    public function test_print_page_is_the_same_document_with_a_way_back(): void
    {
        $invoice = $this->invoice();

        $this->get(route('invoices.print', $invoice))->assertOk()
            ->assertSee('data-invoice-document', false)
            ->assertSee('Back to invoice')
            ->assertSee(route('invoices.show', $invoice), false)
            ->assertSee('data-print', false);
    }

    public function test_download_pdf_is_a_real_pdf_named_for_the_invoice(): void
    {
        $invoice = $this->invoice();

        $response = $this->get(route('invoices.pdf', $invoice))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('Invoice '.$invoice->invoice_number.' - Danjuma Foods.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_status_in_words(): void
    {
        $this->assertSame('overdue', InvoiceDocument::state($this->invoice())['key']);
        $this->assertSame('Paid', InvoiceDocument::state($this->invoice(['status' => 'paid', 'amount_paid' => 107500, 'balance_due' => 0]))['label']);
        $this->assertSame('Part paid', InvoiceDocument::state($this->invoice(['status' => 'partial', 'amount_paid' => 7500, 'balance_due' => 100000, 'due_date' => '2026-11-01']))['label']);
        $this->assertSame('Draft', InvoiceDocument::state($this->invoice(['status' => 'draft']))['label']);
    }

    public function test_template_settings_shape_the_document_and_unsafe_values_are_dropped(): void
    {
        $invoice = $this->invoice(['notes' => 'Thanks for the order']);
        InvoiceTemplate::seedDefaultTemplates($this->tenant->id);
        $template = InvoiceTemplate::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('is_default', true)->first();
        $template->update(['settings' => array_merge(InvoiceTemplate::getDefaultSettings(), [
            'primary_color' => '#AA0000',
            'secondary_color' => 'red;}</style><script>alert(1)</script>',
            'font_family' => 'x}</style><script>alert(2)</script>',
            'show_tax_column' => false,
            'show_notes' => false,
        ])]);

        $html = $this->get(route('invoices.show', $invoice))->assertOk()->getContent();

        $this->assertStringContainsString('#AA0000', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('alert(2)', $html);
        $this->assertStringNotContainsString('Thanks for the order', $html, 'Notes are hidden when the template says so.');
        $this->assertStringNotContainsString('col-tax">7.5%', $html, 'The VAT column is hidden when the template says so.');
    }

    public function test_template_preview_draws_the_real_document(): void
    {
        $html = view('invoices.templates.preview', ['settings' => InvoiceTemplate::getDefaultSettings()])->render();

        $this->assertStringContainsString('data-invoice-document', $html);
        $this->assertStringContainsString('Danjuma Foods', $html);
        $this->assertStringContainsString('1,531,875.00', $html);
    }
}
