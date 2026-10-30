<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Actions\LockDates\UpdateLockDates;
use App\Models\Employee;
use App\Models\Journal;
use App\Models\Payroll;
use App\Models\SalaryStructure;
use App\Models\StatutoryTaxTemplate;
use App\Models\User;
use App\Services\JournalService;
use App\Services\PayrollTaxService;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 14 (payroll journals):
 * - MB-17: the retroactive adjustment booked the net difference as basic
 *   salary with no PAYE, and a negative one couldn't post (Dr 0 / Cr 12,500);
 * - MB-56: net pay was dated the day the payroll was marked paid, not its
 *   pay date.
 */
class Session14PayrollJournalsTest extends TestCase
{
    private const PERMISSIONS = ['view payroll', 'create payroll', 'edit payroll', 'approve payroll'];

    private Employee $employee;

    private SalaryStructure $structure;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-20 09:00:00');
        $this->createAuthenticatedUser(self::PERMISSIONS);
        $this->subscription->update(['ends_at' => '2030-12-31']);
        StatutoryTaxTemplate::where('country_code', 'NGA')->where('tax_year', 2026)->sole()->applyToTenant($this->tenant->id);

        $this->structure = SalaryStructure::create(['tenant_id' => $this->tenant->id, 'name' => 'Officer', 'basic_salary' => 500000, 'effective_from' => '2026-01-01', 'is_active' => true]);
        $this->employee = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => Employee::generateEmployeeId($this->tenant->id),
            'first_name' => 'Amina', 'last_name' => 'Bello', 'hire_date' => '2026-01-05', 'salary_structure_id' => $this->structure->id,
        ]);
        $this->approver = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /** A September payroll, approved (cost posted) and paid. */
    private function paidSeptember(): Payroll
    {
        $this->post(route('payroll.generate'), ['month' => '2026-09', 'employee_ids' => [$this->employee->id]])->assertSessionHasNoErrors();
        $payroll = Payroll::where('employee_id', $this->employee->id)->sole();
        $payroll->approve($this->approver->id);
        $payroll->fresh()->markAsPaid();

        return $payroll->fresh();
    }

    public function test_the_repair_command_runs_from_cron_for_a_business_with_paid_payrolls(): void
    {
        $this->paidSeptember();

        // Signed out, as from cron: the employee eager load is tenant-scoped (audit MB-26).
        $this->artisan('payroll:fix-journals', ['tenant_id' => $this->tenant->id])
            ->expectsOutputToContain('Found 1 paid payroll records')
            ->expectsOutputToContain('Fixed 1 existing journals, created 0 missing journals. Errors: 0.')
            ->assertSuccessful();
    }

    /** The payslip a payroll run would make now (PAYE after pension and other reliefs). */
    private function payslip(): array
    {
        return app(PayrollTaxService::class)->payslipFigures($this->employee->fresh('salaryStructure.items'), $this->tenant->id, 0, [], '2026-09-30');
    }

    private function adjustTo(float $basic): Payroll
    {
        $this->structure->update(['basic_salary' => $basic]);
        // The preview (nothing saved) still shows.
        $this->post(route('payroll.retroactive-adjustment'), ['employee_id' => $this->employee->id, 'effective_from' => '2026-09-01'])->assertOk();
        $this->post(route('payroll.retroactive-adjustment'), ['employee_id' => $this->employee->id, 'effective_from' => '2026-09-01', 'recalculate' => 1])
            ->assertSessionHas('success');

        return Payroll::where('employee_id', $this->employee->id)->latest('id')->firstOrFail();
    }

    /** @return array<string, float> account code => debit - credit on the payroll's cost journal */
    private function lines(Payroll $payroll): array
    {
        $journal = Journal::where('reference_type', Payroll::class)->where('reference_id', $payroll->id)
            ->where('journal_type', JournalService::PAYROLL_ACCRUAL)->with('entries.account')->sole();
        $this->assertEqualsWithDelta((float) $journal->entries->sum('debit'), (float) $journal->entries->sum('credit'), 0.001, 'the journal balances');

        return $journal->entries->groupBy(fn ($e) => $e->account->account_code)
            ->map(fn ($e) => round((float) $e->sum('debit') - (float) $e->sum('credit'), 2))->all();
    }

    public function test_a_raise_books_the_extra_salary_and_its_paye(): void
    {
        $september = $this->paidSeptember();
        $adjustment = $this->adjustTo(600000);
        $now = $this->payslip();
        $taxDelta = round($now['tax_deduction'] - (float) $september->tax_deduction, 2);
        $pensionDelta = round($now['other_deductions'] - (float) $september->other_deductions, 2);

        $this->assertEqualsWithDelta(100000, (float) $adjustment->basic_salary, 0.001);
        $this->assertGreaterThan(0, $taxDelta);
        $this->assertEqualsWithDelta($taxDelta, (float) $adjustment->tax_deduction, 0.001);
        $this->assertEqualsWithDelta(8000, $pensionDelta, 0.001, 'statutory pension, 8% of the extra 100,000');
        $this->assertEqualsWithDelta($pensionDelta, (float) $adjustment->other_deductions, 0.001);
        $this->assertEqualsWithDelta(100000 - $taxDelta - $pensionDelta, (float) $adjustment->net_salary, 0.001);
        $this->assertEqualsWithDelta($now['tax_deduction'], (float) $september->tax_deduction + (float) $adjustment->tax_deduction, 0.001, 'PAYE now matches the recalculated tax');

        $adjustment->approve($this->approver->id);
        $lines = $this->lines($adjustment);
        $this->assertEqualsWithDelta(100000, $lines['6000'], 0.001, 'salaries');
        $this->assertEqualsWithDelta(-$taxDelta, $lines['2310'], 0.001, 'PAYE owed');
        $this->assertEqualsWithDelta(-(100000 - $taxDelta - $pensionDelta), $lines['2210'], 0.001, 'net pay owed');
    }

    public function test_a_cut_posts_a_balanced_journal_that_reduces_salary_and_liabilities(): void
    {
        $september = $this->paidSeptember();
        $adjustment = $this->adjustTo(400000);
        $taxDelta = round($this->payslip()['tax_deduction'] - (float) $september->tax_deduction, 2);

        $this->assertEqualsWithDelta(-100000, (float) $adjustment->basic_salary, 0.001);
        $this->assertLessThan(0, $taxDelta);
        $this->assertEqualsWithDelta($taxDelta, (float) $adjustment->tax_deduction, 0.001);

        $adjustment->approve($this->approver->id);
        $lines = $this->lines($adjustment);
        $this->assertEqualsWithDelta(-100000, $lines['6000'], 0.001, 'salary expense reduced');
        $this->assertEqualsWithDelta(-$taxDelta, $lines['2310'], 0.001, 'PAYE owed reduced (a debit)');
        $this->assertGreaterThan(0, $lines['2210'], 'net pay owed reduced (a debit)');

        // Paying it out: the employee hands back the overpayment.
        $adjustment->fresh()->markAsPaid();
        $payment = Journal::where('reference_id', $adjustment->id)->where('journal_type', JournalService::PAYROLL_PAYMENT)->with('entries')->sole();
        $this->assertEqualsWithDelta((float) $payment->entries->sum('debit'), (float) $payment->entries->sum('credit'), 0.001);
    }

    public function test_net_pay_is_dated_the_pay_date(): void
    {
        $september = $this->paidSeptember();

        $payment = Journal::where('reference_id', $september->id)->where('journal_type', JournalService::PAYROLL_PAYMENT)->sole();
        $this->assertSame($september->pay_date->toDateString(), $payment->journal_date->toDateString());
        $this->assertTrue($september->pay_date->lt(today()), 'paid on 20 October for September');
    }

    public function test_net_pay_dated_in_a_locked_period_goes_on_the_first_open_day_with_a_note(): void
    {
        $this->post(route('payroll.generate'), ['month' => '2026-09', 'employee_ids' => [$this->employee->id]])->assertSessionHasNoErrors();
        $payroll = Payroll::where('employee_id', $this->employee->id)->sole();
        $payroll->approve($this->approver->id);
        app(UpdateLockDates::class)->handle($this->tenant, ['staff_lock_date' => '2026-09-30', 'all_users_lock_date' => '2026-09-30', 'reason' => 'Q3 filed']);

        $payroll->fresh()->markAsPaid();

        $payment = Journal::where('reference_id', $payroll->id)->where('journal_type', JournalService::PAYROLL_PAYMENT)->sole();
        $this->assertSame('2026-10-01', $payment->journal_date->toDateString());
        $this->assertStringContainsString('a locked period', $payment->description);
    }
}
