<?php

namespace Tests\Feature\Regression;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\SalaryStructure;
use App\Models\StatutoryTaxTemplate;
use App\Models\TaxBracket;
use App\Models\Tenant;
use App\Services\PayrollTaxService;
use Tests\TestCase;

/**
 * Round 3, Phase A: urgent correctness fixes.
 */
class PhaseARegressionTest extends TestCase
{
    private function employeeFor(Tenant $tenant, array $attrs = []): Employee
    {
        return Employee::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->id,
            'employee_id' => Employee::generateEmployeeId($tenant->id),
            'first_name' => 'Amina',
            'last_name' => 'Bello',
            'hire_date' => '2026-01-05',
        ], $attrs));
    }

    public function test_r1_two_businesses_can_both_have_employee_emp_00001(): void
    {
        [$tenantA] = $this->createTenantWithSubscription();
        [$tenantB] = $this->createTenantWithSubscription();

        $a = $this->employeeFor($tenantA);
        $b = $this->employeeFor($tenantB);

        $this->assertSame('EMP-00001', $a->employee_id);
        $this->assertSame('EMP-00001', $b->employee_id);
    }

    public function test_r1_employee_ids_are_still_unique_within_one_business(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $this->employeeFor($tenant, ['employee_id' => 'EMP-00007']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->employeeFor($tenant, ['employee_id' => 'EMP-00007']);
    }

    public function test_r1_generator_skips_ids_already_taken_by_imported_staff(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $this->employeeFor($tenant, ['employee_id' => 'EMP-00002']);
        // An imported employee with a free-form ID is the latest row.
        $this->employeeFor($tenant, ['employee_id' => 'STAFF-9']);

        $next = Employee::generateEmployeeId($tenant->id);

        $this->assertSame('EMP-00003', $next);
    }

    public function test_r1_api_rejects_a_duplicate_employee_id_in_the_same_business(): void
    {
        $this->createAuthenticatedUser(['view employees', 'create employees']);
        $this->employeeFor($this->tenant, ['employee_id' => 'EMP-00010']);

        $this->postJson(route('api.employees.store'), [
            'employee_id' => 'EMP-00010',
            'first_name' => 'Musa',
            'last_name' => 'Garba',
            'email' => 'musa@example.com',
            'hire_date' => '2026-02-01',
        ])->assertStatus(422)->assertJsonValidationErrors('employee_id');
    }

    // ── A3: PAYE ────────────────────────────────────────────────

    private function applyNigeria2026(int $tenantId): void
    {
        StatutoryTaxTemplate::where('country_code', 'NGA')->where('tax_year', 2026)->sole()->applyToTenant($tenantId);
    }

    public function test_a3_the_nigeria_2026_template_has_the_nta_2025_bands(): void
    {
        $template = StatutoryTaxTemplate::where('country_code', 'NGA')->where('is_current', true)->sole();

        $this->assertSame(2026, (int) $template->tax_year);
        $this->assertSame([0, 15, 18, 21, 23, 25], array_map(fn ($b) => (int) $b['rate'], $template->brackets));
        $this->assertEquals([800000, 3000000, 12000000, 25000000, 50000000, null], array_column($template->brackets, 'max'));
    }

    public function test_a3_monthly_pay_is_taxed_on_annual_bands(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $this->applyNigeria2026($tenant->id);

        // ₦447,500 a month = ₦5,370,000 a year:
        // 0% on 800k, 15% on 2.2m (330,000), 18% on 2.37m (426,600) = 756,600 a year.
        $result = app(PayrollTaxService::class)->calculateTax(447500, $tenant->id, 0, 'monthly');

        $this->assertSame('progressive', $result['method']);
        $this->assertEqualsWithDelta(63050.00, $result['tax'], 0.01);
    }

    public function test_a3_applying_a_template_replaces_brackets_of_the_other_period(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        TaxBracket::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Old monthly', 'min_amount' => 0, 'max_amount' => null,
            'rate' => 5, 'fixed_amount' => 0, 'period' => 'monthly', 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->applyNigeria2026($tenant->id);

        $this->assertSame(0, TaxBracket::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('period', 'monthly')->where('is_active', true)->count());
    }

    public function test_a3_payroll_run_deducts_pension_and_nhf_before_tax(): void
    {
        $this->createAuthenticatedUser(['create payroll', 'view payroll']);
        $this->applyNigeria2026($this->tenant->id);

        $employee = $this->employeeFor($this->tenant);
        $structure = SalaryStructure::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Officer',
            'basic_salary' => 500000,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);
        $structure->items()->create(['type' => 'deduction', 'name' => 'Pension (8%)', 'amount_type' => 'percentage', 'amount' => 8, 'is_taxable' => true, 'sort_order' => 0]);
        $structure->items()->create(['type' => 'deduction', 'name' => 'NHF', 'amount_type' => 'percentage', 'amount' => 2.5, 'is_taxable' => true, 'sort_order' => 1]);
        $structure->items()->create(['type' => 'deduction', 'name' => 'Cooperative', 'amount_type' => 'fixed', 'amount' => 10000, 'sort_order' => 2]);
        $employee->update(['salary_structure_id' => $structure->id]);

        $this->post(route('payroll.generate'), ['month' => '2026-03', 'employee_ids' => [$employee->id]])
            ->assertSessionHasNoErrors();

        $payroll = Payroll::where('employee_id', $employee->id)->sole();
        // 500,000 - 40,000 pension - 12,500 NHF = 447,500 taxable (the cooperative deduction is not a relief).
        $this->assertEqualsWithDelta(63050.00, (float) $payroll->tax_deduction, 0.01);
        $this->assertEqualsWithDelta(500000 - 40000 - 12500 - 10000 - 63050, (float) $payroll->net_salary, 0.01);
    }
}
