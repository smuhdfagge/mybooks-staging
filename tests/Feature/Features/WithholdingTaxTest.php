<?php

namespace Tests\Feature\Features;

use App\Actions\Bills\SaveBill;
use App\Actions\Payments\ApplySupplierAdvance;
use App\Actions\Payments\RecordPaymentMade;
use App\Actions\VendorCredits\ApplyVendorCredit;
use App\Actions\VendorCredits\SaveVendorCredit;
use App\Models\Bank;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\StatutoryRemittance;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WhtCategory;
use App\Models\WhtCreditUtilisation;
use App\Services\AccountCodeService;
use App\Services\Accounting\FinancialStatements;
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

        // Each has its own account, clear of the codes main already uses
        // (1420 Supplier Advances, 2370-2390 NHF/NSITF/ITF).
        $codes = collect(['wht_receivable', 'wht_payable', 'income_tax_payable', 'supplier_advances', 'nhf_payable', 'nsitf_payable', 'itf_payable', 'vat_payable'])
            ->mapWithKeys(fn ($key) => [$key => AccountCodeService::resolve($tenant->id, $key)]);
        $this->assertSame($codes->count(), $codes->unique()->count(), 'Account codes clash: '.json_encode($codes));
        $this->assertSame(['1430', '2420', '2430'], [$codes['wht_receivable'], $codes['wht_payable'], $codes['income_tax_payable']]);
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
        $this->assertStringContainsString('PAY-000001,INV-000101,"Supply of goods (contract supplies, not made by the supplier)",1000000.00,2.00,20000.00,"Used against income tax",NRS-WHT-0001,2026-10-15', $csv);
        $this->assertStringContainsString('20000.00,"Awaiting credit note"', $csv);
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

    // ── Advances and supplier credits with WHT ──────────────────

    private function assertBooksBalance(): void
    {
        $tb = app(FinancialStatements::class)->trialBalance($this->tenant->id, '2026-12-31');
        $this->assertEqualsWithDelta($tb->sum('total_debit'), $tb->sum('total_credit'), 0.001);
    }

    public function test_an_advance_with_wht_gives_the_supplier_credit_for_the_gross(): void
    {
        $this->createAuthenticatedUser(array_merge(self::PAY, ['view vendors', 'create vendors']));
        $bank = Bank::factory()->create(['tenant_id' => $this->tenant->id, 'current_balance' => 1000000]);
        $vendor = $this->vendor();
        $this->get(route('supplier-advances.create'))->assertOk()->assertSee('WHT transaction type');

        // 49,000 paid, 1,000 WHT (2% of 50,000: no bill yet, so no VAT to take out).
        $this->post(route('supplier-advances.store'), [
            'vendor_id' => $vendor->id, 'payment_date' => '2026-09-01', 'amount' => 49000,
            'payment_method' => 'bank_transfer', 'bank_id' => $bank->id, 'wht_category_id' => $this->category('services')->id,
        ])->assertSessionHasNoErrors();
        $advance = PaymentMade::where('is_advance', true)->firstOrFail();
        $this->assertEqualsWithDelta(1000, (float) $advance->wht_amount, 0.001);
        $this->assertEqualsWithDelta(50000, (float) $advance->unused_amount, 0.001);
        $this->assertEqualsWithDelta(951000, (float) $bank->fresh()->current_balance, 0.001);

        // Dr supplier advances 50,000 / Cr bank 49,000 / Cr WHT payable 1,000.
        $lines = $this->journalLines(PaymentMade::class, $advance->id);
        $this->assertEqualsWithDelta(50000, $lines[AccountCodeService::resolve($this->tenant->id, 'supplier_advances')], 0.001);
        $this->assertEqualsWithDelta(-49000, $lines[AccountCodeService::resolve($this->tenant->id, 'checking')], 0.001);
        $this->assertEqualsWithDelta(-1000, $lines[AccountCodeService::resolve($this->tenant->id, 'wht_payable')], 0.001);
        $this->get(route('supplier-advances.show', $advance))->assertOk()->assertSee('Plus WHT withheld');

        // Editing the money paid keeps the WHT in the credit.
        $this->put(route('payments-made.update', $advance), [
            'payment_date' => '2026-09-01', 'amount' => 39000, 'payment_method' => 'bank_transfer', 'bank_id' => $bank->id,
        ])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(40000, (float) $advance->fresh()->unused_amount, 0.001);
        $this->assertEqualsWithDelta(40000, $this->balance('supplier_advances'), 0.001);
        $this->assertBooksBalance();
    }

    public function test_a_bill_settled_by_an_advance_a_supplier_credit_and_a_payment_with_wht(): void
    {
        $this->createAuthenticatedUser(self::PAY);
        $vendor = $this->vendor();
        $services = $this->category('services')->id;

        $advance = app(RecordPaymentMade::class)->handle($this->tenant->id, [
            'vendor_id' => $vendor->id, 'payment_date' => '2026-09-01', 'amount' => 49000, 'payment_method' => 'cash',
            'is_advance' => true, 'wht_category_id' => $services,
        ], $this->user->id);

        $bill = $this->vatBill($vendor); // 1,075,000
        app(ApplySupplierAdvance::class)->handle($advance, $bill, 50000, '2026-09-11');
        $credit = app(SaveVendorCredit::class)->create($this->tenant->id, [
            'vendor_id' => $vendor->id, 'bill_id' => $bill->id, 'credit_date' => '2026-09-12', 'status' => 'open',
            'reason' => 'price_adjustment', 'items' => [['description' => 'Discount agreed', 'quantity' => 1, 'unit_price' => 100000, 'tax_rate' => 7.5]],
        ], $this->user->id);
        app(ApplyVendorCredit::class)->handle($credit, $bill->fresh(), 107500, '2026-09-12');

        $bill->refresh();
        $this->assertEqualsWithDelta(917500, (float) $bill->balance_due, 0.001);

        // The rest: WHT on its share before VAT (917,500 x 1,000,000 / 1,075,000 x 2% = 17,069.77).
        $this->post(route('payments-made.store'), [
            'vendor_id' => $vendor->id, 'bill_id' => $bill->id, 'payment_date' => '2026-09-20',
            'amount' => 900430.23, 'payment_method' => 'cash', 'wht_category_id' => $this->category('supply_goods')->id,
        ])->assertSessionHasNoErrors();
        $payment = PaymentMade::latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(17069.77, (float) $payment->wht_amount, 0.001);

        $bill->refresh();
        $this->assertSame('paid', $bill->status);
        $this->assertEqualsWithDelta(1075000, (float) $bill->amount_paid, 0.001);
        $this->assertEqualsWithDelta(0, (float) $bill->balance_due, 0.001);
        $this->assertEqualsWithDelta(0, $this->balance('accounts_payable'), 0.001);
        $this->assertEqualsWithDelta(0, $this->balance('supplier_advances'), 0.001);
        $this->assertEqualsWithDelta(18069.77, $this->balance('wht_payable'), 0.001);
        $this->assertBooksBalance();

        // Changing the bill can't take its total below what is settled.
        $this->assertEqualsWithDelta(1075000, $bill->settledByPayments() + (float) $bill->vendorCreditApplications()->sum('amount'), 0.001);
    }

    // ── Schedule and remittance ─────────────────────────────────

    /** WHT payments in September: two to a company (NRS), one to an individual in Kano (state IRS), one in October. */
    private function septemberDeductions(): void
    {
        $company = $this->vendor();
        $person = $this->vendor(['name' => 'Musa Ibrahim', 'payee_type' => 'individual', 'state' => 'Kano', 'tax_number' => null]);
        $supply = $this->category('supply_goods')->id;
        $pay = fn (Vendor $v, string $date, float $amount, ?float $wht = null) => app(RecordPaymentMade::class)->handle($this->tenant->id, [
            'vendor_id' => $v->id, 'payment_date' => $date, 'amount' => $amount, 'payment_method' => 'cash',
            'wht_category_id' => $this->category($v->is($person) ? 'professional' : 'supply_goods')->id, 'wht_amount' => $wht,
        ], $this->user->id);

        $this->post(route('payments-made.store'), [
            'vendor_id' => $company->id, 'bill_id' => $this->vatBill($company)->id, 'payment_date' => '2026-09-15',
            'amount' => 1055000, 'payment_method' => 'cash', 'wht_category_id' => $supply,
        ])->assertSessionHasNoErrors();                    // 20,000
        $pay($company, '2026-09-25', 98000);               // 2,000 (2% of 100,000)
        $pay($person, '2026-09-28', 90000);                // 10,000 (professional, 5% doubled: no TIN)
        $pay($company, '2026-10-02', 49000);               // 1,000 in October
    }

    public function test_the_monthly_schedule_lists_wht_by_authority_and_vendor(): void
    {
        $this->createAuthenticatedUser(array_merge(self::PAY, ['view withholding-tax']));
        Tenant::whereKey($this->tenant->id)->update(['tax_number' => '98765432-0001']);
        $this->septemberDeductions();
        $this->assertEqualsWithDelta(33000, $this->balance('wht_payable'), 0.001);

        $this->get(route('withholding-tax.schedule', ['month' => '2026-09']))->assertOk()
            ->assertSee('Nigeria Revenue Service (NRS)')->assertSee('Kano Internal Revenue Service')
            ->assertSee('Dangote Supplies Ltd')->assertSee('01234567-0001')->assertSee('Musa Ibrahim')->assertSee('No TIN')
            ->assertSee('Due by 21 Oct 2026')->assertSee('Due by 30 Oct 2026')
            ->assertSeeInOrder(['WHT deducted this month', '32,000.00'])
            ->assertDontSee('Record WHT payment'); // can't remit

        $csv = $this->get(route('withholding-tax.schedule.export', ['month' => '2026-09', 'format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('"Nigeria Revenue Service (NRS)","Dangote Supplies Ltd",01234567-0001,Company', $csv);
        $this->assertStringContainsString('2026-09-15,PM-000001,BILL-', $csv);
        $this->assertStringContainsString(',1000000.00,2.00,20000.00', $csv);
        $this->assertStringContainsString('"Kano Internal Revenue Service","Musa Ibrahim",,Individual,', $csv);
        $this->assertStringContainsString('100000.00,10.00,10000.00', $csv);
        $this->assertStringNotContainsString('2026-10-02', $csv);

        $this->get(route('withholding-tax.schedule.export', ['month' => '2026-09', 'format' => 'pdf']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('withholding-tax.schedule', ['month' => '2026-08']))->assertOk()->assertSee('No WHT was deducted from vendors in August 2026');
    }

    public function test_remitting_wht_posts_dr_payable_cr_bank_and_can_be_undone(): void
    {
        $this->createAuthenticatedUser(array_merge(self::PAY, self::ALL));
        $bank = Bank::factory()->create(['tenant_id' => $this->tenant->id, 'current_balance' => 100000]);
        $this->septemberDeductions();

        $this->get(route('withholding-tax.schedule', ['month' => '2026-09']))->assertOk()->assertSee('Record WHT payment');

        $form = ['period' => '2026-09', 'authority' => 'nrs', 'amount' => 22000, 'paid_on' => '2026-10-02',
            'payment_method' => 'bank_transfer', 'bank_id' => $bank->id, 'reference' => 'NRS-RCPT-77'];
        $this->post(route('withholding-tax.remittances.store'), $form)->assertSessionHasNoErrors();

        $remittance = StatutoryRemittance::firstOrFail();
        $this->assertSame('wht', $remittance->body);
        $this->assertSame('Nigeria Revenue Service (NRS)', $remittance->paid_to);
        $this->assertSame('2026-09-01', $remittance->period_start->format('Y-m-d'));
        $this->assertEqualsWithDelta(11000, $this->balance('wht_payable'), 0.001);
        $this->assertEqualsWithDelta(78000, (float) $bank->fresh()->current_balance, 0.001);

        // Dr WHT payable 22,000 / Cr bank 22,000.
        $lines = $this->journalLines(StatutoryRemittance::class, $remittance->id);
        $this->assertEqualsWithDelta(22000, $lines[AccountCodeService::resolve($this->tenant->id, 'wht_payable')], 0.001);
        $this->assertEqualsWithDelta(-22000, $lines[AccountCodeService::resolve($this->tenant->id, 'checking')], 0.001);
        $this->assertSame($remittance->journal_id, Journal::where('reference_type', StatutoryRemittance::class)->value('id'));

        // The schedule shows the NRS paid and Kano still owed.
        $this->get(route('withholding-tax.schedule', ['month' => '2026-09']))->assertOk()
            ->assertSee('Paid in full')->assertSee('Still to pay ₦10,000.00', false)->assertSee('NRS-RCPT-77');

        // Not more than is owed; a state needs its name.
        $this->post(route('withholding-tax.remittances.store'), ['amount' => 11000.01] + $form)->assertSessionHasErrors('amount');
        $this->post(route('withholding-tax.remittances.store'), ['authority' => 'state', 'state' => ''] + $form)->assertSessionHasErrors('state');
        $this->post(route('withholding-tax.remittances.store'), ['authority' => 'state', 'state' => 'Kano', 'amount' => 10000] + $form)->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(1000, $this->balance('wht_payable'), 0.001);
        $this->assertSame('Kano Internal Revenue Service', StatutoryRemittance::latest('id')->value('paid_to'));

        // Payroll's remittance list leaves WHT out.
        $this->assertSame(0, StatutoryRemittance::where('body', '!=', 'wht')->count());

        // Deleting one reverses its journal and gives the bank its money back.
        $this->delete(route('withholding-tax.remittances.destroy', $remittance))->assertRedirect(route('withholding-tax.schedule', ['month' => '2026-09']));
        $this->assertNull(StatutoryRemittance::find($remittance->id));
        $this->assertEqualsWithDelta(23000, $this->balance('wht_payable'), 0.001);
        $this->assertEqualsWithDelta(90000, (float) $bank->fresh()->current_balance, 0.001); // 100,000 - 22,000 - 10,000 + 22,000
        $this->assertSame(2, Journal::where('reference_type', StatutoryRemittance::class)->where('reference_id', $remittance->id)->count());
        $this->assertBooksBalance();
    }

    public function test_wht_schedule_and_remittances_need_permission_and_stay_within_the_business(): void
    {
        [$otherTenant] = $this->createTenantWithSubscription();
        $theirRemittance = StatutoryRemittance::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id, 'body' => 'wht', 'account_code' => '2420', 'period_start' => '2026-09-01',
            'period_end' => '2026-09-30', 'paid_to' => 'THEIR NRS', 'wht_authority' => 'nrs', 'amount' => 500, 'paid_on' => '2026-10-01',
        ]);

        $this->createAuthenticatedUser(self::PAY);
        $this->get(route('withholding-tax.schedule'))->assertForbidden();

        $this->signInFresh(array_merge(self::PAY, ['view withholding-tax']));
        $this->septemberDeductions();
        $this->get(route('withholding-tax.schedule', ['month' => '2026-09']))->assertOk()->assertDontSee('THEIR NRS');
        $this->post(route('withholding-tax.remittances.store'), [
            'period' => '2026-09', 'authority' => 'nrs', 'amount' => 100, 'paid_on' => '2026-10-02', 'payment_method' => 'cash',
        ])->assertForbidden();

        $this->signInFresh(self::ALL);
        $this->get(route('withholding-tax.schedule', ['month' => '2026-09']))->assertOk()->assertDontSee('Dangote Supplies Ltd');
        $this->delete(route('withholding-tax.remittances.destroy', $theirRemittance))->assertNotFound();
        $this->assertNotNull(StatutoryRemittance::withoutGlobalScopes()->find($theirRemittance->id));

        // A payroll remittance can't be deleted from here.
        $payroll = StatutoryRemittance::create([
            'tenant_id' => $this->tenant->id, 'body' => 'paye', 'account_code' => '2310', 'period_start' => '2026-09-01',
            'period_end' => '2026-09-30', 'amount' => 100, 'paid_on' => '2026-10-01',
        ]);
        $this->delete(route('withholding-tax.remittances.destroy', $payroll))->assertNotFound();
    }

    public function test_payments_on_the_last_day_of_the_period_are_included(): void
    {
        // Dates are stored with a time on SQLite; a plain "between" dropped the last day.
        $this->createAuthenticatedUser(array_merge(self::PAY, self::RECEIVE, ['view withholding-tax']));
        $vendor = $this->vendor();
        app(RecordPaymentMade::class)->handle($this->tenant->id, [
            'vendor_id' => $vendor->id, 'payment_date' => '2026-09-30', 'amount' => 98000, 'payment_method' => 'cash',
            'wht_category_id' => $this->category('services')->id,
        ], $this->user->id);
        $customer = $this->customer();
        $this->receiveWithWht($customer, $this->vatInvoice($customer), ['payment_date' => '2026-09-30']);

        $this->get(route('withholding-tax.schedule', ['month' => '2026-09']))->assertOk()->assertSee('Dangote Supplies Ltd');
        $this->get(route('withholding-tax.receivable', ['start_date' => '2026-09-01', 'end_date' => '2026-09-30']))
            ->assertOk()->assertSee('Kano State Ministry of Works');
    }

    public function test_a_vendor_payment_cant_be_deleted_once_its_wht_is_paid_over(): void
    {
        $this->createAuthenticatedUser(array_merge(self::PAY, self::ALL, ['delete payments-made']));
        $bank = Bank::factory()->create(['tenant_id' => $this->tenant->id, 'current_balance' => 100000]);
        $this->septemberDeductions();
        $this->post(route('withholding-tax.remittances.store'), ['period' => '2026-09', 'authority' => 'nrs', 'amount' => 22000,
            'paid_on' => '2026-10-02', 'payment_method' => 'bank_transfer', 'bank_id' => $bank->id])->assertSessionHasNoErrors();

        $nrs = PaymentMade::whereDate('payment_date', '2026-09-25')->firstOrFail();
        $state = PaymentMade::whereDate('payment_date', '2026-09-28')->firstOrFail();
        $october = PaymentMade::whereDate('payment_date', '2026-10-02')->firstOrFail();

        // NRS WHT for September is paid over: deleting would take WHT payable below zero.
        $this->delete(route('payments-made.destroy', $nrs))->assertSessionHas('error');
        $this->assertNotSoftDeleted($nrs);

        // Kano's September WHT and October's are not paid over yet.
        $this->delete(route('payments-made.destroy', $state))->assertSessionDoesntHaveErrors();
        $this->assertSoftDeleted($state);
        $this->delete(route('payments-made.destroy', $october))->assertSessionDoesntHaveErrors();
        $this->assertSoftDeleted($october);
        $this->assertBooksBalance();
    }
}
