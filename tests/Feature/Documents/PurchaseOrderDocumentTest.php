<?php

namespace Tests\Feature\Documents;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Vendor;
use App\Support\PurchaseOrderDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Purchase orders print like invoices. A purchase order is the business's
 * written instruction to a supplier to deliver and to invoice, so it
 * carries where to deliver, what to quote back, and a signature
 * authorising the spend.
 */
class PurchaseOrderDocumentTest extends TestCase
{
    private int $no = 700;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));
        $this->createAuthenticatedUser([
            'view purchase-orders', 'create purchase-orders', 'edit purchase-orders', 'delete purchase-orders',
            'create bills', 'view vendors', 'view settings',
        ]);
        $this->tenant->update([
            'name' => 'Kano Traders Ltd', 'currency' => 'NGN', 'tax_number' => '12345678-0001',
            'address' => '12 Zoo Road', 'city' => 'Kano', 'state' => 'Kano', 'country' => 'Nigeria',
        ]);
    }

    private function order(array $attributes = [], float $received = 0): PurchaseOrder
    {
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Dantata Supplies']);
        $order = PurchaseOrder::create(array_merge([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id,
            'order_number' => 'PO-000'.(++$this->no), 'reference' => 'REQ-19',
            'order_date' => '2026-10-01', 'expected_date' => '2026-10-28', 'status' => 'confirmed',
            'subtotal' => 100000, 'tax_amount' => 7500, 'discount_amount' => 0, 'total' => 107500,
            'created_by' => $this->user->id,
        ], $attributes));
        $item = Item::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cement', 'sku' => 'CEM-50']);
        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id, 'item_id' => $item->id, 'description' => 'Bags of cement',
            'quantity' => 10, 'quantity_received' => $received, 'unit_price' => 10000,
            'tax_rate' => 7.5, 'tax_amount' => 7500, 'total' => 107500,
        ]);

        return $order->fresh();
    }

    public function test_order_page_shows_the_document_and_prints_in_place(): void
    {
        $order = $this->order();

        $html = $this->get(route('purchase-orders.show', $order))->assertOk()
            ->assertSee('data-purchase-order-document', false)
            ->assertSee('Purchase Order')
            ->assertSee('Kano Traders Ltd')
            ->assertSee('TIN: 12345678-0001')
            ->assertSee('Order to')
            ->assertSee('Dantata Supplies')
            ->assertSee('Bags of cement')
            ->assertSee('data-testid="print-purchase-order"', false)
            ->assertSee('Order value')
            ->getContent();

        $this->assertStringNotContainsString('target="_blank"', $html, 'Nothing on the order page opens a new tab.');
        // The line shows its amount before VAT, so the lines add up to the subtotal.
        $this->assertMatchesRegularExpression('/Bags of cement.*?100,000\.00/s', $html);
    }

    public function test_the_supplier_is_told_where_to_deliver_and_what_to_quote(): void
    {
        $order = $this->order();

        $this->get(route('purchase-orders.show', $order))->assertOk()
            ->assertSee('Deliver to')
            ->assertSee('12 Zoo Road')
            ->assertSee('Kano, Kano, Nigeria')
            ->assertSee('Wanted by')
            ->assertSee('28 Oct 2026')
            // So the supplier's invoice and delivery note can be matched back.
            ->assertSee('Please quote order '.$order->order_number.' on your invoice and delivery note.')
            // A purchase order commits money, so it is signed for.
            ->assertSee('Raised by')
            ->assertSee('Authorised for Kano Traders Ltd')
            ->assertSee('Amount in words')
            ->assertSee('One Hundred and Seven Thousand, Five Hundred Naira Only');
    }

    public function test_what_arrived_shows_only_once_something_has(): void
    {
        // A fresh order goes to the supplier clean: no received column.
        $clean = $this->order();
        $this->assertFalse(PurchaseOrderDocument::showReceived($clean));
        $this->get(route('purchase-orders.show', $clean))->assertOk()->assertDontSee('>Received<', false);

        // Once goods arrive the same sheet doubles as a receiving check, and
        // a short delivery is called out.
        $part = $this->order(['status' => 'partially_received'], received: 6);
        $this->assertTrue(PurchaseOrderDocument::showReceived($part));
        $this->get(route('purchase-orders.show', $part))->assertOk()
            ->assertSee('>Received<', false)
            ->assertSee('class="num short"', false)
            ->assertSee('6 of 10 received');
    }

    public function test_status_in_words(): void
    {
        $this->assertSame('Ordered', PurchaseOrderDocument::state($this->order())['label']);
        $this->assertSame('Draft', PurchaseOrderDocument::state($this->order(['status' => 'draft']))['label']);
        $this->assertSame('Part received', PurchaseOrderDocument::state($this->order(['status' => 'partially_received']))['label']);
        $this->assertSame('All goods received', PurchaseOrderDocument::state($this->order(['status' => 'received']))['label']);
        $this->assertSame('Billed', PurchaseOrderDocument::state($this->order(['status' => 'billed']))['label']);
        $this->assertSame('Cancelled', PurchaseOrderDocument::state($this->order(['status' => 'cancelled']))['label']);
    }

    public function test_list_prints_in_the_same_tab_and_the_print_page_leads_back(): void
    {
        $order = $this->order();

        $list = $this->get(route('purchase-orders.index'))->assertOk()->getContent();
        $this->assertStringContainsString(e(route('purchase-orders.show', ['purchaseOrder' => $order, 'print' => 1])), $list);
        $this->assertStringNotContainsString('target="_blank"', $list);

        $this->get(route('purchase-orders.show', ['purchaseOrder' => $order, 'print' => 1]))->assertOk()
            ->assertSee("searchParams.get('print') !== '1'", false);

        $this->get(route('purchase-orders.print', $order))->assertOk()
            ->assertSee('data-purchase-order-document', false)
            ->assertSee('Back to the purchase order')
            ->assertSee(route('purchase-orders.show', $order), false)
            ->assertSee('data-print', false);
    }

    public function test_download_pdf_is_a_real_pdf_named_for_the_order(): void
    {
        $order = $this->order();

        $response = $this->get(route('purchase-orders.pdf', $order))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString(
            'Purchase order '.$order->order_number.' - Dantata Supplies.pdf',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function test_the_order_carries_the_business_logo_and_its_own_print_margins(): void
    {
        Storage::fake('public');
        $img = imagecreatetruecolor(300, 100);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 31, 78, 121));
        ob_start();
        imagewebp($img);
        imagedestroy($img);
        Storage::disk('public')->put('logos/kano.webp', (string) ob_get_clean());
        $this->tenant->update(['logo' => 'logos/kano.webp']);

        $html = $this->get(route('purchase-orders.show', $this->order()))->assertOk()
            ->assertSee('data-testid="purchase-order-logo"', false)
            ->assertDontSee('data-testid="add-logo-hint"', false)
            ->assertSee('src="data:image/png;base64,', false)
            ->getContent();

        $this->assertStringContainsString('@page { size: A4; margin: 0; }', $html);
        $this->assertStringContainsString('box-decoration-break: clone', $html);
    }
}
