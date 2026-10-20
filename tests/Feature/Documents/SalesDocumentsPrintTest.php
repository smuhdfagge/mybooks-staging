<?php

namespace Tests\Feature\Documents;

use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Customer;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Support\DeliveryNoteDocument;
use App\Support\QuotationDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Quotations, credit notes and delivery notes print like invoices: the
 * record page shows the document itself and prints it in place, the list
 * and the PDF use the same document, every one carries the business's
 * logo, and nothing opens in a new tab any more.
 */
class SalesDocumentsPrintTest extends TestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));
        $this->createAuthenticatedUser([
            'view invoices', 'create invoices', 'edit invoices', 'delete invoices', 'send invoices',
            'view customers', 'view sales-orders', 'view settings',
        ]);
        $this->tenant->update(['name' => 'Kano Traders Ltd', 'currency' => 'NGN', 'tax_number' => '12345678-0001']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Danjuma Foods']);
    }

    private function quotation(array $attributes = []): Quotation
    {
        $quotation = Quotation::create(array_merge([
            'tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id,
            'quotation_number' => 'QTN-000401', 'reference' => 'RFQ-7',
            'quotation_date' => '2026-10-01', 'expiry_date' => '2026-11-15', 'status' => 'sent',
            'subtotal' => 100000, 'tax_amount' => 7500, 'discount_amount' => 0, 'total' => 107500,
        ], $attributes));
        QuotationItem::create([
            'quotation_id' => $quotation->id, 'description' => 'Bags of cement', 'quantity' => 2,
            'unit_price' => 50000, 'tax_rate' => 7.5, 'tax_amount' => 7500, 'total' => 107500,
        ]);

        return $quotation->fresh();
    }

    private function creditNote(array $attributes = []): CreditNote
    {
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id, 'invoice_number' => 'INV-000901']);
        $note = CreditNote::create(array_merge([
            'tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id, 'invoice_id' => $invoice->id,
            'credit_note_number' => 'CN-000401', 'credit_note_date' => '2026-10-05', 'status' => 'open',
            'reason' => 'damaged', 'restock' => false,
            'subtotal' => 100000, 'tax_amount' => 7500, 'total' => 107500, 'balance' => 107500,
        ], $attributes));
        CreditNoteItem::create([
            'credit_note_id' => $note->id, 'description' => 'Damaged bags returned', 'quantity' => 2,
            'unit_price' => 50000, 'tax_rate' => 7.5, 'tax_amount' => 7500, 'total' => 107500,
        ]);

        return $note->fresh();
    }

    private function deliveryNote(float $delivered = 10): DeliveryNote
    {
        $note = DeliveryNote::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id,
            'delivery_number' => 'DN-000401', 'delivery_date' => '2026-10-10', 'status' => 'dispatched',
            'shipping_method' => 'Own truck', 'tracking_number' => 'KN-442-AB',
            'shipping_address' => "12 Zoo Road\nKano",
        ]);
        DeliveryNoteItem::create([
            'delivery_note_id' => $note->id, 'description' => 'Bags of cement',
            'quantity_ordered' => 10, 'quantity_delivered' => $delivered,
        ]);

        return $note->fresh();
    }

    public function test_quotation_page_shows_the_document_and_prints_in_place(): void
    {
        $quotation = $this->quotation();

        $html = $this->get(route('quotations.show', $quotation))->assertOk()
            ->assertSee('data-quotation-document', false)
            ->assertSee('Kano Traders Ltd')
            ->assertSee('TIN: 12345678-0001')
            ->assertSee('Prepared for')
            ->assertSee('Danjuma Foods')
            ->assertSee('Bags of cement')
            ->assertSee('data-testid="print-quotation"', false)
            ->assertSee('Quoted price')
            ->getContent();

        $this->assertStringNotContainsString('target="_blank"', $html, 'Nothing on the quotation page opens a new tab.');
        // The line shows its amount before VAT, so the lines add up to the subtotal.
        $this->assertMatchesRegularExpression('/Bags of cement.*?100,000\.00/s', $html);
    }

    public function test_quotation_says_how_long_the_price_holds(): void
    {
        $this->get(route('quotations.show', $this->quotation()))->assertOk()
            ->assertSee('Valid until')
            ->assertSee('15 Nov 2026')
            ->assertSee('This price holds for 26 more days');

        // Past its date, the quotation says so instead.
        $expired = $this->quotation(['quotation_number' => 'QTN-000402', 'expiry_date' => '2026-10-05']);
        $this->get(route('quotations.show', $expired))->assertOk()
            ->assertSee('Expired on 5 Oct 2026')
            ->assertSee('This price expired on');
        $this->assertSame('expired', QuotationDocument::state($expired->fresh())['key']);
    }

    public function test_credit_note_page_shows_the_document_and_what_is_left(): void
    {
        $note = $this->creditNote(['balance' => 40000]);

        $html = $this->get(route('credit-notes.show', $note))->assertOk()
            ->assertSee('data-credit-note-document', false)
            ->assertSee('Credit to')
            ->assertSee('Danjuma Foods')
            ->assertSee('Against invoice')
            ->assertSee('INV-000901')
            ->assertSee('Damaged bags returned')
            ->assertSee('data-testid="print-credit-note"', false)
            ->assertSee('Amount in words')
            ->assertSee('One Hundred and Seven Thousand, Five Hundred Naira Only')
            ->assertSee('Still available')
            ->getContent();

        $this->assertStringNotContainsString('target="_blank"', $html);
    }

    public function test_delivery_note_carries_no_prices_and_calls_out_a_short_delivery(): void
    {
        $short = $this->deliveryNote(delivered: 6);

        $html = $this->get(route('delivery-notes.show', $short))->assertOk()
            ->assertSee('data-delivery-note-document', false)
            ->assertSee('Deliver to')
            ->assertSee('12 Zoo Road')
            ->assertSee('Own truck')
            ->assertSee('KN-442-AB')
            ->assertSee('Ordered')
            ->assertSee('Delivered')
            ->assertSee('Received by')
            ->assertSee('data-testid="print-delivery-note"', false)
            ->assertSee('data-testid="short-delivery"', false)
            ->getContent();

        $this->assertTrue(DeliveryNoteDocument::short($short));
        // A driver should not be carrying prices.
        $this->assertStringNotContainsString('Amount (', $html);
        $this->assertStringNotContainsString('50,000.00', $html);
        $this->assertStringNotContainsString('target="_blank"', $html);
    }

    public function test_a_full_delivery_is_not_flagged_short(): void
    {
        $full = $this->deliveryNote(delivered: 10);

        $this->assertFalse(DeliveryNoteDocument::short($full));
        $this->get(route('delivery-notes.show', $full))->assertOk()
            ->assertDontSee('data-testid="short-delivery"', false);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function documents(): array
    {
        return [
            'quotation' => ['quotation', 'quotations', 'data-quotation-document'],
            'credit note' => ['creditNote', 'credit-notes', 'data-credit-note-document'],
            'delivery note' => ['deliveryNote', 'delivery-notes', 'data-delivery-note-document'],
        ];
    }

    public function test_every_list_prints_in_the_same_tab_and_the_print_page_leads_back(): void
    {
        $records = [
            'quotations' => [$this->quotation(), 'quotation'],
            'credit-notes' => [$this->creditNote(), 'creditNote'],
            'delivery-notes' => [$this->deliveryNote(), 'deliveryNote'],
        ];

        foreach ($records as $group => [$record, $param]) {
            $list = $this->get(route($group.'.index'))->assertOk()->getContent();
            $this->assertStringContainsString(
                e(route($group.'.show', [$param => $record, 'print' => 1])),
                $list,
                $group.' list prints in the same tab'
            );
            $this->assertStringNotContainsString('target="_blank"', $list, $group.' list opens no new tab');

            $this->get(route($group.'.show', [$param => $record, 'print' => 1]))->assertOk()
                ->assertSee("searchParams.get('print') !== '1'", false);

            $this->get(route($group.'.print', $record))->assertOk()
                ->assertSee('Back to the', false)
                ->assertSee(route($group.'.show', $record), false)
                ->assertSee('data-print', false);
        }
    }

    public function test_every_pdf_is_a_real_pdf_named_for_its_document(): void
    {
        $cases = [
            ['quotations.pdf', $this->quotation(), 'Quotation QTN-000401 - Danjuma Foods.pdf'],
            ['credit-notes.pdf', $this->creditNote(), 'Credit note CN-000401 - Danjuma Foods.pdf'],
            ['delivery-notes.pdf', $this->deliveryNote(), 'Delivery note DN-000401 - Danjuma Foods.pdf'],
        ];

        foreach ($cases as [$route, $record, $fileName]) {
            $response = $this->get(route($route, $record))->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');

            $this->assertStringStartsWith('%PDF', $response->getContent(), $route);
            $this->assertStringContainsString($fileName, (string) $response->headers->get('Content-Disposition'), $route);
        }
    }

    public function test_every_document_carries_the_business_logo_and_its_own_print_margins(): void
    {
        Storage::fake('public');
        $img = imagecreatetruecolor(300, 100);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 31, 78, 121));
        ob_start();
        imagewebp($img);
        imagedestroy($img);
        Storage::disk('public')->put('logos/kano.webp', (string) ob_get_clean());
        $this->tenant->update(['logo' => 'logos/kano.webp']);

        $pages = [
            route('quotations.show', $this->quotation()) => 'quotation-logo',
            route('credit-notes.show', $this->creditNote()) => 'credit-note-logo',
            route('delivery-notes.show', $this->deliveryNote()) => 'delivery-note-logo',
        ];

        foreach ($pages as $url => $testId) {
            $html = $this->get($url)->assertOk()
                ->assertSee('data-testid="'.$testId.'"', false)
                ->assertSee('src="data:image/png;base64,', false)
                ->getContent();

            // The sheet carries the margins, so the print dialog cannot push
            // the document off the paper.
            $this->assertStringContainsString('@page { size: A4; margin: 0; }', $html, $url);
            $this->assertStringContainsString('box-decoration-break: clone', $html, $url);
        }
    }
}
