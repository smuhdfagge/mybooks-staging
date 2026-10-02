<?php

namespace Tests\Feature\Features;

use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\Journal;
use App\Models\Payroll;
use App\Models\SalaryStructure;
use App\Models\StatutoryRemittance;
use App\Models\StatutoryTaxTemplate;
use App\Models\Tenant;
use App\Services\PayrollTaxService;
use Tests\TestCase;

/**
 * Tax pack 1: payroll statutory remittances (PAYE by state, pension by
 * PFA, NHF, NSITF, ITF).
 */
class PayrollStatutoryTest extends TestCase
{
    private const PERMS = ['create payroll', 'view payroll', 'edit payroll', 'approve payroll'];

    private function structure(Tenant $tenant, array $items = []): SalaryStructure
    {
        $structure = SalaryStructure::create([
            'tenant_id' => $tenant->id, 'name' => 'Officer '.uniqid(), 'basic_salary' => 300000,
            'effective_from' => '2026-01-01', 'is_active' => true,
        ]);
        $items = $items ?: [
            ['type' => 'allowance', 'name' => 'Housing', 'amount_type' => 'fixed', 'amount' => 100000],
            ['type' => 'allowance', 'name' => 'Transport', 'amount_type' => 'fixed', 'amount' => 50000],
            ['type' => 'allowance', 'name' => 'Meal', 'amount_type' => 'fixed', 'amount' => 20000],
        ];
        foreach ($items as $i => $item) {
            $structure->items()->create($item + ['is_taxable' => true, 'sort_order' => $i]);
        }

        return $structure;
    }

