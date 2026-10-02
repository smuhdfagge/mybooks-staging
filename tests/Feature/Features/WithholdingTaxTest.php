<?php

namespace Tests\Feature\Features;

use App\Models\Bank;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\StatutoryRemittance;
use App\Models\Vendor;
use App\Models\WhtCredit;
use App\Models\WhtRate;
use Tests\TestCase;

/**
 * Tax pack 2: withholding tax ("deduction at source") on payments.
 */
class WithholdingTaxTest extends TestCase
{
    private const PERMS = [
        'create payments-made', 'delete payments-made', 'create payments-received', 'delete payments-received',
        'view withholding-tax', 'manage withholding-tax',
    ];

    private function balance(string $code): float
    {
        return (float) ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', $code)->value('current_balance');
    }

    private function bill(Vendor $vendor): Bill
    {
        return Bill::withoutEvents(fn () => Bill::factory()->create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $vendor->id, 'status' => 'unpaid',
            'subtotal' => 100000, 'tax_amount' => 7500, 'discount_amount' => 0, 'total' => 107500, 'balance_due' => 107500, 'amount_paid' => 0,
        ]));
    }

    private function invoice(Customer $customer): Invoice
    {
        return Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'status' => 'unpaid',
            'subtotal' => 100000, 'tax_amount' => 7500, 'discount_amount' => 0, 'total' => 107500, 'balance_due' => 107500, 'amount_paid' => 0,
        ]));
    }

    private function rate(string $code): WhtRate
    {
        WhtRate::ensureDefaults($this->tenant->id);

        return WhtRate::where('tenant_id', $this->tenant->id)->where('code', $code)->sole();
    }

    /** @return array<int, array{0: string, 1: float, 2: float}> account code, debit, credit */
    private function lines(string $type, int $id): array
    {
        $journal = Journal::where('reference_type', $type)->where('reference_id', $id)->sole();
        $this->assertEqualsWithDelta((float) $journal->total_debit, (float) $journal->total_credit, 0.001);

        return $journal->entries()->with('account')->get()
            ->map(fn ($e) => [$e->account->account_code, (float) $e->debit, (float) $e->credit])->sortBy(0)->values()->all();
    }

    public function test_paying_a_vendor_net_of_wht_settles_the_bill_and_owes_the_wht(): void
    {
        $this->createAuthenticatedUser(self::PERMS);
        $bank = Bank::factory()->create(['tenant_id' => $this->tenant->id, 'current_balance' => 500000]);
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id, 'tax_number' => '12345678-0001', 'entity_type' => 'company']);
        $bill = $this->bill($vendor);
        $rate = $this->rate('professional');

        $this->post(route('payments-made.store'), [
            'vendor_id' => $vendor->id, 'bill_id' => $bill->id, 'payment_date' => '2026-09-15', 'amount' => 107500,
            'payment_method' => 'bank_transfer', 'bank_id' => $bank->id,
            'wht_rate_id' => $rate->id, 'wht_rate' => 5, 'wht_amount' => 5000, // 5% of the 100,000 before VAT
        ])->assertSessionHasNoErrors();

        $payment = PaymentMade::sole();
        $this->assertSame('paid', $bill->fresh()->status);
        $this->assertEqualsWithDelta(0, (float) $bill->fresh()->balance_due, 0.001);
        $this->assertSame([['1100', 0.0, 102500.0], ['2000', 107500.0, 0.0], ['2420', 0.0, 5000.0]], $this->lines(PaymentMade::class, $payment->id));
        $this->assertEqualsWithDelta(397500, (float) $bank->fresh()->current_balance, 0.001);
        $this->assertEqualsWithDelta(5000, $this->balance('2420'), 0.001);

        // Deleting it puts everything back.
        $this->delete(route('payments-made.destroy', $payment))->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(500000, (float) $bank->fresh()->current_balance, 0.001);
        $this->assertEqualsWithDelta(0, $this->balance('2420'), 0.001);
    }

    public function test_a_payment_without_wht_posts_as_before(): void
    {
        $this->createAuthenticatedUser(self::PERMS);
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = $this->bill($vendor);

        $this->post(route('payments-made.store'), [
            'vendor_id' => $vendor->id, 'bill_id' => $bill->id, 'payment_date' => '2026-09-15', 'amount' => 107500, 'payment_method' => 'bank_transfer',
        ])->assertSessionHasNoErrors();

        $payment = PaymentMade::sole();
        $this->assertEqualsWithDelta(0, (float) $payment->wht_amount, 0.001);
        $this->assertSame([['1100', 0.0, 107500.0], ['2000', 107500.0, 0.0]], $this->lines(PaymentMade::class, $payment->id));
    }

    public function test_a_customer_paying_net_of_wht_gives_a_tax_credit_to_follow_up(): void
    {
        $this->createAuthenticatedUser(self::PERMS);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = $this->invoice($customer);
        $rate = $this->rate('services');

        $this->post(route('payments-received.store'), [
            'customer_id' => $customer->id, 'invoice_id' => $invoice->id, 'payment_date' => '2026-09-20', 'amount' => 107500,
            'payment_method' => 'bank_transfer', 'wht_rate_id' => $rate->id, 'wht_rate' => 2, 'wht_amount' => 2000,
        ])->assertSessionHasNoErrors();

        $payment = PaymentReceived::sole();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame([['1100', 105500.0, 0.0], ['1200', 0.0, 107500.0], ['1420', 2000.0, 0.0]], $this->lines(PaymentReceived::class, $payment->id));

        $credit = WhtCredit::sole();
        $this->assertSame(WhtCredit::STATUS_AWAITING, $credit->status);
        $this->assertEqualsWithDelta(2000, (float) $credit->amount, 0.001);

        // The customer's certificate arrives.
        $this->put(route('withholding-tax.credits.update', $credit), ['certificate_number' => 'WHT/2026/0091', 'certificate_date' => '2026-10-01'])
            ->assertSessionHasNoErrors();
        $this->assertSame(WhtCredit::STATUS_RECEIVED, $credit->fresh()->status);
        $this->get(route('withholding-tax.index', ['tab' => 'credits']))->assertOk()->assertSee('WHT/2026/0091');
        $csv = $this->get(route('withholding-tax.credits.export', ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Certificate received', $csv);

        // Deleting the payment removes the credit and reverses the journal.
        $this->delete(route('payments-received.destroy', $payment))->assertSessionHasNoErrors();
        $this->assertSame(0, WhtCredit::count());
        $this->assertEqualsWithDelta(0, $this->balance('1420'), 0.001);
    }

    public function test_wht_must_be_less_than_the_payment_and_not_on_deposits(): void
    {
        $this->createAuthenticatedUser(self::PERMS);
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->post(route('payments-made.store'), [
            'vendor_id' => $vendor->id, 'payment_date' => '2026-09-15', 'amount' => 1000, 'payment_method' => 'cash', 'wht_amount' => 1000,
        ])->assertSessionHasErrors('wht_amount');

        $this->post(route('payments-received.store'), [
            'customer_id' => $customer->id, 'payment_date' => '2026-09-15', 'amount' => 1000, 'payment_method' => 'cash',
            'is_deposit' => 1, 'wht_amount' => 50,
        ])->assertSessionHasErrors('wht_amount');

        $this->assertSame(0, PaymentMade::count() + PaymentReceived::count());
    }

    public function test_the_api_records_wht_through_the_same_action(): void
    {
        $this->createAuthenticatedUser(self::PERMS);
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $bill = $this->bill($vendor);

        $this->postJson('/api/v1/payments-made', [
            'vendor_id' => $vendor->id, 'bill_id' => $bill->id, 'payment_date' => '2026-09-15', 'amount' => 107500,
            'payment_method' => 'bank_transfer', 'wht_rate_id' => $this->rate('rent')->id, 'wht_rate' => 10, 'wht_amount' => 10000,
        ])->assertCreated();

        $this->assertEqualsWithDelta(10000, $this->balance('2420'), 0.001);
        $this->assertSame('paid', $bill->fresh()->status);
    }

    public function test_schedule_lists_wht_per_vendor_with_tin_and_remitting_clears_it(): void
    {
        $this->createAuthenticatedUser(self::PERMS);
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Adamu Consulting', 'tax_number' => '98765432-0001']);
        $bill = $this->bill($vendor);
        $this->post(route('payments-made.store'), [
            'vendor_id' => $vendor->id, 'bill_id' => $bill->id, 'payment_date' => '2026-09-15', 'amount' => 107500,
            'payment_method' => 'bank_transfer', 'wht_rate_id' => $this->rate('professional')->id, 'wht_rate' => 5, 'wht_amount' => 5000,
        ])->assertSessionHasNoErrors();

        $csv = $this->get(route('withholding-tax.schedule', ['month' => '2026-09', 'format' => 'csv']))->assertOk()->streamedContent();
        $this->assertMatchesRegularExpression('/"?Adamu Consulting"?,98765432-0001,Company,.*,2026-09-15,.*,107500.00,5.00,5000.00/', $csv);
        $this->get(route('withholding-tax.schedule', ['month' => '2026-09', 'format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('withholding-tax.index', ['month' => '2026-09']))->assertOk()->assertSee('Adamu Consulting')->assertSee('21 October 2026');

        $this->post(route('withholding-tax.remit'), [
            'period' => '2026-09', 'amount' => 6000, 'date' => now()->toDateString(), 'payment_method' => 'bank_transfer',
        ])->assertSessionHasErrors('amount');

        $this->post(route('withholding-tax.remit'), [
            'period' => '2026-09', 'amount' => 5000, 'date' => now()->toDateString(), 'payment_method' => 'bank_transfer', 'reference' => 'NRS-123',
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(0, $this->balance('2420'), 0.001);
        $remittance = StatutoryRemittance::sole();
        $this->assertSame('wht', $remittance->body);
        $this->assertSame('tax_remittance', $remittance->journal->journal_type);
    }

    public function test_rates_are_seeded_and_editable(): void
    {
        $this->createAuthenticatedUser(self::PERMS);
        $this->get(route('withholding-tax.index', ['tab' => 'rates']))->assertOk()->assertSee("Directors' fees");

        $rate = $this->rate('rent');
        $this->assertEqualsWithDelta(10, (float) $rate->rate_company, 0.001);
        $this->assertNull(WhtRate::where('code', 'directors_fees')->sole()->rate_company);

        $this->put(route('withholding-tax.rates.update'), [
            'rates' => [$rate->id => ['name' => 'Rent', 'rate_company' => 5, 'rate_individual' => 5, 'is_active' => 1]],
            'new' => ['name' => 'Haulage', 'rate_company' => 2, 'rate_individual' => 2],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(5, (float) $rate->fresh()->rate_company, 0.001);
        $this->assertSame(1, WhtRate::where('name', 'Haulage')->count());

        $this->put(route('withholding-tax.settings.update'), ['small_company' => 1, 'small_company_threshold' => 2000000, 'double_without_tin' => 1])
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->tenant->fresh()->settings['wht']['small_company']);
    }

    public function test_a_business_cannot_use_another_business_wht_records(): void
    {
        $this->createAuthenticatedUser(self::PERMS);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->post(route('payments-received.store'), [
            'customer_id' => $customer->id, 'invoice_id' => $this->invoice($customer)->id, 'payment_date' => '2026-09-20', 'amount' => 107500,
            'payment_method' => 'bank_transfer', 'wht_amount' => 2000,
        ])->assertSessionHasNoErrors();
        $credit = WhtCredit::sole();
        $otherRate = $this->rate('services');

        auth()->logout();
        $this->createAuthenticatedUser(self::PERMS);
        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->put(route('withholding-tax.credits.update', $credit), ['certificate_number' => 'X'])->assertNotFound();
        $this->get(route('withholding-tax.index', ['tab' => 'credits']))->assertOk()->assertDontSee($customer->name);
        $this->post(route('payments-made.store'), [
            'vendor_id' => $vendor->id, 'payment_date' => '2026-09-15', 'amount' => 1000, 'payment_method' => 'cash',
            'wht_rate_id' => $otherRate->id, 'wht_amount' => 50,
        ])->assertSessionHasErrors('wht_rate_id');
    }

    public function test_permissions(): void
    {
        $this->createAuthenticatedUser(['view withholding-tax']);
        $this->get(route('withholding-tax.index'))->assertOk();
        $this->post(route('withholding-tax.remit'), [])->assertForbidden();
        $this->put(route('withholding-tax.rates.update'), [])->assertForbidden();

        auth()->logout();
        $this->createAuthenticatedUser([]);
        $this->get(route('withholding-tax.index'))->assertForbidden();
        $this->get(route('withholding-tax.schedule'))->assertForbidden();
    }
}
