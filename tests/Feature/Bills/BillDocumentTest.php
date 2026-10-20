<?php

namespace Tests\Feature\Bills;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Vendor;
use App\Support\BillDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bills print like invoices: the bill page shows the document itself and
 * prints it in place, the list, the print page and the PDF use the same
 * document, and nothing about a bill opens in a new tab.
 */
class BillDocumentTest extends TestCase
{
    private int $no = 600;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));
        $this->createAuthenticatedUser(['view bills', 'create bills', 'edit bills', 'create payments-made', 'view settings']);
        $this->tenant->update(['name' => 'Kano Traders Ltd', 'currency' => 'NGN', 'tax_number' => '12345678-0001']);
    }

    private function bill(array $attributes = []): Bill
    {
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Dantata Supplies']);
        $bill = Bill::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'bill_number' => 'BILL-000'.(++$this->no),
            'vendor_bill_number' => 'DS-4417',
            'bill_date' => '2026-09-01',
            'due_date' => '2026-10-01',
            'status' => 'unpaid',
            'subtotal' => 100000, 'tax_amount' => 7500, 'discount_amount' => 0, 'total' => 107500,
            'amount_paid' => 0, 'balance_due' => 107500,
        ], $attributes));

        BillItem::query()->where('bill_id', $bill->id)->delete();
        BillItem::create([
            'bill_id' => $bill->id, 'description' => 'Cement, 50 bags', 'quantity' => 2, 'unit_price' => 50000,
            'tax_rate' => 7.5, 'tax_amount' => 7500, 'total' => 107500,
        ]);

        return $bill->fresh();
    }

    public function test_bill_page_shows_the_document_and_prints_in_place(): void
    {
        $bill = $this->bill();

        $html = $this->get(route('bills.show', $bill))->assertOk()
            ->assertSee('data-bill-document', false)
            ->assertSee('Kano Traders Ltd')
            ->assertSee('TIN: 12345678-0001')
            ->assertSee('Billed by')
            ->assertSee('Dantata Supplies')
            ->assertSee('Cement, 50 bags')
            ->assertSee('data-testid="print-bill"', false)
            ->assertSee('Balance payable')
            ->assertSee('19 days late')
            ->getContent();

        $this->assertStringNotContainsString('target="_blank"', $html, 'Nothing on the bill page opens a new tab.');
        // The line shows its amount before VAT, so the lines add up to the subtotal.
        $this->assertMatchesRegularExpression('/Cement, 50 bags.*?100,000\.00/s', $html);
    }

    public function test_the_suppliers_own_number_and_the_order_are_on_the_bill(): void
    {
        $bill = $this->bill();

        $this->get(route('bills.show', $bill))->assertOk()
            ->assertSee('Their invoice no.')
            ->assertSee('DS-4417');
    }

    public function test_bill_list_print_opens_the_bill_and_prints_in_the_same_tab(): void
    {
        $bill = $this->bill();

        $html = $this->get(route('bills.index'))->assertOk()->getContent();

        $this->assertStringContainsString(e(route('bills.show', ['bill' => $bill, 'print' => 1])), $html);
        $this->assertStringNotContainsString('target="_blank"', $html);
        $this->get(route('bills.show', ['bill' => $bill, 'print' => 1]))->assertOk()
            ->assertSee("searchParams.get('print') !== '1'", false);
    }

    public function test_print_page_is_the_same_document_with_a_way_back(): void
    {
        $bill = $this->bill();

        $this->get(route('bills.print', $bill))->assertOk()
            ->assertSee('data-bill-document', false)
            ->assertSee('Back to bill')
            ->assertSee(route('bills.show', $bill), false)
            ->assertSee('data-print', false);
    }

    public function test_download_pdf_is_a_real_pdf_named_for_the_bill(): void
    {
        $bill = $this->bill();

        $response = $this->get(route('bills.pdf', $bill))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString(
            'Bill '.$bill->bill_number.' - Dantata Supplies.pdf',
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_status_in_words(): void
    {
        $this->assertSame('overdue', BillDocument::state($this->bill())['key']);
        $this->assertSame('Paid', BillDocument::state($this->bill(['status' => 'paid', 'amount_paid' => 107500, 'balance_due' => 0]))['label']);
        $this->assertSame('Part paid', BillDocument::state($this->bill(['status' => 'partial', 'amount_paid' => 7500, 'balance_due' => 100000, 'due_date' => '2026-11-01']))['label']);
        $this->assertSame('Draft', BillDocument::state($this->bill(['status' => 'draft']))['label']);
    }

    public function test_printing_keeps_its_own_margins_and_a_paid_bill_says_so(): void
    {
        $bill = $this->bill(['status' => 'paid', 'amount_paid' => 107500, 'balance_due' => 0]);

        $html = $this->get(route('bills.show', $bill))->assertOk()
            ->assertSee('Paid in full')
            ->assertSee('Settled in full.')
            ->getContent();

        // The sheet carries the margins (repeated on every page), so the print
        // dialog's margin setting cannot push the bill off the paper.
        $this->assertStringContainsString('@page { size: A4; margin: 0; }', $html);
        $this->assertStringContainsString('box-decoration-break: clone', $html);
    }

    public function test_the_amount_is_written_out_and_signed_for_before_payment(): void
    {
        $bill = $this->bill();

        $this->get(route('bills.show', $bill))->assertOk()
            ->assertSee('Amount in words')
            ->assertSee('One Hundred and Seven Thousand, Five Hundred Naira Only')
            ->assertSee('Checked by')
            ->assertSee('Approved by')
            ->assertSee('Passed for payment');
    }

    public function test_the_bill_carries_the_business_logo(): void
    {
        Storage::fake('public');
        $img = imagecreatetruecolor(300, 100);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 31, 78, 121));
        ob_start();
        imagewebp($img);
        imagedestroy($img);
        Storage::disk('public')->put('logos/kano.webp', (string) ob_get_clean());
        $this->tenant->update(['logo' => 'logos/kano.webp']);

        $this->get(route('bills.show', $this->bill()))->assertOk()
            ->assertSee('data-testid="bill-logo"', false)
            ->assertDontSee('data-testid="add-logo-hint"', false)
            ->assertSee('src="data:image/png;base64,', false);
    }
}