    private function employee(Tenant $tenant, array $attrs = []): Employee
    {
        return Employee::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->id,
            'employee_id' => Employee::generateEmployeeId($tenant->id),
            'first_name' => 'Amina', 'last_name' => 'Bello', 'hire_date' => '2026-01-05', 'status' => 'active',
            'salary_structure_id' => $this->structure($tenant)->id,
        ], $attrs));
    }

    private function runPayroll(array $employees, string $month = '2026-09'): void
    {
        $this->post(route('payroll.generate'), ['month' => $month, 'employee_ids' => array_map(fn ($e) => $e->id, $employees)])
            ->assertSessionHasNoErrors();
        // Someone other than the preparer approves (segregation of duties).
        $preparer = $this->user;
        $this->actingAs($this->createUserForTenant($this->tenant, self::PERMS));
        foreach (Payroll::whereIn('employee_id', array_map(fn ($e) => $e->id, $employees))->get() as $payroll) {
            $this->post(route('payroll.approve', $payroll))->assertSessionHas('success');
        }
        $this->actingAs($preparer);
    }

    /** Another business, logged in fresh (so its accounts aren't stamped with ours). */
    private function switchToNewBusiness(array $permissions): void
    {
        auth()->logout();
        $this->createAuthenticatedUser($permissions);
    }

    private function balance(string $code, ?int $tenantId = null): float
    {
        return (float) ChartOfAccount::where('tenant_id', $tenantId ?? $this->tenant->id)->where('account_code', $code)->value('current_balance');
    }

    private function nigeria2026(): void
    {
        StatutoryTaxTemplate::where('country_code', 'NGA')->where('tax_year', 2026)->sole()->applyToTenant($this->tenant->id);
    }

    public function test_payroll_run_adds_pension_nhf_and_nsitf_and_pays_less_paye(): void
    {
        $this->createAuthenticatedUser(self::PERMS);
        $this->nigeria2026();
        $employee = $this->employee($this->tenant, ['nhf_registered' => true, 'tax_state' => 'Lagos', 'pfa_name' => 'Stanbic IBTC Pension', 'rsa_pin' => 'PEN100200300400']);

        $this->runPayroll([$employee]);
        $payroll = Payroll::where('employee_id', $employee->id)->sole();

        // Gross 470,000; pensionable pay = basic + housing + transport = 450,000.
        $this->assertEqualsWithDelta(470000, (float) $payroll->gross_salary, 0.001);
        $deductions = collect($payroll->deduction_details)->keyBy(fn ($d) => $d['_statutory'] ?? $d['name']);
        $this->assertEqualsWithDelta(36000, $deductions['pension']['amount'], 0.001);   // 8% of 450,000
        $this->assertEqualsWithDelta(7500, $deductions['nhf']['amount'], 0.001);        // 2.5% of 300,000
        $employer = collect($payroll->employer_contribution_details)->keyBy('_statutory');
        $this->assertEqualsWithDelta(45000, $employer['pension']['amount'], 0.001);     // 10% of 450,000
        $this->assertEqualsWithDelta(4700, $employer['nsitf']['amount'], 0.001);        // 1% of 470,000
        $this->assertFalse($employer->has('itf'), 'ITF is off until the business turns it on');

        // Pension and NHF are reliefs: PAYE is worked out on 470,000 - 43,500.
        $expected = app(PayrollTaxService::class)->calculateTax(426500, $this->tenant->id)['tax'];
        $this->assertEqualsWithDelta($expected, (float) $payroll->tax_deduction, 0.01);
        $this->assertSame('Lagos', $payroll->statutory['tax_state']);

        // Approval posts a balanced journal with each body in its own liability.
        $journal = Journal::where('reference_type', Payroll::class)->where('reference_id', $payroll->id)->sole();
        $this->assertEqualsWithDelta((float) $journal->total_debit, (float) $journal->total_credit, 0.001);
        $this->assertEqualsWithDelta(81000, $this->balance('2320'), 0.001); // pension 36,000 + 45,000
        $this->assertEqualsWithDelta(7500, $this->balance('2370'), 0.001);  // NHF
        $this->assertEqualsWithDelta(4700, $this->balance('2380'), 0.001);  // NSITF
        $this->assertEqualsWithDelta($expected, $this->balance('2310'), 0.01);
    }

    public function test_a_pension_line_in_the_salary_structure_is_not_doubled(): void
    {
        $this->createAuthenticatedUser(self::PERMS);
        $structure = $this->structure($this->tenant, [
            ['type' => 'deduction', 'name' => 'Pension (8%)', 'amount_type' => 'percentage', 'amount' => 8],
        ]);
        $employee = $this->employee($this->tenant, ['salary_structure_id' => $structure->id]);

        $this->runPayroll([$employee]);

        $payroll = Payroll::where('employee_id', $employee->id)->sole();
        $pension = collect($payroll->deduction_details)->filter(fn ($d) => str_contains(strtolower($d['name']), 'pension'));
        $this->assertCount(1, $pension);
    }

    public function test_businesses_outside_nigeria_get_no_automatic_contributions(): void
    {
        $this->createAuthenticatedUser(self::PERMS);
        $this->tenant->update(['country' => 'GH']);
        $employee = $this->employee($this->tenant, ['nhf_registered' => true]);

        $this->runPayroll([$employee]);

        $payroll = Payroll::where('employee_id', $employee->id)->sole();
        $this->assertEqualsWithDelta(0, (float) $payroll->other_deductions, 0.001);
        $this->assertEqualsWithDelta(0, (float) $payroll->employer_contributions, 0.001);
    }

    public function test_schedules_group_paye_by_state_and_pension_by_pfa(): void
    {
        $this->createAuthenticatedUser(self::PERMS);
        $this->nigeria2026();
        $this->tenant->update(['state' => 'Kano']);
        $a = $this->employee($this->tenant, ['first_name' => 'Musa', 'tax_state' => 'Lagos', 'pfa_name' => 'Stanbic IBTC Pension', 'rsa_pin' => 'PEN111']);
        $b = $this->employee($this->tenant, ['first_name' => 'Zainab', 'pfa_name' => 'ARM Pension', 'rsa_pin' => 'PEN222']); // no state: the business's

        $this->runPayroll([$a, $b]);

        $csv = $this->get(route('payroll.liabilities.schedule', ['body' => 'paye', 'month' => '2026-09', 'format' => 'csv']))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('State,', $csv);
        $this->assertMatchesRegularExpression('/Kano,.*Zainab/', $csv);
        $this->assertMatchesRegularExpression('/Lagos,.*Musa/', $csv);

        $pension = $this->get(route('payroll.liabilities.schedule', ['body' => 'pension', 'month' => '2026-09', 'format' => 'csv']))
            ->assertOk()->streamedContent();
        $this->assertMatchesRegularExpression('/"?ARM Pension"?,.*Zainab.*PEN222,450000.00,36000.00,45000.00,81000.00/', $pension);
        $this->assertMatchesRegularExpression('/"?Stanbic IBTC Pension"?,.*Musa.*PEN111/', $pension);

        $this->get(route('payroll.liabilities.schedule', ['body' => 'nsitf', 'month' => '2026-09', 'format' => 'pdf']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->get(route('payroll.liabilities', ['month' => '2026-09']))->assertOk()
            ->assertSee('PAYE (State Internal Revenue Service)')
            ->assertSee('162,000.00', false); // pension owed: 2 x 81,000
    }

    public function test_recording_a_remittance_posts_the_journal_and_clears_the_month(): void
    {
        $this->createAuthenticatedUser(self::PERMS);
        $employee = $this->employee($this->tenant, ['pfa_name' => 'ARM Pension']);
        $this->runPayroll([$employee]);

        $this->post(route('payroll.liabilities.remit'), [
            'account_code' => '2320', 'amount' => 81000, 'date' => now()->toDateString(), 'payment_method' => 'bank_transfer',
            'reference' => 'PENCOM-SEP', 'period' => '2026-09', 'paid_to' => 'ARM Pension',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $remittance = StatutoryRemittance::sole();
        $this->assertSame('pension', $remittance->body);
        $this->assertSame('2026-09-01', $remittance->period_start->toDateString());
        $this->assertEqualsWithDelta(0, $this->balance('2320'), 0.001);
        $this->assertEqualsWithDelta(-81000, $this->balance('1100'), 0.001);

        $pension = app(\App\Services\Payroll\StatutorySchedule::class)
            ->summary($this->tenant->id, \Carbon\Carbon::parse('2026-09-01'))->firstWhere('body', 'pension');
        $this->assertEqualsWithDelta(81000, $pension->due, 0.001);
        $this->assertEqualsWithDelta(0, $pension->outstanding, 0.001);
    }

    public function test_itf_and_rates_are_business_settings(): void
    {
        $this->createAuthenticatedUser(self::PERMS);
        $this->put(route('payroll.liabilities.settings'), [
            'enabled' => 1, 'pension_applies' => 1, 'pension_employee_rate' => 8, 'pension_employer_rate' => 12,
            'nhf_rate' => 2.5, 'nsitf_applies' => 0, 'nsitf_rate' => 1, 'itf_applies' => 1, 'itf_rate' => 1,
        ])->assertSessionHasNoErrors();

        $employee = $this->employee($this->tenant);
        $this->runPayroll([$employee]);

        $employer = collect(Payroll::where('employee_id', $employee->id)->sole()->employer_contribution_details)->keyBy('_statutory');
        $this->assertEqualsWithDelta(54000, $employer['pension']['amount'], 0.001); // 12% of 450,000
        $this->assertEqualsWithDelta(4700, $employer['itf']['amount'], 0.001);
        $this->assertFalse($employer->has('nsitf'));
        $this->assertEqualsWithDelta(4700, $this->balance('2390'), 0.001);

        // ITF is paid for the year: a remittance covers January to December.
        $this->post(route('payroll.liabilities.remit'), [
            'account_code' => '2390', 'amount' => 4700, 'date' => now()->toDateString(), 'payment_method' => 'bank_transfer', 'period' => '2026-09',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2026-01-01', StatutoryRemittance::sole()->period_start->toDateString());
        $this->assertSame('2026-12-31', StatutoryRemittance::sole()->period_end->toDateString());
    }

    public function test_a_business_sees_only_its_own_schedules_and_remittances(): void
    {
        $this->createAuthenticatedUser(self::PERMS);
        $mine = $this->employee($this->tenant, ['first_name' => 'Mine']);
        $this->runPayroll([$mine]);
        $myTenant = $this->tenant;
        $this->post(route('payroll.liabilities.remit'), [
            'account_code' => '2320', 'amount' => 100, 'date' => now()->toDateString(), 'payment_method' => 'bank_transfer', 'period' => '2026-09', 'reference' => 'MINE-REF',
        ]);

        $this->switchToNewBusiness(self::PERMS);
        $theirs = $this->employee($this->tenant, ['first_name' => 'Theirs']);
        $this->runPayroll([$theirs]);

        $csv = $this->get(route('payroll.liabilities.schedule', ['body' => 'pension', 'month' => '2026-09', 'format' => 'csv']))->streamedContent();
        $this->assertStringContainsString('Theirs', $csv);
        $this->assertStringNotContainsString('Mine', $csv);
        $this->get(route('payroll.liabilities', ['month' => '2026-09']))->assertOk()->assertDontSee('MINE-REF');
        $this->assertSame(1, StatutoryRemittance::withoutGlobalScopes()->where('tenant_id', $myTenant->id)->count());
    }

    public function test_viewing_needs_view_payroll_and_changing_needs_edit_payroll(): void
    {
        $this->createAuthenticatedUser(['view payroll']);
        $this->get(route('payroll.liabilities'))->assertOk()->assertDontSee('Save rates');
        $this->get(route('payroll.liabilities.schedule', ['body' => 'paye', 'format' => 'csv']))->assertOk();
        $this->put(route('payroll.liabilities.settings'), ['pension_employee_rate' => 8])->assertForbidden();
        $this->post(route('payroll.liabilities.remit'), [])->assertForbidden();

        $this->switchToNewBusiness([]);
        $this->get(route('payroll.liabilities'))->assertForbidden();
        $this->get(route('payroll.liabilities.schedule', ['body' => 'paye']))->assertForbidden();
    }
}
