<?php

namespace Tests\Feature\Features;

use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\Import;
use App\Models\Journal;
use App\Models\Payroll;
use App\Models\PensionFundAdministrator;
use App\Models\SalaryStructure;
use App\Models\State;
use App\Models\StatutoryContribution;
use App\Models\StatutoryTaxTemplate;
use App\Models\User;
use App\Services\ImportService;
use App\Services\JournalService;
use App\Services\Payroll\StatutoryScheduleService;
use App\Services\Payroll\StatutorySettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Nigeria payroll statutory remittances: employee statutory details,
 * contribution settings, schedules (PAYE by state, pension by PFA, NHF,
 * NSITF, ITF), recording remittances and due dates.
 */
class PayrollRemittanceTest extends TestCase
{
    private function state(string $name): State
    {
        return State::where('name', $name)->firstOrFail();
    }

    private function pfa(string $name): PensionFundAdministrator
    {
        return PensionFundAdministrator::whereNull('tenant_id')->where('name', $name)->firstOrFail();
    }

    // ── Shared set-up: two employees, Kano and Lagos ───────────────

    private function balance(string $code, ?int $tenantId = null): float
    {
        return (float) ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId ?? $this->tenant->id)
            ->where('account_code', $code)->value('current_balance');
    }

    /** @param array<int, array{0: string, 1: float}> $allowances */
    private function employeeWithStructure(string $first, string $state, string $pfa, float $basic, array $allowances, array $extra = [], array $deductions = []): Employee
    {
        $structure = SalaryStructure::create([
            'tenant_id' => $this->tenant->id, 'name' => $first.' grade', 'basic_salary' => $basic, 'effective_from' => '2026-01-01', 'is_active' => true,
        ]);
        foreach ($allowances as $i => [$name, $amount]) {
            $structure->items()->create(['type' => 'allowance', 'name' => $name, 'amount_type' => 'fixed', 'amount' => $amount, 'is_taxable' => true, 'sort_order' => $i]);
        }
        foreach ($deductions as $i => [$name, $rate]) {
            $structure->items()->create(['type' => 'deduction', 'name' => $name, 'amount_type' => 'percentage', 'amount' => $rate, 'is_taxable' => true, 'sort_order' => 10 + $i]);
        }

        return Employee::create(array_merge([
            'tenant_id' => $this->tenant->id, 'employee_id' => 'EMP-'.$first, 'first_name' => $first, 'last_name' => 'Test',
            'hire_date' => '2026-01-01', 'status' => 'active', 'salary_structure_id' => $structure->id,
            'tax_id' => 'TIN-'.$first, 'tax_state_id' => $this->state($state)->id,
            'pension_fund_administrator_id' => $this->pfa($pfa)->id,
            'rsa_pin' => 'PEN1000000000'.strlen($first).'0', 'nhf_number' => 'NHF-'.$first,
        ], $extra));
    }

    /**
     * Worked example (September 2026, NTA 2025 bands, statutory settings on, ITF on):
     *
     * Aminu, Kano, Stanbic IBTC: basic 300,000 + housing 150,000 + transport 50,000 + meal 20,000 = gross 520,000.
     *   Pensionable 500,000: employee 8% 40,000, employer 10% 50,000. NHF 2.5% of basic 7,500.
     *   Taxable 520,000 - 40,000 - 7,500 = 472,500; x12 = 5,670,000; tax 0 + 330,000 + 480,600 = 810,600 a year = 67,550.
     *   NSITF 1% of gross 5,200; ITF 1% 5,200. Net 520,000 - 67,550 - 40,000 - 7,500 = 404,950.
     *   (His structure also has an old "Pension (8%)" deduction of gross: ignored when settings are on.)
     * Ngozi, Lagos, Leadway: basic 200,000 + housing 100,000 + transport 40,000 = gross 340,000; rent 1,200,000 a year.
     *   Pensionable 340,000: employee 27,200, employer 34,000. NHF 5,000. Rent relief 240,000 / 12 = 20,000.
     *   Taxable 340,000 - 27,200 - 5,000 - 20,000 = 287,800; x12 = 3,453,600; tax 330,000 + 81,648 = 411,648 = 34,304.
     *   NSITF 3,400; ITF 3,400. Net 340,000 - 34,304 - 27,200 - 5,000 = 273,496.
     *
     * @return array{0: Payroll, 1: Payroll}
     */
    private function septemberPayroll(bool $approve = true, array $permissions = []): array
    {
        $this->createAuthenticatedUser(array_merge(['create payroll', 'view payroll'], $permissions));
        $this->tenant->update(['country' => 'NG']);
        StatutoryTaxTemplate::where('country_code', 'NGA')->where('tax_year', 2026)->sole()->applyToTenant($this->tenant->id);
        StatutorySettings::put($this->tenant->id, true, ['Housing', 'Transport']);
        StatutoryContribution::forTenant($this->tenant->id)['itf']->update(['is_enabled' => true]);

        $aminu = $this->employeeWithStructure('Aminu', 'Kano', 'Stanbic IBTC Pension Managers Limited', 300000,
            [['Housing Allowance', 150000], ['Transport Allowance', 50000], ['Meal Allowance', 20000]], [], [['Pension (8%)', 8]]);
        $ngozi = $this->employeeWithStructure('Ngozi', 'Lagos', 'Leadway Pensure PFA Limited', 200000,
            [['Housing', 100000], ['Transport', 40000]], ['annual_rent' => 1200000]);

        $this->post(route('payroll.generate'), ['month' => '2026-09', 'employee_ids' => [$aminu->id, $ngozi->id]])->assertSessionHasNoErrors();

        $a = Payroll::where('employee_id', $aminu->id)->sole();
        $n = Payroll::where('employee_id', $ngozi->id)->sole();
        if ($approve) {
            $approver = User::factory()->create(['tenant_id' => $this->tenant->id]);
            $a->approve($approver->id);
            $n->approve($approver->id);
        }

        return [$a->fresh(), $n->fresh()];
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function line(array $lines, string $code): float
    {
        return (float) collect($lines)->where('statutory', $code)->sum('amount');
    }

    // ── Part 2: statutory settings and payslips ─────────────────────

    public function test_statutory_settings_are_seeded_per_business_with_sources(): void
    {
        [$other] = $this->createTenantWithSubscription();
        $this->createAuthenticatedUser();

        $rows = StatutoryContribution::forTenant($this->tenant->id);
        $this->assertEqualsWithDelta(8, (float) $rows['pension_employee']->rate, 0.0001);
        $this->assertSame('pensionable', $rows['pension_employee']->base);
        $this->assertEqualsWithDelta(10, (float) $rows['pension_employer']->rate, 0.0001);
        $this->assertEqualsWithDelta(2.5, (float) $rows['nhf']->rate, 0.0001);
        $this->assertSame('basic', $rows['nhf']->base);
        $this->assertEqualsWithDelta(1, (float) $rows['nsitf']->rate, 0.0001);
        $this->assertSame('gross', $rows['nsitf']->base);
        $this->assertFalse($rows['itf']->is_enabled, 'ITF only above the thresholds');
        $this->assertNull($rows['paye']->rate);
        foreach ($rows as $row) {
            $this->assertNotEmpty($row->source, $row->code.' has a source');
            $this->assertNotNull($row->effective_from);
        }
        $this->assertStringContainsString('Pension Reform Act 2014', $rows['pension_employee']->source);

        // Each business has its own copy.
        $rows['nhf']->update(['rate' => 3]);
        $this->assertEqualsWithDelta(2.5, (float) StatutoryContribution::forTenant($other->id)['nhf']->rate, 0.0001);
        $this->assertFalse(StatutorySettings::get($this->tenant->id)['auto'], 'off until the business turns it on');
    }

    public function test_worked_example_payslips_for_kano_and_lagos_employees(): void
    {
        [$a, $n] = $this->septemberPayroll(false);

        $this->assertEqualsWithDelta(520000, (float) $a->gross_salary, 0.001);
        $this->assertEqualsWithDelta(40000, $this->line($a->deduction_details, 'pension_employee'), 0.001);
        $this->assertEqualsWithDelta(7500, $this->line($a->deduction_details, 'nhf'), 0.001);
        $this->assertSame(0, collect($a->deduction_details)->where('name', 'Pension (8%)')->count(), 'old structure pension ignored');
        $this->assertEqualsWithDelta(472500, (float) $a->taxable_income, 0.001);
        $this->assertEqualsWithDelta(67550, (float) $a->tax_deduction, 0.001);
        $this->assertEqualsWithDelta(47500, (float) $a->other_deductions, 0.001);
        $this->assertEqualsWithDelta(404950, (float) $a->net_salary, 0.001);
        $this->assertEqualsWithDelta(50000, $this->line($a->employer_contribution_details, 'pension_employer'), 0.001);
        $this->assertEqualsWithDelta(5200, $this->line($a->employer_contribution_details, 'nsitf'), 0.001);
        $this->assertEqualsWithDelta(5200, $this->line($a->employer_contribution_details, 'itf'), 0.001);
        $this->assertEqualsWithDelta(60400, (float) $a->employer_contributions, 0.001);
        $this->assertSame($this->state('Kano')->id, $a->tax_state_id);
        $this->assertSame($this->pfa('Stanbic IBTC Pension Managers Limited')->id, $a->pension_fund_administrator_id);

        $this->assertEqualsWithDelta(340000, (float) $n->gross_salary, 0.001);
        $this->assertEqualsWithDelta(27200, $this->line($n->deduction_details, 'pension_employee'), 0.001);
        $this->assertEqualsWithDelta(5000, $this->line($n->deduction_details, 'nhf'), 0.001);
        $this->assertEqualsWithDelta(287800, (float) $n->taxable_income, 0.001);
        $this->assertEqualsWithDelta(34304, (float) $n->tax_deduction, 0.001);
        $this->assertEqualsWithDelta(273496, (float) $n->net_salary, 0.001);
        $this->assertEqualsWithDelta(34000, $this->line($n->employer_contribution_details, 'pension_employer'), 0.001);
        $this->assertEqualsWithDelta(3400, $this->line($n->employer_contribution_details, 'nsitf'), 0.001);
        $this->assertSame($this->state('Lagos')->id, $n->tax_state_id);
    }

    public function test_with_statutory_settings_off_payslips_are_worked_out_as_before(): void
    {
        $this->createAuthenticatedUser(['create payroll', 'view payroll']);
        StatutoryTaxTemplate::where('country_code', 'NGA')->where('tax_year', 2026)->sole()->applyToTenant($this->tenant->id);
        $aminu = $this->employeeWithStructure('Aminu', 'Kano', 'Stanbic IBTC Pension Managers Limited', 300000,
            [['Housing Allowance', 150000], ['Transport Allowance', 50000], ['Meal Allowance', 20000]], [], [['Pension (8%)', 8]]);

        $this->post(route('payroll.generate'), ['month' => '2026-09', 'employee_ids' => [$aminu->id]])->assertSessionHasNoErrors();
        $p = Payroll::sole();

        // Structure pension: 8% of gross 520,000 = 41,600; no NHF, no employer lines.
        $this->assertEqualsWithDelta(41600, (float) $p->other_deductions, 0.001);
        $this->assertEqualsWithDelta(0, (float) $p->employer_contributions, 0.001);
        $this->assertEqualsWithDelta(478400, (float) $p->taxable_income, 0.001);
    }

    public function test_settings_page_changes_rates_and_pensionable_pay_used_by_payroll(): void
    {
        $this->createAuthenticatedUser(['create payroll', 'view payroll', 'manage statutory-settings']);
        StatutoryTaxTemplate::where('country_code', 'NGA')->where('tax_year', 2026)->sole()->applyToTenant($this->tenant->id);
        $this->get(route('payroll.statutory.settings'))->assertOk()->assertSee('Pension Reform Act 2014')->assertSee('19 PFAs licensed by PenCom');

        $form = [];
        foreach (StatutoryContribution::forTenant($this->tenant->id) as $code => $c) {
            $form[$code] = ['name' => $c->name, 'rate' => $c->rate, 'base' => $c->base, 'is_enabled' => $c->is_enabled ? 1 : 0,
                'due_rule' => $c->due_rule, 'due_value' => $c->due_value, 'effective_from' => $c->effective_from?->toDateString(), 'source' => $c->source];
        }
        $form['pension_employee']['rate'] = 10;
        $form['nsitf']['is_enabled'] = 0;
        $this->put(route('payroll.statutory.settings.update'), ['auto' => 1, 'pensionable_components' => 'Housing', 'contributions' => $form])
            ->assertSessionHasNoErrors()->assertRedirect(route('payroll.statutory.settings'));

        $this->assertSame(['auto' => true, 'pensionable_components' => ['Housing']], StatutorySettings::get($this->tenant->id));

        $aminu = $this->employeeWithStructure('Aminu', 'Kano', 'Stanbic IBTC Pension Managers Limited', 300000,
            [['Housing Allowance', 150000], ['Transport Allowance', 50000]]);
        $this->post(route('payroll.generate'), ['month' => '2026-09', 'employee_ids' => [$aminu->id]])->assertSessionHasNoErrors();
        $p = Payroll::sole();

        // Pensionable now basic + housing = 450,000: employee 10% 45,000, employer 10% 45,000; no NSITF.
        $this->assertEqualsWithDelta(45000, $this->line($p->deduction_details, 'pension_employee'), 0.001);
        $this->assertEqualsWithDelta(45000, $this->line($p->employer_contribution_details, 'pension_employer'), 0.001);
        $this->assertEqualsWithDelta(0, $this->line($p->employer_contribution_details, 'nsitf'), 0.001);

        // Bad due day refused.
        $form['paye']['due_value'] = 45;
        $this->put(route('payroll.statutory.settings.update'), ['contributions' => $form])->assertSessionHasErrors('contributions.paye.due_value');
    }

    public function test_approving_posts_each_scheme_to_its_own_liability_account(): void
    {
        $this->septemberPayroll();

        $this->assertEqualsWithDelta(101854, $this->balance('2310'), 0.001, 'PAYE 67,550 + 34,304');
        $this->assertEqualsWithDelta(151200, $this->balance('2320'), 0.001, 'pension 40,000 + 50,000 + 27,200 + 34,000');
        $this->assertEqualsWithDelta(12500, $this->balance('2370'), 0.001, 'NHF');
        $this->assertEqualsWithDelta(8600, $this->balance('2380'), 0.001, 'NSITF');
        $this->assertEqualsWithDelta(8600, $this->balance('2390'), 0.001, 'ITF');
        $this->assertEqualsWithDelta(0, $this->balance('2300'), 0.001, 'nothing left in general payroll liabilities');
        $this->assertEqualsWithDelta(84000, $this->balance('6050'), 0.001, 'employer pension expense');
        $this->assertEqualsWithDelta(8600, $this->balance('6070'), 0.001, 'NSITF expense');
        $this->assertEqualsWithDelta(8600, $this->balance('6020'), 0.001, 'ITF expense');
        foreach (Journal::where('journal_type', 'payroll_accrual')->get() as $journal) {
            $this->assertEqualsWithDelta((float) $journal->total_debit, (float) $journal->total_credit, 0.001);
        }
    }

    public function test_statutory_settings_need_permission_and_stay_inside_the_business(): void
    {
        [$other] = $this->createTenantWithSubscription();
        $theirs = PensionFundAdministrator::create(['tenant_id' => $other->id, 'name' => 'Their PFA']);
        $this->createAuthenticatedUser(['view payroll', 'edit payroll']);

        $this->get(route('payroll.statutory.settings'))->assertForbidden();
        $this->post(route('payroll.statutory.pfas.store'), ['name' => 'X'])->assertForbidden();

        $this->user->givePermissionTo(Permission::findOrCreate('manage statutory-settings', 'web'));
        $this->post(route('payroll.statutory.pfas.store'), ['name' => 'Our Merged PFA', 'code' => 'PFA099'])->assertSessionHasNoErrors();
        $ours = PensionFundAdministrator::where('name', 'Our Merged PFA')->sole();
        $this->assertSame($this->tenant->id, $ours->tenant_id);
        $this->post(route('payroll.statutory.pfas.store'), ['name' => 'Stanbic IBTC Pension Managers Limited'])->assertSessionHasErrors('name');

        $this->patch(route('payroll.statutory.pfas.update', $theirs), ['name' => 'Hijacked'])->assertForbidden();
        $this->patch(route('payroll.statutory.pfas.update', $this->pfa('Access Pensions Limited')), ['name' => 'Renamed'])->assertForbidden();
        $this->patch(route('payroll.statutory.pfas.update', $ours), ['name' => 'Our PFA (renamed)', 'is_active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($ours->fresh()->is_active);
        $this->assertSame('Their PFA', $theirs->fresh()->name);
    }

    // ── Part 3: remittance schedules ───────────────────────────────

    /** @return array<string, mixed> */
    private function schedule(string $name, string $month = '2026-09'): array
    {
        return app(StatutoryScheduleService::class)->build($this->tenant->id, $name, Carbon::parse($month.'-01'), true);
    }

    public function test_schedules_group_by_state_and_pfa_and_total_correctly(): void
    {
        $this->septemberPayroll();

        $paye = $this->schedule('paye');
        $byState = collect($paye['groups'])->mapWithKeys(fn ($g) => [$g['label'] => $g['amount']]);
        $this->assertEquals(['Kano' => 67550.0, 'Lagos' => 34304.0], $byState->all());
        $this->assertEqualsWithDelta(101854, $paye['total'], 0.001);
        $kanoRow = collect($paye['groups'])->firstWhere('label', 'Kano')['rows'][0];
        $this->assertSame('Aminu Test', $kanoRow['employee']);
        $this->assertSame('TIN-Aminu', $kanoRow['tin']);
        $this->assertEqualsWithDelta(520000, $kanoRow['gross'], 0.001);
        $this->assertEqualsWithDelta(472500, $kanoRow['taxable'], 0.001);

        $pension = $this->schedule('pension');
        $stanbic = collect($pension['groups'])->firstWhere('label', 'Stanbic IBTC Pension Managers Limited');
        $leadway = collect($pension['groups'])->firstWhere('label', 'Leadway Pensure PFA Limited');
        $this->assertEqualsWithDelta(40000, $stanbic['rows'][0]['employee_share'], 0.001);
        $this->assertEqualsWithDelta(50000, $stanbic['rows'][0]['employer_share'], 0.001);
        $this->assertEqualsWithDelta(90000, $stanbic['amount'], 0.001);
        $this->assertEqualsWithDelta(61200, $leadway['amount'], 0.001);
        $this->assertSame('PEN100000000050', $stanbic['rows'][0]['rsa_pin']);
        $this->assertEqualsWithDelta(151200, $pension['total'], 0.001);

        $this->assertEqualsWithDelta(12500, $this->schedule('nhf')['total'], 0.001);
        $this->assertEqualsWithDelta(8600, $this->schedule('nsitf')['total'], 0.001);
        $itf = $this->schedule('itf');
        $this->assertEqualsWithDelta(8600, $itf['total'], 0.001);
        $this->assertEqualsWithDelta(8600, $itf['extra']['year_to_date'], 0.001);
        $this->assertEqualsWithDelta(860000, $itf['extra']['year_payroll'], 0.001);

        // A different month has nothing.
        $this->assertEqualsWithDelta(0, $this->schedule('paye', '2026-08')['total'], 0.001);
    }

    public function test_schedule_totals_agree_to_the_ledger_and_a_stray_journal_shows_up(): void
    {
        $this->septemberPayroll();

        foreach (['paye' => '2310', 'pension' => '2320', 'nhf' => '2370', 'nsitf' => '2380', 'itf' => '2390'] as $name => $code) {
            $s = $this->schedule($name);
            $this->assertSame($code, $s['ledger']['account_code']);
            $this->assertEqualsWithDelta($s['total'], $s['ledger']['posted'], 0.001, $name.' agrees');
            $this->assertEqualsWithDelta(0, $s['ledger']['difference'], 0.001);
        }

        // A manual journal into NHF Payable in September is a difference to explain.
        $journal = Journal::create([
            'tenant_id' => $this->tenant->id, 'journal_number' => Journal::generateNumber($this->tenant->id), 'journal_date' => '2026-09-20',
            'reference' => 'ADJ-1', 'description' => 'Adjustment', 'status' => 'posted', 'is_posted' => true, 'posted_at' => now(),
        ]);
        $js = app(JournalService::class);
        $js->createEntry($journal, '6000', 1000, 0, 'x');
        $js->createEntry($journal, '2370', 0, 1000, 'x');
        $journal->updateTotals();
        $journal->save();
        $js->updateAccountBalances($journal);

        $nhf = $this->schedule('nhf');
        $this->assertEqualsWithDelta(13500, $nhf['ledger']['posted'], 0.001);
        $this->assertEqualsWithDelta(-1000, $nhf['ledger']['difference'], 0.001);
    }

    public function test_schedules_use_state_and_pfa_as_at_the_pay_run_and_skip_drafts(): void
    {
        [$a] = $this->septemberPayroll();
        // Aminu moves to Lagos and changes PFA after September was paid.
        $a->employee->update(['tax_state_id' => $this->state('Lagos')->id, 'pension_fund_administrator_id' => $this->pfa('Access Pensions Limited')->id]);
        // A draft payroll for the month is not owed yet.
        Payroll::create(array_merge($a->only(['employee_id', 'pay_period_start', 'pay_period_end', 'pay_date', 'basic_salary', 'allowances', 'gross_salary', 'tax_deduction', 'other_deductions', 'total_deductions', 'net_salary', 'deduction_details']), [
            'tenant_id' => $this->tenant->id, 'payroll_number' => 'PAY-DRAFT', 'status' => 'draft',
        ]));

        $paye = $this->schedule('paye');
        $this->assertEqualsWithDelta(67550, collect($paye['groups'])->firstWhere('label', 'Kano')['amount'], 0.001);
        $this->assertEqualsWithDelta(101854, $paye['total'], 0.001);
        $this->assertNotNull(collect($this->schedule('pension')['groups'])->firstWhere('label', 'Stanbic IBTC Pension Managers Limited'));
    }

    public function test_remittance_screens_and_exports(): void
    {
        $this->septemberPayroll(true, ['view statutory-remittances', 'record statutory-remittances']);

        $this->get(route('payroll.statutory.index', ['month' => '2026-09']))->assertOk()
            ->assertSee('PAYE schedule')->assertSee('101,854.00')->assertSee('151,200.00');
        $this->get(route('payroll.statutory.show', ['schedule' => 'paye', 'month' => '2026-09']))->assertOk()
            ->assertSee('Kano')->assertSee('Lagos')->assertSee('67,550.00')->assertSee('TIN-Aminu')->assertSee('(agrees)');

        $csv = $this->get(route('payroll.statutory.export', ['schedule' => 'pension', 'month' => '2026-09', 'format' => 'csv']));
        $csv->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString('PFA,Employee,"Staff no.","RSA PIN",Employee,Employer,Total', $body);
        $this->assertStringContainsString('"Stanbic IBTC Pension Managers Limited","Aminu Test",EMP-Aminu,PEN100000000050,40000,50000,90000', $body);
        $this->assertStringContainsString('"Schedule total",,,,,,151200.00', $body);
        $this->assertStringContainsString('Difference,,,,,,0.00', $body);

        $pdf = $this->get(route('payroll.statutory.export', ['schedule' => 'paye', 'month' => '2026-09', 'format' => 'pdf']));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));

        $this->get(route('payroll.statutory.show', ['schedule' => 'vat', 'month' => '2026-09']))->assertNotFound();
        $this->get(route('payroll.statutory.index', ['month' => 'September']))->assertSessionHasErrors('month');
    }

    public function test_schedules_need_permission_mask_numbers_for_viewers_and_stay_inside_the_business(): void
    {
        [$other] = $this->createTenantWithSubscription();
        $this->septemberPayroll();
        $tenant = $this->tenant;

        // Same business, payroll rights but no remittance permission.
        $this->get(route('payroll.statutory.index'))->assertForbidden();

        // A viewer sees schedules with numbers masked.
        $viewer = $this->createUserForTenant($tenant, ['view statutory-remittances']);
        $this->actingAs($viewer);
        $this->get(route('payroll.statutory.show', ['schedule' => 'paye', 'month' => '2026-09']))->assertOk()
            ->assertSee('****minu')->assertDontSee('TIN-Aminu');
        $this->get(route('payroll.statutory.show', ['schedule' => 'pension', 'month' => '2026-09']))->assertOk()
            ->assertDontSee('PEN100000000050');

        // Another business sees nothing of ours.
        $outsider = $this->createUserForTenant($other, ['view statutory-remittances', 'record statutory-remittances']);
        $this->actingAs($outsider);
        $this->get(route('payroll.statutory.show', ['schedule' => 'paye', 'month' => '2026-09']))->assertOk()
            ->assertDontSee('Aminu')->assertSee('Nothing owed');
        $this->assertEqualsWithDelta(0, app(StatutoryScheduleService::class)->build($other->id, 'pension', Carbon::parse('2026-09-01'))['total'], 0.001);
    }

    // ── Part 1: employee statutory details ─────────────────────────

    public function test_the_pencom_pfa_list_and_nigerian_states_are_seeded(): void
    {
        $this->assertSame(19, PensionFundAdministrator::whereNull('tenant_id')->count());
        $this->assertNotNull($this->pfa('Stanbic IBTC Pension Managers Limited'));
        $this->assertSame(37, State::whereHas('country', fn ($q) => $q->where('code', 'NG'))->count());
    }

    public function test_employee_form_saves_statutory_details_and_normalises_the_rsa_pin(): void
    {
        $this->createAuthenticatedUser(['create employees', 'view employees']);

        $this->get(route('employees.create'))->assertOk()->assertSee('RSA PIN')->assertSee('Stanbic IBTC Pension Managers Limited');

        $this->post(route('employees.store'), [
            'first_name' => 'Aminu', 'last_name' => 'Bello', 'hire_date' => '2026-01-05', 'employment_type' => 'full-time',
            'tax_id' => '1234567890', 'tax_state_id' => $this->state('Kano')->id,
            'pension_fund_administrator_id' => $this->pfa('Stanbic IBTC Pension Managers Limited')->id,
            'rsa_pin' => 'pen 1001 2345 6789', 'nhf_number' => 'NHF-778899',
        ])->assertRedirect(route('employees.index'));

        $employee = Employee::where('first_name', 'Aminu')->sole();
        $this->assertSame('PEN100123456789', $employee->rsa_pin);
        $this->assertSame('Kano', $employee->taxState->name);
        $this->assertSame('NHF-778899', $employee->nhf_number);
        // Stored encrypted, like the tax ID.
        $raw = DB::table('employees')->where('id', $employee->id)->value('rsa_pin');
        $this->assertStringNotContainsString('PEN100123456789', (string) $raw);
        // Not written in clear to the activity log either.
        $log = DB::table('activity_logs')->where('model_type', Employee::class)->where('model_id', $employee->id)->get();
        $this->assertNotEmpty($log);
        $this->assertStringNotContainsString('PEN100123456789', $log->toJson());
        $this->assertStringNotContainsString('NHF-778899', $log->toJson());
    }

    public function test_rsa_pin_must_be_pen_and_twelve_digits(): void
    {
        $this->createAuthenticatedUser(['create employees']);
        $base = ['first_name' => 'A', 'last_name' => 'B', 'hire_date' => '2026-01-05', 'employment_type' => 'full-time'];

        foreach (['PEN12345', 'PEN1234567890123', 'ABC123456789012', 'PEN12345678901X'] as $bad) {
            $this->post(route('employees.store'), $base + ['rsa_pin' => $bad])->assertSessionHasErrors('rsa_pin');
        }
        $this->post(route('employees.store'), $base + ['rsa_pin' => 'PEN200987654321'])->assertSessionHasNoErrors();
    }

    public function test_a_pfa_added_by_another_business_cannot_be_chosen(): void
    {
        [$other] = $this->createTenantWithSubscription();
        $this->createAuthenticatedUser(['create employees']);
        $theirs = PensionFundAdministrator::create(['tenant_id' => $other->id, 'name' => 'Their Own PFA']);
        $ours = PensionFundAdministrator::create(['tenant_id' => $this->tenant->id, 'name' => 'Our Own PFA']);
        $base = ['first_name' => 'A', 'last_name' => 'B', 'hire_date' => '2026-01-05', 'employment_type' => 'full-time'];

        $this->post(route('employees.store'), $base + ['pension_fund_administrator_id' => $theirs->id])
            ->assertSessionHasErrors('pension_fund_administrator_id');
        $this->post(route('employees.store'), $base + ['pension_fund_administrator_id' => $ours->id])->assertSessionHasNoErrors();
    }

    public function test_api_masks_rsa_pin_and_nhf_number_without_payroll_rights(): void
    {
        $this->createAuthenticatedUser(['view employees']);
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => 'EMP-90001', 'first_name' => 'Ngozi', 'last_name' => 'Eze',
            'hire_date' => '2026-01-01', 'tax_state_id' => $this->state('Lagos')->id,
            'pension_fund_administrator_id' => $this->pfa('Leadway Pensure PFA Limited')->id,
            'rsa_pin' => 'PEN100555666777', 'nhf_number' => 'NHF12345678',
        ]);

        $data = $this->getJson("/api/v1/employees/{$employee->id}")->assertOk()->json('data');
        $this->assertSame('****6777', $data['rsa_pin']);
        $this->assertSame('****5678', $data['nhf_number']);
        $this->assertSame('Lagos', $data['tax_state']['name']);
        $this->assertSame('Leadway Pensure PFA Limited', $data['pension_fund_administrator']['name']);

        $this->user->givePermissionTo(Permission::findOrCreate('edit payroll', 'web'));
        $data = $this->getJson("/api/v1/employees/{$employee->id}")->assertOk()->json('data');
        $this->assertSame('PEN100555666777', $data['rsa_pin']);
        $this->assertSame('NHF12345678', $data['nhf_number']);
    }

    public function test_employee_import_reads_statutory_columns(): void
    {
        Storage::fake('imports');
        $this->createAuthenticatedUser(['import data']);

        $csv = "name,email,hire_date,salary,tax_id,tax_state,pfa,rsa_pin,nhf_number\n"
            ."Musa Danladi,musa@example.com,2026-02-01,300000,2233445566,Kano,Stanbic IBTC Pension Managers Limited,PEN100111222333,NHF001\n"
            ."Bad Pin,bad@example.com,2026-02-01,300000,,Lagos,,PEN123,\n";
        $path = $this->tenant->id.'/employees.csv';
        Storage::disk('imports')->put($path, $csv);
        $import = Import::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'type' => Import::TYPE_EMPLOYEES,
            'format' => Import::FORMAT_CSV, 'status' => Import::STATUS_MAPPING,
            'original_filename' => 'employees.csv', 'file_path' => $path, 'file_size' => strlen($csv),
        ]);

        app(ImportService::class)->processImport($import);

        $musa = Employee::where('email', 'musa@example.com')->sole();
        $this->assertSame('Musa', $musa->first_name);
        $this->assertSame('Danladi', $musa->last_name);
        $this->assertSame('Kano', $musa->taxState->name);
        $this->assertSame('Stanbic IBTC Pension Managers Limited', $musa->pensionFundAdministrator->name);
        $this->assertSame('PEN100111222333', $musa->rsa_pin);
        $this->assertEqualsWithDelta(300000, (float) $musa->salary, 0.001);
        $this->assertSame(0, Employee::where('email', 'bad@example.com')->count());
        $this->assertStringContainsString('RSA PIN', implode(' ', $import->fresh()->errors ?? []));
    }
}
