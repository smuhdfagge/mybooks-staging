<?php

namespace Tests\Feature\Regression;

use App\Models\AccountingPeriod;
use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\TaxBracket;
use App\Models\Vendor;
use App\Services\PayrollTaxService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Round 3, Phase D, finding O4: controllers that had no test at all.
 * Each page answers with its permission and refuses without it; the
 * riskier ones (budgets, fixed assets, accounting periods, the API and
 * payroll tax) also have behaviour tests, including another business's
 * records staying out of reach.
 */
class PhaseDControllerTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function pages(): array
    {
        return [
            'accounting periods' => ['accounting-periods.index', 'view chart-of-accounts'],
            'activity log' => ['activity-logs.index', 'view settings'],
            'allowances' => ['allowances.index', 'create payroll'],
            'banks' => ['banks.index', 'view banks'],
            'budgets' => ['budgets.index', 'view budgets'],
            'deductions' => ['deductions.index', 'create payroll'],
            'designations' => ['designations.index', 'view designations'],
            'expenses' => ['expenses.index', 'view expenses'],
            'fixed asset categories' => ['fixed-asset-categories.index', 'view fixed-assets'],
            'fixed assets' => ['fixed-assets.index', 'view fixed-assets'],
            'fixed asset register' => ['fixed-assets.register', 'view fixed-assets'],
            'depreciation schedule' => ['fixed-assets.depreciation-schedule', 'view fixed-assets'],
            'item categories' => ['item-categories.index', 'view items'],
            'journals' => ['journals.index', 'view journals'],
            'leaves' => ['leaves.index', 'view leaves'],
            'recurrent expenses' => ['recurrent-expenses.index', 'view recurrent-expenses'],
            'invoice templates' => ['settings.invoice-templates.index', 'view settings'],
            'tax groups' => ['tax-groups.index', 'view tax-rates'],
            'vendors' => ['vendors.index', 'view vendors'],
        ];
    }

    private function enableEverything(): void
    {
        config(['mybooks.features.assembly' => true, 'mybooks.features.delivery_notes' => true]);
        $this->plan->update(['slug' => 'professional']);
    }

    #[DataProvider('pages')]
    public function test_o4_page_opens_with_its_permission(string $route, string $permission): void
    {
        $this->createAuthenticatedUser([$permission]);
        $this->enableEverything();

        $this->get(route($route))->assertOk();
    }

    #[DataProvider('pages')]
    public function test_o4_page_is_refused_without_its_permission(string $route, string $permission): void
    {
        $this->createAuthenticatedUser(['view dashboard']);
        $this->enableEverything();

        $this->get(route($route))->assertForbidden();
    }

    /**
     * These optional modules (off by default) have controllers but no
     * index views yet, so their pages would fail with a 500 when switched
     * on. Kept visible here until the views exist.
     */
    public function test_o4_optional_modules_without_views_are_known_gaps(): void
    {
        foreach (['inventory.bom.index', 'delivery-notes.index'] as $view) {
            if (! view()->exists($view)) {
                $this->markTestSkipped("View {$view} does not exist yet: bill-of-materials and delivery-notes pages fail when those features are switched on.");
            }
        }
        $this->assertTrue(true);
    }

    /** Analytics uses MySQL date functions, so it is checked by the MariaDB CI job. */
    public function test_o4_analytics_page_opens_on_mariadb(): void
    {
        if (! in_array(\DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Analytics queries use MySQL/MariaDB date functions (DAYOFWEEK, HOUR).');
        }
        $this->createAuthenticatedUser(['view reports']);

        $this->get(route('analytics.index'))->assertOk();
        $this->createAuthenticatedUser(['view dashboard']);
        $this->get(route('analytics.index'))->assertForbidden();
    }

    // ── Budgets ─────────────────────────────────────────────────

    public function test_o4_budget_is_saved_with_its_lines_and_names_are_unique_per_year(): void
    {
        $this->createAuthenticatedUser(['view budgets', 'create budgets']);
        $this->enableEverything();
        $account = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $payload = ['name' => 'Operating', 'fiscal_year' => '2026', 'lines' => [
            ['account_id' => $account->id, 'jan' => 1000, 'feb' => 2000],
        ]];
        $this->post(route('budgets.store'), $payload)->assertSessionHasNoErrors();

        $budget = Budget::where('tenant_id', $this->tenant->id)->sole();
        $this->assertSame('Operating', $budget->name);
        $this->assertEqualsWithDelta(3000, (float) $budget->lines()->sole()->annual_total, 0.001);

        $this->post(route('budgets.store'), $payload)->assertSessionHasErrors('name');
        $this->assertSame(1, Budget::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_o4_budget_lines_cannot_use_another_business_account(): void
    {
        [$other] = $this->createTenantWithSubscription();
        $foreign = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $other->id)->firstOrFail();
        $this->createAuthenticatedUser(['view budgets', 'create budgets']);
        $this->enableEverything();

        $this->post(route('budgets.store'), ['name' => 'X', 'fiscal_year' => '2026', 'lines' => [['account_id' => $foreign->id, 'jan' => 1]]])
            ->assertSessionHasErrors('lines.0.account_id');
    }

    public function test_o4_another_business_budget_is_out_of_reach(): void
    {
        [$other] = $this->createTenantWithSubscription();
        $owner = $this->createUserForTenant($other);
        $budget = Budget::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'name' => 'Theirs', 'fiscal_year' => '2026', 'status' => 'draft', 'created_by' => $owner->id]);
        $this->createAuthenticatedUser(['view budgets']);
        $this->enableEverything();

        $status = $this->get(route('budgets.show', $budget->id))->getStatusCode();
        $this->assertContains($status, [403, 404]);
    }

    // ── Fixed assets ────────────────────────────────────────────

    public function test_o4_fixed_asset_page_shows_its_schedule_and_another_business_asset_is_refused(): void
    {
        [$other] = $this->createTenantWithSubscription();
        $this->createAuthenticatedUser(['create fixed-assets', 'view fixed-assets']);
        $this->post(route('fixed-assets.store'), [
            'name' => 'Generator', 'purchase_date' => '2026-01-01', 'in_service_date' => '2026-01-01',
            'purchase_cost' => 1200000, 'funding_source' => 'opening_balance', 'salvage_value' => 0,
            'useful_life' => 5, 'depreciation_method' => 'straight_line',
        ])->assertSessionHasNoErrors();
        $asset = \App\Models\FixedAsset::sole();

        $this->get(route('fixed-assets.show', $asset))->assertOk()->assertSee('Generator');

        $this->actingAs($this->createUserForTenant($other, ['view fixed-assets']));
        $status = $this->get(route('fixed-assets.show', $asset->id))->getStatusCode();
        $this->assertContains($status, [403, 404]);
    }

    // ── Accounting periods ──────────────────────────────────────

    public function test_o4_accounting_period_is_created_refuses_overlaps_and_closes(): void
    {
        $this->createAuthenticatedUser(['view chart-of-accounts', 'edit chart-of-accounts']);

        $this->post(route('accounting-periods.store'), ['name' => 'Jan 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31'])
            ->assertSessionHasNoErrors();
        $this->post(route('accounting-periods.store'), ['name' => 'Overlap', 'start_date' => '2026-01-15', 'end_date' => '2026-02-15'])
            ->assertSessionHasErrors('dates');

        $period = AccountingPeriod::where('tenant_id', $this->tenant->id)->sole();
        $this->post(route('accounting-periods.close', $period), [])->assertSessionHasErrors('confirm');
        $this->post(route('accounting-periods.close', $period), ['confirm' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($period->fresh()->isClosed());
    }

    // ── API controllers without a test ──────────────────────────

    public function test_o4_api_vendors_create_list_update_delete_and_stay_in_their_business(): void
    {
        [$other] = $this->createTenantWithSubscription();
        $foreign = Vendor::factory()->create(['tenant_id' => $other->id]);
        $this->createAuthenticatedUser(['view vendors', 'create vendors', 'edit vendors', 'delete vendors']);

        $id = $this->postJson('/api/v1/vendors', ['name' => 'Kano Supplies'])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/vendors')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Kano Supplies');
        $this->putJson("/api/v1/vendors/{$id}", ['name' => 'Kano Supplies Ltd'])->assertOk();
        $this->assertSame('Kano Supplies Ltd', Vendor::find($id)->name);

        $this->getJson("/api/v1/vendors/{$foreign->id}")->assertNotFound();
        $this->deleteJson("/api/v1/vendors/{$foreign->id}")->assertNotFound();
        $this->deleteJson("/api/v1/vendors/{$id}")->assertOk();
    }

    public function test_o4_api_items_create_and_list(): void
    {
        $this->createAuthenticatedUser(['view items', 'create items']);

        $this->postJson('/api/v1/items', ['name' => 'Rice 50kg', 'type' => 'product', 'selling_price' => 75000])->assertCreated();
        $this->getJson('/api/v1/items')->assertOk()->assertJsonPath('data.0.name', 'Rice 50kg');
        $this->postJson('/api/v1/items', [])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_o4_api_search_only_finds_this_business_records(): void
    {
        [$other] = $this->createTenantWithSubscription();
        Customer::factory()->create(['tenant_id' => $other->id, 'name' => 'Dangote Foreign']);
        $this->createAuthenticatedUser(['view customers']);
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Dangote Local']);

        $names = collect($this->getJson('/api/v1/search/customers?q=Dangote')->assertOk()->json('data'))->pluck('name')->all();
        $this->assertSame(['Dangote Local'], $names);
    }

    public function test_o4_api_dashboard_and_settings_answer(): void
    {
        $this->createAuthenticatedUser(['view dashboard', 'view settings']);

        $this->getJson('/api/v1/dashboard')->assertOk()->assertJson(['success' => true]);
        $this->getJson('/api/v1/settings')->assertOk()->assertJson(['success' => true]);
        $this->putJson('/api/v1/settings/organization', ['name' => 'X'])->assertForbidden();
    }

    // ── Payroll tax ─────────────────────────────────────────────

    public function test_o4_payroll_tax_uses_annual_bands_for_monthly_pay_and_falls_back_to_a_flat_rate(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $service = app(PayrollTaxService::class);

        // No bands: flat rate, or nothing.
        $this->assertEqualsWithDelta(10000, $service->calculateTax(100000, $tenant->id, 10)['tax'], 0.001);
        $this->assertSame('flat', $service->calculateTax(100000, $tenant->id, 10)['method']);
        $this->assertEquals(0, $service->calculateTax(100000, $tenant->id)['tax']);
        $this->assertEquals(0, $service->calculateTax(0, $tenant->id, 10)['tax']);

        // Annual bands: 0% to 1.2m, 10% above. 150,000 a month = 1.8m a year
        // -> 60,000 a year -> 5,000 a month.
        TaxBracket::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'name' => 'Band 1', 'min_amount' => 0, 'max_amount' => 1200000, 'rate' => 0, 'period' => 'annual', 'is_active' => true, 'sort_order' => 0]);
        TaxBracket::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'name' => 'Band 2', 'min_amount' => 1200000, 'max_amount' => null, 'rate' => 10, 'period' => 'annual', 'is_active' => true, 'sort_order' => 1]);

        $result = $service->calculateTax(150000, $tenant->id, 99, 'monthly');
        $this->assertEqualsWithDelta(5000, $result['tax'], 0.01);
        $this->assertSame('annual', $result['period_converted_from']);
    }

    public function test_o4_employer_contributions_respect_their_cap(): void
    {
        $result = app(PayrollTaxService::class)->calculateEmployerContributions(500000, [
            ['name' => 'Pension (10%)', 'type' => 'percentage', 'rate' => 10],
            ['name' => 'NSITF', 'type' => 'percentage', 'rate' => 1, 'cap' => 3000],
            ['name' => 'Group life', 'type' => 'fixed', 'rate' => 2500],
        ]);

        $this->assertEqualsWithDelta(50000 + 3000 + 2500, $result['total'], 0.001);
        $this->assertEqualsWithDelta(3000, $result['details'][1]['amount'], 0.001);
    }

    // ── Admin panel ─────────────────────────────────────────────

    public function test_o4_admin_panel_login_and_admin_user_list(): void
    {
        $this->get(route('admin.login'))->assertOk();
        $admin = \App\Models\AdminUser::create(['name' => 'Ops', 'email' => 'ops@example.com', 'password' => 'Secret-123!', 'is_active' => true, 'role' => 'super_admin']);

        $this->post(route('admin.login'), ['email' => 'ops@example.com', 'password' => 'wrong'])->assertSessionHasErrors();
        $this->assertGuest('admin');

        $this->actingAs($admin, 'admin')->get(route('admin.users.index'))->assertOk()->assertSee('ops@example.com');

        $viewer = \App\Models\AdminUser::create(['name' => 'View', 'email' => 'view@example.com', 'password' => 'Secret-123!', 'is_active' => true, 'role' => 'viewer']);
        $this->actingAs($viewer, 'admin')->get(route('admin.users.index'))->assertForbidden();
    }
}
