<?php

namespace Tests\Feature\Features;

use App\Actions\Bills\SaveBill;
use App\Models\Bank;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WhtCategory;
use App\Models\WhtCreditUtilisation;
use App\Services\AccountCodeService;
use App\Services\Accounting\WithholdingTax;
use App\Services\JournalService;
use Tests\TestCase;

/**
 * Withholding tax (WHT): setup, deductions on purchases and sales, the
 * monthly schedule and remittance, and WHT credit notes.
 */
class WithholdingTaxTest extends TestCase
{
    private const ALL = ['view withholding-tax', 'manage withholding-tax', 'remit withholding-tax'];

    private const RECEIVE = ['create payments-received', 'edit payments-received', 'delete payments-received', 'view payments-received'];

    private const PAY = ['create payments-made', 'edit payments-made', 'delete payments-made', 'view payments-made'];

    /** Sign in as a user of a brand-new business (signed out first, so the business is set up as itself). */
    private function signInFresh(array $permissions): void
    {
        $this->app['auth']->forgetGuards();
        $this->createAuthenticatedUser($permissions);
    }

    // ── Setup ───────────────────────────────────────────────────

    public function test_a_new_business_gets_the_statutory_rates_and_wht_accounts(): void
    {
        [$tenant] = $this->createTenantWithSubscription();

        $rates = WhtCategory::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get()->keyBy('code');
        $this->assertCount(count(WhtCategory::defaults()), $rates);
        $this->assertEqualsWithDelta(2, (float) $rates['supply_goods']->rate_company, 0.001);
        $this->assertEqualsWithDelta(5, (float) $rates['professional']->rate_individual, 0.001);
        $this->assertEqualsWithDelta(10, (float) $rates['rent']->rate_company, 0.001);
        $this->assertEqualsWithDelta(5, (float) $rates['royalties']->rate_individual, 0.001);
        $this->assertEqualsWithDelta(15, (float) $rates['directors_fees']->rate_individual, 0.001);
        $this->assertSame('2025-01-01', $rates['supply_goods']->effective_from->format('Y-m-d'));
        $this->assertStringContainsString('Withholding', (string) $rates['supply_goods']->source);

        foreach (['wht_payable' => 'liability', 'wht_receivable' => 'asset', 'income_tax_payable' => 'liability'] as $key => $type) {
            $code = AccountCodeService::resolve($tenant->id, $key);
            $this->assertSame($type, ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('account_code', $code)->value('type'), $key);
        }
    }

