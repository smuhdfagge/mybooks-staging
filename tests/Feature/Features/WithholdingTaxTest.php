<?php

namespace Tests\Feature\Features;

use App\Models\ChartOfAccount;
use App\Models\Vendor;
use App\Models\WhtCategory;
use App\Services\AccountCodeService;
use App\Services\Accounting\WithholdingTax;
use Tests\TestCase;

/**
 * Withholding tax (WHT): setup, deductions on purchases and sales, the
 * monthly schedule and remittance, and WHT credit notes.
 */
class WithholdingTaxTest extends TestCase
{
    private const ALL = ['view withholding-tax', 'manage withholding-tax', 'remit withholding-tax'];

    /** Sign in as a user of a brand-new business (signed out first, so the business is set up as itself). */
    private function signInFresh(array $permissions): void
    {
        auth()->logout();
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
}
