<?php

namespace Tests\Feature\Documents;

use App\Models\Bank;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\Vendor;
use App\Services\Accounting\WithholdingTax;
use App\Support\PaymentDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Payments print: a receipt for the customer, a payment advice for the
 * supplier. Both are one sheet with two sides, and both carry the
 * withholding tax, which is the part that decides whether anybody can
 * claim it back.
 */
class PaymentDocumentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));
        $this->createAuthenticatedUser([
            'view payments-received', 'create payments-received', 'edit payments-received', 'delete payments-received',
            'view payments-made', 'create payments-made', 'edit payments-made', 'delete payments-made',
            'view customers', 'view vendors', 'view invoices', 'view bills', 'view settings',
        ]);
        $this->tenant->update(['name' => 'Kano Traders Ltd', 'currency' => 'NGN', 'tax_number' => '12345678-0001']);
    }

    private function bank(): Bank
    {
        return Bank::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Zenith Current']);
    }

    private function received(array $attributes = []): PaymentReceived
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Danjuma Foods']);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => 'INV-000777',
        ]);

        return PaymentReceived::create(array_merge([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_id' => $invoice->id,
            'payment_number' => 'RCP-000501', 'payment_date' => '2026-10-15',
            'amount' => 95000, 'payment_method' => 'bank_transfer',
            'bank_id' => $this->bank()->id, 'reference' => 'TRF-9912',
            'created_by' => $this->user->id,
        ], $attributes))->fresh();
    }

    private function made(array $attributes = []): PaymentMade
    {
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Dantata Supplies']);
        $bill = Bill::factory()->create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'bill_number' => 'BILL-000777',
        ]);

        return PaymentMade::create(array_merge([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'bill_id' => $bill->id,
            'payment_number' => 'PAY-000501', 'payment_date' => '2026-10-15',
            'amount' => 95000, 'payment_method' => 'bank_transfer',
            'bank_id' => $this->bank()->id, 'reference' => 'TRF-9913',
            'created_by' => $this->user->id,
        ], $attributes))->fresh();
    }

    public function test_a_receipt_is_what_the_customer_gets(): void
    {
        $payment = $this->received();

        $html = $this->get(route('payments-received.show', $payment))->assertOk()
            ->assertSee('data-payment-document', false)
            ->assertSee('Receipt')
            ->assertSee('Kano Traders Ltd')
            ->assertSee('TIN: 12345678-0001')
            ->assertSee('Received from')
            ->assertSee('Danjuma Foods')
            ->assertSee('For invoice')
            ->assertSee('INV-000777')
            ->assertSee('Zenith Current')
            ->assertSee('TRF-9912')
            ->assertSee('Amount received in words')
            ->assertSee('Ninety-Five Thousand Naira Only')
            ->assertSee('For Kano Traders Ltd')
            ->assertSee('data-testid="print-receipt"', false)
            ->getContent();

        $this->assertStringNotContainsString('target="_blank"', $html);
    }

    public function test_a_receipt_shows_the_wht_the_customer_kept_back(): void
    {
        $payment = $this->received([
            'amount' => 95000, 'wht_rate' => 5, 'wht_base' => 100000, 'wht_amount' => 5000,
            'wht_authority' => WithholdingTax::AUTHORITY_STATE, 'wht_state' => 'Kano',
        ]);

        $this->assertSame(100000.0, $payment->settledAmount());

        $this->get(route('payments-received.show', $payment))->assertOk()
            ->assertSee('Less WHT deducted by you')
            ->assertSee('Kano Internal Revenue Service')
            ->assertSee('Settled on the invoice')
            // The customer must send the credit note or the money is lost.
            ->assertSee('Please send us the withholding tax credit note for this deduction.')
            ->assertSee('Still waiting for the credit note. Without it you cannot claim this back.')
            // The words follow the cash that changed hands, not the settled figure.
            ->assertSee('Ninety-Five Thousand Naira Only');
    }

    public function test_a_payment_advice_lets_the_supplier_claim_the_wht_back(): void
    {
        $payment = $this->made([
            'amount' => 95000, 'wht_rate' => 5, 'wht_base' => 100000, 'wht_amount' => 5000,
            'wht_authority' => WithholdingTax::AUTHORITY_STATE, 'wht_state' => 'Kano',
        ]);

        $this->get(route('payments-made.show', $payment))->assertOk()
            ->assertSee('data-payment-document', false)
            ->assertSee('Payment Advice')
            ->assertSee('Paid to')
            ->assertSee('Dantata Supplies')
            ->assertSee('For bill')
            ->assertSee('BILL-000777')
            ->assertSee('Less WHT withheld')
            ->assertSee('Settled on the bill')
            ->assertSee('The withholding tax credit note for this deduction will follow.')
            ->assertSee('Prepared by')
            ->assertSee('Approved by')
            ->assertSee('data-testid="print-payment-advice"', false)
            // This is the business's own liability, not the supplier's.
            ->assertSee('You owe this to the tax authority, not to the supplier.');
    }

    public function test_money_on_account_says_so_instead_of_naming_a_document(): void
    {
        $deposit = $this->received(['invoice_id' => null, 'is_deposit' => true, 'unused_amount' => 95000]);

        $this->get(route('payments-received.show', $deposit))->assertOk()
            ->assertSee('Deposit on account')
            ->assertSee('Held on account, not yet set against a document.')
            ->assertDontSee('For invoice');
    }

    public function test_both_sides_print_in_the_same_tab_and_lead_back(): void
    {
        $cases = [
            ['payments-received', 'paymentReceived', $this->received()],
            ['payments-made', 'paymentMade', $this->made()],
        ];

        foreach ($cases as [$group, $param, $payment]) {
            $list = $this->get(route($group.'.index'))->assertOk()->getContent();
            $this->assertStringContainsString(e(route($group.'.show', [$param => $payment, 'print' => 1])), $list, $group);
            $this->assertStringNotContainsString('target="_blank"', $list, $group);

            $this->get(route($group.'.show', [$param => $payment, 'print' => 1]))->assertOk()
                ->assertSee("searchParams.get('print') !== '1'", false);

            $this->get(route($group.'.print', $payment))->assertOk()
                ->assertSee('data-payment-document', false)
                ->assertSee('Back to the payment')
                ->assertSee(route($group.'.show', $payment), false)
                ->assertSee('data-print', false);
        }
    }

    public function test_both_pdfs_are_real_pdfs_named_for_their_document(): void
    {
        $cases = [
            ['payments-received.pdf', $this->received(), 'Receipt RCP-000501 - Danjuma Foods.pdf'],
            ['payments-made.pdf', $this->made(), 'Payment advice PAY-000501 - Dantata Supplies.pdf'],
        ];

        foreach ($cases as [$route, $payment, $fileName]) {
            $response = $this->get(route($route, $payment))->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');

            $this->assertStringStartsWith('%PDF', $response->getContent(), $route);
            $this->assertStringContainsString($fileName, (string) $response->headers->get('Content-Disposition'), $route);
            $this->assertSame($fileName, PaymentDocument::fileName($payment));
        }
    }

    public function test_both_carry_the_business_logo_and_their_own_print_margins(): void
    {
        Storage::fake('public');
        $img = imagecreatetruecolor(300, 100);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 31, 78, 121));
        ob_start();
        imagewebp($img);
        imagedestroy($img);
        Storage::disk('public')->put('logos/kano.webp', (string) ob_get_clean());
        $this->tenant->update(['logo' => 'logos/kano.webp']);

        $urls = [
            route('payments-received.show', $this->received()),
            route('payments-made.show', $this->made()),
        ];

        foreach ($urls as $url) {
            $html = $this->get($url)->assertOk()
                ->assertSee('data-testid="payment-logo"', false)
                ->assertSee('src="data:image/png;base64,', false)
                ->getContent();

            $this->assertStringContainsString('@page { size: A4; margin: 0; }', $html, $url);
            $this->assertStringContainsString('box-decoration-break: clone', $html, $url);
        }
    }
}