    public function test_seeding_again_keeps_the_business_own_rates(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $rent = WhtCategory::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('code', 'rent')->firstOrFail();
        $rent->skipTenantGuard = true;
        $rent->update(['rate_company' => 7.5]);

        WhtCategory::seedDefaults($tenant->id);

        $this->assertEqualsWithDelta(7.5, (float) $rent->fresh()->rate_company, 0.001);
        $this->assertSame(count(WhtCategory::defaults()), WhtCategory::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_rate_depends_on_payee_type_and_doubles_without_a_tin(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $rates = WhtCategory::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get()->keyBy('code');

        $this->assertSame(10.0, $rates['royalties']->rateFor('company', true));
        $this->assertSame(5.0, $rates['royalties']->rateFor('individual', true));
        // Non-passive income without a TIN: twice the rate.
        $this->assertSame(10.0, $rates['professional']->rateFor('company', false));
        // Passive income (dividends) is not doubled.
        $this->assertSame(10.0, $rates['dividends']->rateFor('individual', false));

        $this->assertSame(WithholdingTax::AUTHORITY_FEDERAL, WithholdingTax::authorityFor('company'));
        $this->assertSame(WithholdingTax::AUTHORITY_STATE, WithholdingTax::authorityFor('individual'));
    }

    public function test_the_business_can_edit_its_rates_and_settings(): void
    {
        $this->createAuthenticatedUser(self::ALL);
        $rent = WhtCategory::where('code', 'rent')->firstOrFail();

        $this->get(route('withholding-tax.setup'))->assertOk()->assertSee('Rent, hire and lease');

        $this->put(route('withholding-tax.rates.update'), ['rates' => [$rent->id => [
            'name' => 'Rent', 'rate_company' => 7.5, 'rate_individual' => 6, 'effective_from' => '2026-01-01',
            'source' => 'Our accountant', 'double_without_tin' => 0, 'is_active' => 1,
        ]]])->assertSessionHasNoErrors();

        $rent->refresh();
        $this->assertEqualsWithDelta(7.5, (float) $rent->rate_company, 0.001);
        $this->assertEqualsWithDelta(6, (float) $rent->rate_individual, 0.001);
        $this->assertSame('Our accountant', $rent->source);

        $this->post(route('withholding-tax.rates.store'), [
            'name' => 'Hire of vehicles', 'rate_company' => 10, 'rate_individual' => 10, 'double_without_tin' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertTrue(WhtCategory::where('name', 'Hire of vehicles')->exists());

        $this->put(route('withholding-tax.settings.update'), [
            'business_type' => 'company', 'small_company' => 1, 'small_company_threshold' => 2000000,
        ])->assertSessionHasNoErrors();
        $settings = WithholdingTax::settings($this->tenant->id);
        $this->assertTrue($settings['small_company']);
        $this->assertEqualsWithDelta(2000000, $settings['small_company_threshold'], 0.001);
    }

    public function test_setup_needs_the_permissions_and_stays_within_the_business(): void
    {
        [$other] = $this->createTenantWithSubscription();
        $theirs = WhtCategory::withoutGlobalScopes()->where('tenant_id', $other->id)->where('code', 'rent')->firstOrFail();

        $this->createAuthenticatedUser(['view withholding-tax']);
        $this->get(route('withholding-tax.setup'))->assertOk()->assertDontSee('Save rates');
        $this->put(route('withholding-tax.rates.update'), ['rates' => []])->assertForbidden();
        $this->put(route('withholding-tax.settings.update'), ['business_type' => 'company', 'small_company_threshold' => 0])->assertForbidden();

        $this->signInFresh([]);
        $this->get(route('withholding-tax.setup'))->assertForbidden();

        // A manager can't change another business's rates by id.
        $this->signInFresh(self::ALL);
        $this->put(route('withholding-tax.rates.update'), ['rates' => [$theirs->id => [
            'name' => 'Hacked', 'rate_company' => 0, 'rate_individual' => 0, 'is_active' => 1,
        ]]]);
        $this->assertEqualsWithDelta(10, (float) $theirs->fresh()->rate_company, 0.001);
        $this->assertNotSame('Hacked', $theirs->fresh()->name);
    }

    public function test_vendor_and_customer_wht_flags_are_saved_web_and_api(): void
    {
        [$other] = $this->createTenantWithSubscription();
        $theirs = WhtCategory::withoutGlobalScopes()->where('tenant_id', $other->id)->firstOrFail();

        $this->createAuthenticatedUser(['create vendors', 'edit vendors', 'create customers']);
        $professional = WhtCategory::where('code', 'professional')->firstOrFail();

        $this->get(route('vendors.create'))->assertOk()->assertSee('Usual WHT transaction type');

        $this->post(route('vendors.store'), [
            'name' => 'Musa Consulting', 'tax_number' => '12345678-0001', 'payee_type' => 'individual',
            'wht_category_id' => $professional->id, 'wht_exempt' => 0, 'payment_terms' => 30,
        ])->assertSessionHasNoErrors();
        $vendor = Vendor::where('name', 'Musa Consulting')->firstOrFail();
        $this->assertSame('individual', $vendor->payee_type);
        $this->assertSame($professional->id, $vendor->wht_category_id);
        $this->assertTrue($vendor->hasTin());

        // Another business's transaction type is refused.
        $this->post(route('vendors.store'), ['name' => 'X', 'wht_category_id' => $theirs->id])->assertSessionHasErrors('wht_category_id');

        $this->postJson('/api/v1/customers', [
            'name' => 'Federal Ministry', 'payee_type' => 'company', 'wht_category_id' => $professional->id,
        ])->assertCreated()->assertJsonPath('data.wht_category_id', $professional->id)->assertJsonPath('data.payee_type', 'company');
    }

    // ── Purchases: we withhold ──────────────────────────────────

    private function category(string $code): WhtCategory
    {
        return WhtCategory::where('tenant_id', $this->tenant->id)->where('code', $code)->firstOrFail();
    }

    private function balance(string $key): float
    {
        $code = AccountCodeService::resolve($this->tenant->id, $key);

        return round((float) ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', $code)->value('current_balance'), 2);
    }

    /** A bill for $net of contract supplies plus 7.5% VAT. */
    private function vatBill(Vendor $vendor, float $net = 1000000, string $date = '2026-09-10'): Bill
    {
        return app(SaveBill::class)->create($this->tenant->id, [
            'vendor_id' => $vendor->id, 'bill_date' => $date, 'due_date' => $date,
            'items' => [['description' => 'Supply of materials', 'quantity' => 1, 'unit_price' => $net, 'tax_rate' => 7.5]],
        ], $this->user->id);
    }

    private function vendor(array $attributes = []): Vendor
    {
        return Vendor::factory()->create($attributes + [
            'tenant_id' => $this->tenant->id, 'name' => 'Dangote Supplies Ltd', 'tax_number' => '01234567-0001',
            'payee_type' => 'company',
        ]);
    }

    /** @return array<string, float> debit - credit per account code */
    private function journalLines(string $type, int $id): array
    {
        $journal = Journal::with('entries.account')->where('reference_type', $type)->where('reference_id', $id)
            ->where('status', 'posted')->firstOrFail();
        app(JournalService::class)->assertBalanced($journal);

        $lines = [];
        foreach ($journal->entries as $entry) {
            $code = $entry->account->account_code;
            $lines[$code] = round(($lines[$code] ?? 0) + (float) $entry->debit - (float) $entry->credit, 2);
        }

        return $lines;
    }

    public function test_paying_a_vat_bill_withholds_2_percent_of_the_amount_before_vat(): void
    {
        $this->createAuthenticatedUser(self::PAY);
        $bank = Bank::factory()->create(['tenant_id' => $this->tenant->id, 'current_balance' => 2000000]);
        $vendor = $this->vendor();
        $bill = $this->vatBill($vendor);
        $this->assertEqualsWithDelta(1075000, (float) $bill->total, 0.001);
        $this->get(route('payments-made.create', ['bill_id' => $bill->id]))->assertOk()->assertSee('Deduct withholding tax (WHT)');

        // Web form: the net paid and the transaction type; the WHT is worked out.
        $this->post(route('payments-made.store'), [
            'vendor_id' => $vendor->id, 'bill_id' => $bill->id, 'payment_date' => '2026-09-15',
            'amount' => 1055000, 'payment_method' => 'bank_transfer', 'bank_id' => $bank->id,
            'wht_category_id' => $this->category('supply_goods')->id,
        ])->assertSessionHasNoErrors();

        $payment = PaymentMade::firstOrFail();
        $this->assertEqualsWithDelta(1055000, (float) $payment->amount, 0.001);
        $this->assertEqualsWithDelta(20000, (float) $payment->wht_amount, 0.001);
        $this->assertEqualsWithDelta(1000000, (float) $payment->wht_base, 0.001);
        $this->assertEqualsWithDelta(2, (float) $payment->wht_rate, 0.001);
        $this->assertSame(WithholdingTax::AUTHORITY_FEDERAL, $payment->wht_authority);

        // Net + WHT settles the bill.
        $bill->refresh();
        $this->assertSame('paid', $bill->status);
        $this->assertEqualsWithDelta(0, (float) $bill->balance_due, 0.001);
        $this->assertEqualsWithDelta(1075000, (float) $bill->amount_paid, 0.001);

        // Bank goes down by the net only.
        $this->assertEqualsWithDelta(945000, (float) $bank->fresh()->current_balance, 0.001);

        // Dr payables 1,075,000 / Cr bank 1,055,000 / Cr WHT payable 20,000.
        $lines = $this->journalLines(PaymentMade::class, $payment->id);
        $this->assertEqualsWithDelta(1075000, $lines[AccountCodeService::resolve($this->tenant->id, 'accounts_payable')], 0.001);
        $this->assertEqualsWithDelta(-1055000, $lines[AccountCodeService::resolve($this->tenant->id, 'checking')], 0.001);
        $this->assertEqualsWithDelta(-20000, $lines[AccountCodeService::resolve($this->tenant->id, 'wht_payable')], 0.001);
        $this->assertEqualsWithDelta(20000, $this->balance('wht_payable'), 0.001);
        $this->assertEqualsWithDelta(0, $this->balance('accounts_payable'), 0.001);

        $this->get(route('payments-made.show', $payment))->assertOk()->assertSee('WHT withheld')->assertSee('Nigeria Revenue Service');
        $this->createUserForTenant($this->tenant, ['view bills']);
        $this->actingAs(User::where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail())
            ->get(route('bills.show', $bill))->assertOk()->assertSee('WHT withheld');
    }

    public function test_api_payments_take_wht_the_same_way(): void
    {
        [$otherTenant] = $this->createTenantWithSubscription();
        $theirs = WhtCategory::withoutGlobalScopes()->where('tenant_id', $otherTenant->id)->firstOrFail();
        $this->createAuthenticatedUser(self::PAY);
        $vendor = $this->vendor();
        $bill = $this->vatBill($vendor);

        $this->postJson('/api/v1/payments-made', [
            'vendor_id' => $vendor->id, 'bill_id' => $bill->id, 'payment_date' => '2026-09-15',
            'amount' => 1055000, 'payment_method' => 'cash', 'wht_category_id' => $this->category('supply_goods')->id,
        ])->assertCreated()
            ->assertJsonPath('data.wht_amount', 20000)
            ->assertJsonPath('data.wht_authority', 'nrs');

        $this->assertSame('paid', $bill->fresh()->status);
        $this->assertEqualsWithDelta(20000, $this->balance('wht_payable'), 0.001);

        // A WHT amount without a type, for a vendor with no usual type, is refused.
        $other = $this->vendor(['name' => 'No Type Ltd']);
        $this->postJson('/api/v1/payments-made', [
            'vendor_id' => $other->id, 'payment_date' => '2026-09-15', 'amount' => 1000, 'payment_method' => 'cash', 'wht_amount' => 50,
        ])->assertStatus(422)->assertJsonValidationErrors('wht_category_id');

        // Another business's transaction type is refused.
        $this->postJson('/api/v1/payments-made', [
            'vendor_id' => $vendor->id, 'payment_date' => '2026-09-15', 'amount' => 1000, 'payment_method' => 'cash', 'wht_category_id' => $theirs->id,
        ])->assertStatus(422)->assertJsonValidationErrors('wht_category_id');
    }

    public function test_an_individual_without_a_tin_pays_double_to_the_state_irs(): void
    {
        $this->createAuthenticatedUser(self::PAY);
        $vendor = $this->vendor(['name' => 'Aisha Bello', 'tax_number' => null, 'payee_type' => 'individual', 'state' => 'Kano',
            'wht_category_id' => $this->category('professional')->id]);
        $bill = $this->vatBill($vendor, 500000); // 537,500 with VAT

        // The vendor's usual type is used; 5% doubled to 10% on 500,000.
        $this->post(route('payments-made.store'), [
            'vendor_id' => $vendor->id, 'bill_id' => $bill->id, 'payment_date' => '2026-09-15',
            'amount' => 487500, 'payment_method' => 'cash', 'wht_amount' => 50000,
        ])->assertSessionHasNoErrors();

        $payment = PaymentMade::firstOrFail();
        $this->assertEqualsWithDelta(10, (float) $payment->wht_rate, 0.001);
        $this->assertEqualsWithDelta(500000, (float) $payment->wht_base, 0.001);
        $this->assertSame(WithholdingTax::AUTHORITY_STATE, $payment->wht_authority);
        $this->assertSame('Kano', $payment->wht_state);
        $this->assertSame('paid', $bill->fresh()->status);
    }

    public function test_wht_cannot_overpay_a_bill_or_be_taken_from_an_exempt_vendor(): void
    {
        $this->createAuthenticatedUser(self::PAY);
        $vendor = $this->vendor();
        $bill = $this->vatBill($vendor);
        $supply = $this->category('supply_goods')->id;
        $form = fn (array $extra) => $extra + [
            'vendor_id' => $vendor->id, 'bill_id' => $bill->id, 'payment_date' => '2026-09-15', 'payment_method' => 'cash',
        ];

        // 1,060,000 paid + 20,000 WHT is more than the 1,075,000 owed.
        $this->post(route('payments-made.store'), $form(['amount' => 1060000, 'wht_category_id' => $supply, 'wht_amount' => 20000]))
            ->assertSessionHasErrors('amount');
        // WHT can't be more than the amount it is worked out on.
        $this->post(route('payments-made.store'), $form(['amount' => 1000, 'wht_category_id' => $supply, 'wht_amount' => 50000]))
            ->assertSessionHasErrors('wht_amount');

        $vendor->update(['wht_exempt' => true]);
        $this->post(route('payments-made.store'), $form(['amount' => 1055000, 'wht_category_id' => $supply]))
            ->assertSessionHasErrors('wht_amount');
        $this->assertSame(0, PaymentMade::count());
    }

    public function test_part_payments_with_wht_leave_the_right_balance(): void
    {
        $this->createAuthenticatedUser(self::PAY);
        $vendor = $this->vendor();
        $bill = $this->vatBill($vendor);
        $supply = $this->category('supply_goods')->id;

        // Half the bill: 537,500 settled = 527,500 paid + 10,000 WHT (2% of 500,000).
        $this->post(route('payments-made.store'), [
            'vendor_id' => $vendor->id, 'bill_id' => $bill->id, 'payment_date' => '2026-09-15',
            'amount' => 527500, 'payment_method' => 'cash', 'wht_category_id' => $supply, 'wht_amount' => 10000,
        ])->assertSessionHasNoErrors();
        $bill->refresh();
        $this->assertSame('partial', $bill->status);
        $this->assertEqualsWithDelta(537500, (float) $bill->balance_due, 0.001);
        $this->assertEqualsWithDelta(500000, (float) PaymentMade::firstOrFail()->wht_base, 0.001);

        // The rest, worked out: 527,500 paid settles the remaining 537,500.
        $this->post(route('payments-made.store'), [
            'vendor_id' => $vendor->id, 'bill_id' => $bill->id, 'payment_date' => '2026-09-20',
            'amount' => 527500, 'payment_method' => 'cash', 'wht_category_id' => $supply,
        ])->assertSessionHasNoErrors();
        $this->assertSame('paid', $bill->fresh()->status);
        $this->assertEqualsWithDelta(20000, $this->balance('wht_payable'), 0.001);

        // Editing the money paid keeps the WHT and still can't overpay.
        $first = PaymentMade::orderBy('id')->firstOrFail();
        $edit = ['payment_date' => '2026-09-15', 'payment_method' => 'cash'];
        $this->put(route('payments-made.update', $first), $edit + ['amount' => 527500.01])->assertSessionHasErrors('amount');
        $this->put(route('payments-made.update', $first), $edit + ['amount' => 517500])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(10000, (float) $bill->fresh()->balance_due, 0.001);
        $this->assertEqualsWithDelta(20000, $this->balance('wht_payable'), 0.001);
        $this->assertEqualsWithDelta(10000, $this->balance('accounts_payable'), 0.001);
    }

    public function test_small_company_exemption_and_no_wht_without_a_type(): void
    {
        $this->createAuthenticatedUser(self::PAY);
        $tenant = Tenant::findOrFail($this->tenant->id);
        $tenant->update(['settings' => ['wht' => ['small_company' => true, 'small_company_threshold' => 2000000]]]);
        $vendor = $this->vendor(['wht_category_id' => $this->category('services')->id]);
        $form = fn (array $extra) => $extra + ['vendor_id' => $vendor->id, 'payment_date' => '2026-09-15', 'payment_method' => 'cash'];

        // Within N2m this month to a vendor with a TIN: no WHT is worked out.
        $this->post(route('payments-made.store'), $form(['amount' => 1500000, 'wht_category_id' => $this->category('services')->id]))
            ->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(0, (float) PaymentMade::latest('id')->first()->wht_amount, 0.001);

        // Over N2m in the month: WHT applies again (2%, no bill so no VAT).
        $this->post(route('payments-made.store'), $form(['amount' => 980000, 'wht_category_id' => $this->category('services')->id]))
            ->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(20000, (float) PaymentMade::latest('id')->first()->wht_amount, 0.001);

        // No type and no amount: an ordinary payment.
        $this->post(route('payments-made.store'), $form(['amount' => 1000]))->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(0, (float) PaymentMade::latest('id')->first()->wht_amount, 0.001);
        $this->assertEqualsWithDelta(20000, $this->balance('wht_payable'), 0.001);
    }

    public function test_deleting_a_payment_reverses_its_wht(): void
    {
        $this->createAuthenticatedUser(self::PAY);
        $bank = Bank::factory()->create(['tenant_id' => $this->tenant->id, 'current_balance' => 2000000]);
        $vendor = $this->vendor();
        $bill = $this->vatBill($vendor);

        $this->postJson('/api/v1/payments-made', [
            'vendor_id' => $vendor->id, 'bill_id' => $bill->id, 'payment_date' => '2026-09-15', 'amount' => 1055000,
            'payment_method' => 'bank_transfer', 'bank_id' => $bank->id, 'wht_category_id' => $this->category('supply_goods')->id,
        ])->assertCreated();
        $payment = PaymentMade::firstOrFail();

        $this->delete(route('payments-made.destroy', $payment))->assertSessionHasNoErrors();

        $bill->refresh();
        $this->assertSame('unpaid', $bill->status);
        $this->assertEqualsWithDelta(1075000, (float) $bill->balance_due, 0.001);
        $this->assertEqualsWithDelta(0, $this->balance('wht_payable'), 0.001);
        $this->assertEqualsWithDelta(1075000, $this->balance('accounts_payable'), 0.001);
        $this->assertEqualsWithDelta(2000000, (float) $bank->fresh()->current_balance, 0.001);
        // The original journal is kept and reversed.
        $this->assertSame(2, Journal::where('reference_type', PaymentMade::class)->where('reference_id', $payment->id)->count());
    }

    // ── Sales: the customer withholds ───────────────────────────

    /** An invoice for 1,000,000 plus 7.5% VAT. */
    private function vatInvoice(Customer $customer, string $number = 'INV-000101'): Invoice
    {
        return Invoice::factory()->sent()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'invoice_number' => $number,
            'subtotal' => 1000000, 'tax_amount' => 75000, 'discount_amount' => 0, 'total' => 1075000,
            'amount_paid' => 0, 'balance_due' => 1075000,
        ]);
    }

    private function customer(array $attributes = []): Customer
    {
        return Customer::factory()->create($attributes + ['tenant_id' => $this->tenant->id, 'name' => 'Kano State Ministry of Works']);
    }

    /** Record a 1,055,000 receipt on a 1,075,000 invoice with 20,000 WHT deducted. */
    private function receiveWithWht(Customer $customer, Invoice $invoice, array $extra = []): PaymentReceived
    {
        $this->postJson('/api/v1/payments-received', $extra + [
            'customer_id' => $customer->id, 'invoice_id' => $invoice->id, 'payment_date' => '2026-09-20',
            'amount' => 1055000, 'payment_method' => 'cash', 'wht_amount' => 20000,
            'wht_category_id' => $this->category('supply_goods')->id,
        ])->assertCreated();

        return PaymentReceived::latest('id')->firstOrFail();
    }

    public function test_a_customer_payment_net_of_wht_settles_the_invoice_and_books_a_wht_receivable(): void
    {
        $this->createAuthenticatedUser(self::RECEIVE);
        Tenant::whereKey($this->tenant->id)->update(['tax_number' => '98765432-0001']); // our TIN: no doubling
        $bank = Bank::factory()->create(['tenant_id' => $this->tenant->id, 'current_balance' => 0]);
        $customer = $this->customer();
        $invoice = $this->vatInvoice($customer);

        $this->get(route('payments-received.create', ['invoice_id' => $invoice->id]))->assertOk()->assertSee('The customer deducted withholding tax (WHT)');

        // Web form with only the type: WHT worked out at our (company) rate, 2% of 1,000,000.
        $this->post(route('payments-received.store'), [
            'customer_id' => $customer->id, 'invoice_id' => $invoice->id, 'payment_date' => '2026-09-20',
            'amount' => 1055000, 'payment_method' => 'bank_transfer', 'bank_id' => $bank->id,
            'wht_category_id' => $this->category('supply_goods')->id,
        ])->assertSessionHasNoErrors();

        $payment = PaymentReceived::firstOrFail();
        $this->assertEqualsWithDelta(20000, (float) $payment->wht_amount, 0.001);
        $this->assertEqualsWithDelta(1000000, (float) $payment->wht_base, 0.001);
        $this->assertSame(PaymentReceived::WHT_OUTSTANDING, $payment->whtStatus());

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertEqualsWithDelta(0, (float) $invoice->balance_due, 0.001);
        $this->assertEqualsWithDelta(1055000, (float) $bank->fresh()->current_balance, 0.001);

        // Dr bank 1,055,000 / Dr WHT receivable 20,000 / Cr receivables 1,075,000.
        $lines = $this->journalLines(PaymentReceived::class, $payment->id);
        $this->assertEqualsWithDelta(1055000, $lines[AccountCodeService::resolve($this->tenant->id, 'checking')], 0.001);
        $this->assertEqualsWithDelta(20000, $lines[AccountCodeService::resolve($this->tenant->id, 'wht_receivable')], 0.001);
        $this->assertEqualsWithDelta(-1075000, $lines[AccountCodeService::resolve($this->tenant->id, 'accounts_receivable')], 0.001);
        $this->assertEqualsWithDelta(20000, $this->balance('wht_receivable'), 0.001);

        $this->get(route('payments-received.show', $payment))->assertOk()->assertSee('WHT deducted by customer')->assertSee('Not received yet');
    }

    public function test_api_customer_payments_take_wht_and_refuse_it_on_deposits(): void
    {
        $this->createAuthenticatedUser(self::RECEIVE);
        $customer = $this->customer();
        $invoice = $this->vatInvoice($customer);

        // An amount as given, without a type: the rate is worked out from it.
        $this->postJson('/api/v1/payments-received', [
            'customer_id' => $customer->id, 'invoice_id' => $invoice->id, 'payment_date' => '2026-09-20',
            'amount' => 1055000, 'payment_method' => 'cash', 'wht_amount' => 20000,
        ])->assertCreated()->assertJsonPath('data.wht_amount', 20000)->assertJsonPath('data.wht_status', 'outstanding');
        $this->assertEqualsWithDelta(2, (float) PaymentReceived::firstOrFail()->wht_rate, 0.001);
        $this->assertSame('paid', $invoice->fresh()->status);

        // Too much: 1,060,000 + 20,000 is more than owed on a new invoice.
        $second = $this->vatInvoice($customer, 'INV-000102');
        $this->postJson('/api/v1/payments-received', [
            'customer_id' => $customer->id, 'invoice_id' => $second->id, 'payment_date' => '2026-09-20',
            'amount' => 1060000, 'payment_method' => 'cash', 'wht_amount' => 20000,
        ])->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->postJson('/api/v1/payments-received', [
            'customer_id' => $customer->id, 'payment_date' => '2026-09-20', 'amount' => 5000,
            'payment_method' => 'cash', 'is_deposit' => true, 'wht_amount' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('wht_amount');
        $this->assertSame(1, PaymentReceived::count());
    }

    public function test_wht_credit_note_lifecycle_received_then_used_against_income_tax(): void
    {
        $this->createAuthenticatedUser(array_merge(self::RECEIVE, self::ALL));
        $customer = $this->customer();
        $first = $this->receiveWithWht($customer, $this->vatInvoice($customer));
        $second = $this->receiveWithWht($customer, $this->vatInvoice($customer, 'INV-000102'));
        $this->assertEqualsWithDelta(40000, $this->balance('wht_receivable'), 0.001);

        // Nothing in hand yet: can't be used.
        $this->post(route('withholding-tax.credit-notes.utilise'), ['payment_ids' => [$first->id], 'utilisation_date' => '2026-12-31'])
            ->assertSessionHasErrors('payment_ids');

        $this->post(route('withholding-tax.credit-notes.store', $first), [
            'wht_credit_note_number' => 'NRS-WHT-0001', 'wht_credit_note_date' => '2026-10-15',
        ])->assertSessionHasNoErrors();
        $first->refresh();
        $this->assertSame(PaymentReceived::WHT_RECEIVED, $first->whtStatus());
        $this->assertSame('2026-10-15', $first->wht_credit_note_date->format('Y-m-d'));

        $this->get(route('withholding-tax.receivable', ['start_date' => '2026-01-01', 'end_date' => '2026-12-31']))
            ->assertOk()->assertSee('NRS-WHT-0001')->assertSee('Kano State Ministry of Works');

        // Use it: Dr income tax payable 20,000 / Cr WHT receivable 20,000.
        $this->post(route('withholding-tax.credit-notes.utilise'), [
            'payment_ids' => [$first->id], 'utilisation_date' => '2026-12-31', 'reference' => 'CIT 2026',
        ])->assertSessionHasNoErrors();
        $first->refresh();
        $this->assertSame(PaymentReceived::WHT_UTILISED, $first->whtStatus());
        $this->assertEqualsWithDelta(20000, $this->balance('wht_receivable'), 0.001);
        $this->assertEqualsWithDelta(-20000, $this->balance('income_tax_payable'), 0.001);
        $utilisation = $first->whtUtilisation;
        $lines = $this->journalLines(WhtCreditUtilisation::class, $utilisation->id);
        $this->assertEqualsWithDelta(20000, $lines[AccountCodeService::resolve($this->tenant->id, 'income_tax_payable')], 0.001);
        $this->assertEqualsWithDelta(-20000, $lines[AccountCodeService::resolve($this->tenant->id, 'wht_receivable')], 0.001);

        // Used once only, and its credit note can't be changed after.
        $this->post(route('withholding-tax.credit-notes.utilise'), ['payment_ids' => [$first->id], 'utilisation_date' => '2026-12-31'])
            ->assertSessionHasErrors('payment_ids');
        $this->post(route('withholding-tax.credit-notes.store', $first), ['wht_credit_note_number' => 'X', 'wht_credit_note_date' => '2026-10-15'])
            ->assertSessionHasErrors('wht_credit_note_number');

        // The report splits it out.
        $csv = $this->get(route('withholding-tax.receivable.export', ['format' => 'csv', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('PAY-000001,INV-000101,"Supply of goods (contract supplies, not made by the supplier)",1000000.00,2.00,20000.00,Utilised,NRS-WHT-0001,2026-10-15', $csv);
        $this->assertStringContainsString('20000.00,Outstanding', $csv);
        $this->get(route('withholding-tax.receivable.export', ['format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');

        // A utilised payment can't be deleted; one still outstanding can, and its WHT is reversed.
        $this->deleteJson('/api/v1/payments-received/'.$first->id)->assertStatus(422);
        $this->deleteJson('/api/v1/payments-received/'.$second->id)->assertOk();
        $this->assertEqualsWithDelta(0, $this->balance('wht_receivable'), 0.001);
        $this->assertSame('unpaid', Invoice::where('invoice_number', 'INV-000102')->value('status'));
        $this->assertEqualsWithDelta(1075000, (float) Invoice::where('invoice_number', 'INV-000102')->value('balance_due'), 0.001);
    }

    public function test_wht_credit_notes_need_permission_and_stay_within_the_business(): void
    {
        [$otherTenant] = $this->createTenantWithSubscription();
        $theirCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $theirPayment = PaymentReceived::withoutEvents(fn () => PaymentReceived::create([
            'tenant_id' => $otherTenant->id, 'customer_id' => $theirCustomer->id, 'payment_number' => 'PAY-900001',
            'payment_date' => '2026-09-20', 'amount' => 1000, 'payment_method' => 'cash', 'wht_amount' => 50,
            'wht_credit_note_number' => 'THEIRS', 'wht_credit_note_date' => '2026-09-30',
        ]));

        $this->createAuthenticatedUser(array_merge(self::RECEIVE, ['view withholding-tax']));
        $customer = $this->customer();
        $mine = $this->receiveWithWht($customer, $this->vatInvoice($customer));

        // View only: can see the report, not record or use credit notes.
        $this->get(route('withholding-tax.receivable'))->assertOk()->assertDontSee('THEIRS');
        $this->post(route('withholding-tax.credit-notes.store', $mine), ['wht_credit_note_number' => 'A', 'wht_credit_note_date' => '2026-10-01'])->assertForbidden();

        $this->signInFresh(self::ALL);
        $this->post(route('withholding-tax.credit-notes.store', $theirPayment), ['wht_credit_note_number' => 'A', 'wht_credit_note_date' => '2026-10-01'])->assertNotFound();
        $this->post(route('withholding-tax.credit-notes.utilise'), ['payment_ids' => [$theirPayment->id], 'utilisation_date' => '2026-12-31'])
            ->assertSessionHasErrors('payment_ids.0');
        $this->assertNull($theirPayment->fresh()->wht_utilisation_id);
    }
}
