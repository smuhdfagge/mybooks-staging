<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Payroll;
use App\Models\PayrollBatch;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureItem;
use App\Models\TaxBracket;
use App\Services\JournalService;
use App\Services\PayrollTaxService;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    protected function seedDefaultAccounts(int $tenantId): void
    {
        $accounts = [
            ['account_code' => '1000', 'name' => 'Cash', 'type' => 'asset'],
            ['account_code' => '1100', 'name' => 'Checking Account', 'type' => 'asset'],
            ['account_code' => '1250', 'name' => 'Employee Advances', 'type' => 'asset'],
            ['account_code' => '2210', 'name' => 'Accrued Salaries', 'type' => 'liability'],
            ['account_code' => '2300', 'name' => 'Payroll Liabilities', 'type' => 'liability'],
            ['account_code' => '2310', 'name' => 'Tax Payable', 'type' => 'liability'],
            ['account_code' => '2320', 'name' => 'Pension Payable', 'type' => 'liability'],
            ['account_code' => '2330', 'name' => 'Insurance Payable', 'type' => 'liability'],
            ['account_code' => '2340', 'name' => 'Union Dues Payable', 'type' => 'liability'],
            ['account_code' => '2360', 'name' => 'Garnishments Payable', 'type' => 'liability'],
            ['account_code' => '6000', 'name' => 'Salaries & Wages', 'type' => 'expense'],
            ['account_code' => '6020', 'name' => 'Payroll Taxes', 'type' => 'expense'],
            ['account_code' => '6030', 'name' => 'Allowances Expense', 'type' => 'expense'],
            ['account_code' => '6040', 'name' => 'Overtime Expense', 'type' => 'expense'],
            ['account_code' => '6050', 'name' => 'Employer Pension', 'type' => 'expense'],
            ['account_code' => '6060', 'name' => 'Employer Health Insurance', 'type' => 'expense'],
            ['account_code' => '6070', 'name' => 'Workers Compensation', 'type' => 'expense'],
        ];

        foreach ($accounts as $acct) {
            ChartOfAccount::firstOrCreate(
                ['tenant_id' => $tenantId, 'account_code' => $acct['account_code']],
                array_merge($acct, [
                    'tenant_id' => $tenantId,
                    'is_system' => true,
                    'is_active' => true,
                    'current_balance' => 0,
                ])
            );
        }
    }

    protected function createEmployee(array $attrs = []): Employee
    {
        return Employee::withoutEvents(function () use ($attrs) {
            return Employee::create(array_merge([
                'tenant_id' => $this->tenant->id,
                'employee_id' => 'EMP-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT),
                'first_name' => 'Test',
                'last_name' => 'Employee',
                'hire_date' => now()->subYear(),
                'status' => 'active',
            ], $attrs));
        });
    }

    protected function createPayroll(Employee $employee, array $attrs = []): Payroll
    {
        return Payroll::withoutEvents(function () use ($employee, $attrs) {
            return Payroll::create(array_merge([
                'tenant_id' => $this->tenant->id,
                'employee_id' => $employee->id,
                'payroll_number' => 'PAY-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT),
                'pay_period_start' => now()->startOfMonth(),
                'pay_period_end' => now()->endOfMonth(),
                'pay_date' => now(),
                'basic_salary' => 5000.00,
                'allowances' => 0,
                'overtime_hours' => 0,
                'overtime_amount' => 0,
                'gross_salary' => 5000.00,
                'tax_deduction' => 0,
                'other_deductions' => 0,
                'total_deductions' => 0,
                'net_salary' => 5000.00,
                'status' => Payroll::STATUS_DRAFT,
                'created_by' => $this->user->id,
            ], $attrs));
        });
    }

    // ── Salary Calculation Tests ────────────────────────────────

    public function test_calculate_totals_computes_correctly(): void
    {
        $this->createAuthenticatedUser();
        $employee = $this->createEmployee();

        $payroll = $this->createPayroll($employee, [
            'basic_salary' => 5000,
            'allowances' => 1000,
            'overtime_amount' => 500,
            'tax_deduction' => 650,
            'other_deductions' => 100,
        ]);

        $payroll->calculateTotals();

        $this->assertEquals(6500.00, (float) $payroll->gross_salary);
        $this->assertEquals(750.00, (float) $payroll->total_deductions);
        $this->assertEquals(5750.00, (float) $payroll->net_salary);
    }

    public function test_gross_salary_includes_basic_allowances_and_overtime(): void
    {
        $this->createAuthenticatedUser();
        $employee = $this->createEmployee();

        $payroll = $this->createPayroll($employee, [
            'basic_salary' => 3000,
            'allowances' => 500,
            'overtime_amount' => 200,
        ]);

        $payroll->calculateTotals();

        $this->assertEquals(3700.00, (float) $payroll->gross_salary);
    }

    // ── Journal Balance Verification Tests ──────────────────────

    public function test_journal_debits_equal_credits_basic_payroll(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $employee = $this->createEmployee();
        $payroll = $this->createPayroll($employee, [
            'basic_salary' => 5000,
            'allowances' => 0,
            'gross_salary' => 5000,
            'tax_deduction' => 500,
            'other_deductions' => 0,
            'total_deductions' => 500,
            'net_salary' => 4500,
            'status' => Payroll::STATUS_APPROVED,
            'payment_method' => 'bank_transfer',
        ]);

        $payroll->markAsPaid();

        $journal = Journal::where('reference_type', Payroll::class)
            ->where('reference_id', $payroll->id)
            ->first();

        $this->assertNotNull($journal);

        $totalDebit = $journal->entries()->sum('debit');
        $totalCredit = $journal->entries()->sum('credit');

        $this->assertEquals(
            round($totalDebit, 2),
            round($totalCredit, 2),
            'Journal debits must equal credits'
        );
    }

    public function test_journal_debits_equal_credits_with_allowances_and_overtime(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $employee = $this->createEmployee();
        $payroll = $this->createPayroll($employee, [
            'basic_salary' => 4000,
            'allowances' => 800,
            'overtime_hours' => 10,
            'overtime_amount' => 500,
            'gross_salary' => 5300,
            'tax_deduction' => 530,
            'other_deductions' => 200,
            'total_deductions' => 730,
            'net_salary' => 4570,
            'status' => Payroll::STATUS_APPROVED,
            'payment_method' => 'bank_transfer',
        ]);

        $payroll->markAsPaid();

        $journal = Journal::where('reference_type', Payroll::class)
            ->where('reference_id', $payroll->id)
            ->first();

        $this->assertNotNull($journal);

        $totalDebit = $journal->entries()->sum('debit');
        $totalCredit = $journal->entries()->sum('credit');

        $this->assertEquals(
            round($totalDebit, 2),
            round($totalCredit, 2),
            'Journal debits must equal credits'
        );
    }

    public function test_journal_maps_basic_salary_to_6000_account(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $employee = $this->createEmployee();
        $payroll = $this->createPayroll($employee, [
            'basic_salary' => 5000,
            'allowances' => 1000,
            'gross_salary' => 6000,
            'tax_deduction' => 600,
            'total_deductions' => 600,
            'net_salary' => 5400,
            'status' => Payroll::STATUS_APPROVED,
            'payment_method' => 'bank_transfer',
        ]);

        $payroll->markAsPaid();

        $journal = Journal::where('reference_type', Payroll::class)
            ->where('reference_id', $payroll->id)->first();

        // Basic salary debits 6000 (Salaries & Wages) — NOT the full gross
        $salaryEntry = $journal->entries()
            ->whereHas('account', fn($q) => $q->where('account_code', '6000'))
            ->first();
        $this->assertEquals(5000.00, (float) $salaryEntry->debit);
    }

    public function test_journal_maps_allowances_to_6030_account(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $employee = $this->createEmployee();
        $payroll = $this->createPayroll($employee, [
            'basic_salary' => 5000,
            'allowances' => 1200,
            'gross_salary' => 6200,
            'tax_deduction' => 620,
            'total_deductions' => 620,
            'net_salary' => 5580,
            'status' => Payroll::STATUS_APPROVED,
            'payment_method' => 'bank_transfer',
        ]);

        $payroll->markAsPaid();

        $journal = Journal::where('reference_type', Payroll::class)
            ->where('reference_id', $payroll->id)->first();

        $allowanceEntry = $journal->entries()
            ->whereHas('account', fn($q) => $q->where('account_code', '6030'))
            ->first();
        $this->assertNotNull($allowanceEntry);
        $this->assertEquals(1200.00, (float) $allowanceEntry->debit);
    }

    public function test_journal_maps_overtime_to_6040_account(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $employee = $this->createEmployee();
        $payroll = $this->createPayroll($employee, [
            'basic_salary' => 5000,
            'allowances' => 0,
            'overtime_hours' => 8,
            'overtime_amount' => 400,
            'gross_salary' => 5400,
            'tax_deduction' => 540,
            'total_deductions' => 540,
            'net_salary' => 4860,
            'status' => Payroll::STATUS_APPROVED,
            'payment_method' => 'bank_transfer',
        ]);

        $payroll->markAsPaid();

        $journal = Journal::where('reference_type', Payroll::class)
            ->where('reference_id', $payroll->id)->first();

        $overtimeEntry = $journal->entries()
            ->whereHas('account', fn($q) => $q->where('account_code', '6040'))
            ->first();
        $this->assertNotNull($overtimeEntry);
        $this->assertEquals(400.00, (float) $overtimeEntry->debit);
    }

    public function test_no_overtime_double_counting(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $employee = $this->createEmployee();

        // gross = basic(4000) + allowances(500) + overtime(300) = 4800
        $payroll = $this->createPayroll($employee, [
            'basic_salary' => 4000,
            'allowances' => 500,
            'overtime_amount' => 300,
            'gross_salary' => 4800,
            'tax_deduction' => 0,
            'other_deductions' => 0,
            'total_deductions' => 0,
            'net_salary' => 4800,
            'status' => Payroll::STATUS_APPROVED,
            'payment_method' => 'cash',
        ]);

        $payroll->markAsPaid();

        $journal = Journal::where('reference_type', Payroll::class)
            ->where('reference_id', $payroll->id)->first();

        $totalDebit = $journal->entries()->sum('debit');
        $totalCredit = $journal->entries()->sum('credit');

        // Total debit should be exactly basic + allowances + overtime = 4800
        $this->assertEquals(4800.00, round($totalDebit, 2));
        // Total credit should also be 4800 (net_salary since no deductions)
        $this->assertEquals(4800.00, round($totalCredit, 2));

        // Verify overtime is NOT counted twice
        $salaryDebit = $journal->entries()
            ->whereHas('account', fn($q) => $q->where('account_code', '6000'))
            ->sum('debit');
        $this->assertEquals(4000.00, round($salaryDebit, 2), 'Salary account should only have basic salary');
    }

    public function test_journal_employer_contributions_balance(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $employee = $this->createEmployee();
        $payroll = $this->createPayroll($employee, [
            'basic_salary' => 5000,
            'allowances' => 0,
            'gross_salary' => 5000,
            'tax_deduction' => 500,
            'other_deductions' => 0,
            'total_deductions' => 500,
            'net_salary' => 4500,
            'employer_contributions' => 350,
            'status' => Payroll::STATUS_APPROVED,
            'payment_method' => 'bank_transfer',
        ]);

        $payroll->markAsPaid();

        $journal = Journal::where('reference_type', Payroll::class)
            ->where('reference_id', $payroll->id)->first();

        $totalDebit = $journal->entries()->sum('debit');
        $totalCredit = $journal->entries()->sum('credit');

        // Debits: 5000 (salary) + 350 (employer contributions) = 5350
        // Credits: 4500 (net pay) + 500 (tax) + 350 (employer contributions payable) = 5350
        $this->assertEquals(round($totalDebit, 2), round($totalCredit, 2));
        $this->assertEquals(5350.00, round($totalDebit, 2));
    }

    // ── Status Workflow Tests ───────────────────────────────────

    public function test_only_approved_payroll_can_be_marked_as_paid(): void
    {
        $this->createAuthenticatedUser();
        $employee = $this->createEmployee();

        $payroll = $this->createPayroll($employee, ['status' => Payroll::STATUS_DRAFT]);
        $this->assertFalse($payroll->markAsPaid());
        $this->assertEquals(Payroll::STATUS_DRAFT, $payroll->fresh()->status);

        $payroll2 = $this->createPayroll($employee, ['status' => Payroll::STATUS_PENDING]);
        $this->assertFalse($payroll2->markAsPaid());

        $payroll3 = $this->createPayroll($employee, ['status' => Payroll::STATUS_CANCELLED]);
        $this->assertFalse($payroll3->markAsPaid());
    }

    public function test_approved_payroll_can_be_marked_as_paid(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $employee = $this->createEmployee();
        $payroll = $this->createPayroll($employee, [
            'status' => Payroll::STATUS_APPROVED,
            'payment_method' => 'bank_transfer',
        ]);

        $this->assertTrue($payroll->markAsPaid());
        $this->assertEquals(Payroll::STATUS_PAID, $payroll->fresh()->status);
    }

    public function test_is_paid_helper(): void
    {
        $this->createAuthenticatedUser();
        $employee = $this->createEmployee();

        $payroll = $this->createPayroll($employee, ['status' => Payroll::STATUS_DRAFT]);
        $this->assertFalse($payroll->isPaid());

        $payroll->update(['status' => Payroll::STATUS_PAID]);
        $this->assertTrue($payroll->fresh()->isPaid());
    }

    // ── Segregation of Duties Tests ─────────────────────────────

    public function test_creator_cannot_approve_own_payroll(): void
    {
        $user = $this->createAuthenticatedUser(['create payroll', 'approve payroll']);
        $employee = $this->createEmployee();
        $payroll = $this->createPayroll($employee, [
            'status' => Payroll::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        $response = $this->post(route('payroll.approve', $payroll));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals(Payroll::STATUS_DRAFT, $payroll->fresh()->status);
    }

    public function test_different_user_can_approve_payroll(): void
    {
        $creator = $this->createAuthenticatedUser(['create payroll', 'approve payroll', 'view payroll']);
        $employee = $this->createEmployee();
        $payroll = $this->createPayroll($employee, [
            'status' => Payroll::STATUS_DRAFT,
            'created_by' => $creator->id,
        ]);

        // Create a different approver user
        $approver = $this->createUserForTenant($this->tenant, ['approve payroll', 'view payroll']);
        $this->actingAs($approver);

        $response = $this->post(route('payroll.approve', $payroll));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertEquals(Payroll::STATUS_APPROVED, $payroll->fresh()->status);
        $this->assertEquals($approver->id, $payroll->fresh()->approved_by);
    }

    // ── Progressive Tax Tests ───────────────────────────────────

    public function test_flat_tax_fallback_when_no_brackets(): void
    {
        $this->createAuthenticatedUser();

        $taxService = new PayrollTaxService();
        $result = $taxService->calculateTax(10000, $this->tenant->id, 15);

        $this->assertEquals('flat', $result['method']);
        $this->assertEquals(1500.00, $result['tax']);
    }

    public function test_progressive_tax_with_brackets(): void
    {
        $this->createAuthenticatedUser();

        // Create tax brackets: 0-5000 @ 0%, 5001-20000 @ 10%, 20001+ @ 20%
        TaxBracket::create([
            'tenant_id' => $this->tenant->id,
            'name' => '0% Bracket',
            'min_amount' => 0,
            'max_amount' => 5000,
            'rate' => 0,
            'fixed_amount' => 0,
            'period' => 'monthly',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        TaxBracket::create([
            'tenant_id' => $this->tenant->id,
            'name' => '10% Bracket',
            'min_amount' => 5000,
            'max_amount' => 20000,
            'rate' => 10,
            'fixed_amount' => 0,
            'period' => 'monthly',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        TaxBracket::create([
            'tenant_id' => $this->tenant->id,
            'name' => '20% Bracket',
            'min_amount' => 20000,
            'max_amount' => null,
            'rate' => 20,
            'fixed_amount' => 0,
            'period' => 'monthly',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $taxService = new PayrollTaxService();

        // Income 10000 → first 5000 @ 0% = 0, next 5000 @ 10% = 500
        $result = $taxService->calculateTax(10000, $this->tenant->id, 0, 'monthly');
        $this->assertEquals('progressive', $result['method']);
        $this->assertEquals(500.00, $result['tax']);

        // Income 25000 → 5000@0% + 15000@10% + 5000@20% = 0 + 1500 + 1000 = 2500
        $result2 = $taxService->calculateTax(25000, $this->tenant->id, 0, 'monthly');
        $this->assertEquals(2500.00, $result2['tax']);

        // Income 3000 → all in 0% bracket
        $result3 = $taxService->calculateTax(3000, $this->tenant->id, 0, 'monthly');
        $this->assertEquals(0.00, $result3['tax']);
    }

    public function test_zero_taxable_income_returns_zero(): void
    {
        $this->createAuthenticatedUser();

        $taxService = new PayrollTaxService();
        $result = $taxService->calculateTax(0, $this->tenant->id, 25);

        $this->assertEquals(0, $result['tax']);
        $this->assertEquals('none', $result['method']);
    }

    // ── Employer Contribution Tests ─────────────────────────────

    public function test_employer_percentage_contributions(): void
    {
        $taxService = new PayrollTaxService();

        $result = $taxService->calculateEmployerContributions(10000, [
            ['name' => 'Pension', 'type' => 'percentage', 'rate' => 5],
            ['name' => 'Health', 'type' => 'percentage', 'rate' => 3],
        ]);

        $this->assertEquals(800.00, $result['total']);
        $this->assertCount(2, $result['details']);
        $this->assertEquals(500.00, $result['details'][0]['amount']);
        $this->assertEquals(300.00, $result['details'][1]['amount']);
    }

    public function test_employer_fixed_contributions(): void
    {
        $taxService = new PayrollTaxService();

        $result = $taxService->calculateEmployerContributions(10000, [
            ['name' => 'Workers Comp', 'type' => 'fixed', 'rate' => 150],
        ]);

        $this->assertEquals(150.00, $result['total']);
    }

    public function test_employer_contributions_with_cap(): void
    {
        $taxService = new PayrollTaxService();

        $result = $taxService->calculateEmployerContributions(100000, [
            ['name' => 'Social Security', 'type' => 'percentage', 'rate' => 6.2, 'cap' => 1000],
        ]);

        // 100000 * 6.2% = 6200, but capped at 1000
        $this->assertEquals(1000.00, $result['total']);
    }

    // ── Duplicate Period Protection Tests ────────────────────────

    public function test_duplicate_payroll_period_prevented_in_batch_generation(): void
    {
        $this->createAuthenticatedUser(['create payroll', 'view payroll']);
        $this->seedDefaultAccounts($this->tenant->id);

        $employee = $this->createEmployee();

        $payPeriodStart = now()->startOfMonth();
        $payPeriodEnd = now()->endOfMonth();

        // Simulate the duplicate check logic from PayrollController::generate()
        Payroll::withoutEvents(function () use ($employee, $payPeriodStart, $payPeriodEnd) {
            return Payroll::create([
                'tenant_id' => $this->tenant->id,
                'employee_id' => $employee->id,
                'payroll_number' => 'PAY-DUP001',
                'pay_period_start' => $payPeriodStart,
                'pay_period_end' => $payPeriodEnd,
                'pay_date' => now(),
                'basic_salary' => 5000,
                'allowances' => 0,
                'overtime_hours' => 0,
                'overtime_amount' => 0,
                'gross_salary' => 5000,
                'tax_deduction' => 0,
                'other_deductions' => 0,
                'total_deductions' => 0,
                'net_salary' => 5000,
                'status' => Payroll::STATUS_DRAFT,
                'created_by' => $this->user->id,
            ]);
        });

        // Replicate the duplicate check — use whereDate for SQLite compatibility
        $existingPayrolls = Payroll::where('employee_id', $employee->id)
            ->whereDate('pay_period_start', $payPeriodStart->toDateString())
            ->whereDate('pay_period_end', $payPeriodEnd->toDateString())
            ->pluck('employee_id')
            ->toArray();

        $this->assertNotEmpty($existingPayrolls, 'Duplicate check should detect existing payroll');
        $this->assertContains($employee->id, $existingPayrolls);
    }

    // ── Payroll Deletion Journal Cleanup ─────────────────────────

    public function test_deleting_paid_payroll_reverses_its_journal(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $employee = $this->createEmployee();
        $payroll = $this->createPayroll($employee, [
            'basic_salary' => 3000,
            'gross_salary' => 3000,
            'net_salary' => 3000,
            'status' => Payroll::STATUS_APPROVED,
            'payment_method' => 'cash',
        ]);

        $payroll->markAsPaid();

        $journal = Journal::where('reference_type', Payroll::class)
            ->where('reference_id', $payroll->id)->first();
        $this->assertNotNull($journal);

        $payroll->delete();

        // The original journal is kept and a reversing journal is posted,
        // so the ledger shows both (finding M6). Previously it was force-deleted.
        $journals = Journal::withTrashed()->where('reference_type', Payroll::class)
            ->where('reference_id', $payroll->id)->orderBy('id')->get();
        $this->assertCount(2, $journals);
        $this->assertSame('reversed', $journals[0]->status);
        $this->assertSame('REV-'.$journals[0]->journal_number, $journals[1]->reference);

        // Every account's lines across both journals net to zero
        $net = \App\Models\JournalEntry::whereIn('journal_id', $journals->pluck('id'))
            ->selectRaw('account_id, SUM(debit) - SUM(credit) as net')
            ->groupBy('account_id')->pluck('net');
        foreach ($net as $value) {
            $this->assertEqualsWithDelta(0, (float) $value, 0.001);
        }
    }

    // ── Account Balance Tests ────────────────────────────────────

    public function test_payroll_updates_account_balances(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $employee = $this->createEmployee();
        $payroll = $this->createPayroll($employee, [
            'basic_salary' => 5000,
            'allowances' => 1000,
            'gross_salary' => 6000,
            'tax_deduction' => 600,
            'total_deductions' => 600,
            'net_salary' => 5400,
            'status' => Payroll::STATUS_APPROVED,
            'payment_method' => 'bank_transfer',
        ]);

        $payroll->markAsPaid();

        $salaryAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('account_code', '6000')->first();
        $allowanceAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('account_code', '6030')->first();
        $bankAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('account_code', '1100')->first();
        $taxPayableAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('account_code', '2310')->first();

        // Expense accounts: debit increases balance
        $this->assertEquals(5000.00, (float) $salaryAccount->current_balance);
        $this->assertEquals(1000.00, (float) $allowanceAccount->current_balance);

        // Asset account (bank): credit decreases balance
        $this->assertEquals(-5400.00, (float) $bankAccount->current_balance);

        // Tax withheld goes to 2310 (Tax Payable)
        $this->assertEquals(600.00, (float) $taxPayableAccount->current_balance);
    }

    // ── Batch Processing Tests ───────────────────────────────────

    public function test_batch_mark_as_paid_creates_journals_for_all(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $batch = PayrollBatch::create([
            'tenant_id' => $this->tenant->id,
            'batch_number' => 'PBN-000001',
            'pay_period_start' => now()->startOfMonth(),
            'pay_period_end' => now()->endOfMonth(),
            'status' => PayrollBatch::STATUS_APPROVED,
            'created_by' => $this->user->id,
        ]);

        $emp1 = $this->createEmployee(['first_name' => 'Alice']);
        $emp2 = $this->createEmployee(['first_name' => 'Bob']);

        $p1 = $this->createPayroll($emp1, [
            'payroll_batch_id' => $batch->id,
            'basic_salary' => 3000, 'gross_salary' => 3000, 'net_salary' => 3000,
            'status' => Payroll::STATUS_APPROVED, 'payment_method' => 'bank_transfer',
        ]);
        $p2 = $this->createPayroll($emp2, [
            'payroll_batch_id' => $batch->id,
            'basic_salary' => 4000, 'gross_salary' => 4000, 'net_salary' => 4000,
            'status' => Payroll::STATUS_APPROVED, 'payment_method' => 'bank_transfer',
        ]);

        foreach ($batch->payrolls()->where('status', 'approved')->get() as $payroll) {
            $payroll->markAsPaid();
        }

        $this->assertNotNull(Journal::where('reference_type', Payroll::class)->where('reference_id', $p1->id)->first());
        $this->assertNotNull(Journal::where('reference_type', Payroll::class)->where('reference_id', $p2->id)->first());
    }

    // ── HTTP Route Tests ─────────────────────────────────────────

    public function test_paid_payroll_cannot_be_edited(): void
    {
        $user = $this->createAuthenticatedUser(['edit payroll', 'view payroll']);
        $employee = $this->createEmployee();
        $payroll = $this->createPayroll($employee, ['status' => Payroll::STATUS_PAID]);

        $response = $this->get(route('payroll.edit', $payroll));

        $response->assertRedirect(route('payroll.show', $payroll));
        $response->assertSessionHas('error');
    }

    public function test_paid_payroll_cannot_be_deleted(): void
    {
        $user = $this->createAuthenticatedUser(['delete payroll', 'view payroll']);
        $employee = $this->createEmployee();
        $payroll = $this->createPayroll($employee, ['status' => Payroll::STATUS_PAID]);

        $response = $this->delete(route('payroll.destroy', $payroll));

        $response->assertRedirect(route('payroll.index'));
        $response->assertSessionHas('error');
        $this->assertNotNull($payroll->fresh());
    }

    public function test_only_draft_can_be_approved(): void
    {
        $creator = $this->createAuthenticatedUser(['create payroll', 'approve payroll', 'view payroll']);
        $employee = $this->createEmployee();

        // Create payroll by a different user so SoD check passes
        $payroll = $this->createPayroll($employee, [
            'status' => Payroll::STATUS_APPROVED,
            'created_by' => $this->createUserForTenant($this->tenant)->id,
        ]);

        $response = $this->post(route('payroll.approve', $payroll));
        $response->assertSessionHas('error');
    }

    // ── Granular Account Mapping Tests ───────────────────────────

    public function test_tax_withheld_posts_to_tax_payable_account(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $employee = $this->createEmployee();
        $payroll = $this->createPayroll($employee, [
            'basic_salary' => 5000,
            'gross_salary' => 5000,
            'tax_deduction' => 500,
            'total_deductions' => 500,
            'net_salary' => 4500,
            'status' => Payroll::STATUS_APPROVED,
            'payment_method' => 'bank_transfer',
        ]);

        $payroll->markAsPaid();

        $journal = Journal::where('reference_type', Payroll::class)
            ->where('reference_id', $payroll->id)->first();

        $taxPayableEntry = $journal->entries()->whereHas('account', fn($q) => $q->where('account_code', '2310'))->first();
        $this->assertNotNull($taxPayableEntry);
        $this->assertEquals(500.00, (float) $taxPayableEntry->credit);
    }

    public function test_deductions_split_by_type_to_correct_liability_accounts(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $employee = $this->createEmployee();
        $payroll = $this->createPayroll($employee, [
            'basic_salary' => 10000,
            'gross_salary' => 10000,
            'tax_deduction' => 1000,
            'other_deductions' => 900,
            'total_deductions' => 1900,
            'net_salary' => 8100,
            'deduction_details' => [
                ['name' => 'Employee Pension', 'amount_type' => 'fixed', 'rate' => 300, 'amount' => 300],
                ['name' => 'Health Insurance', 'amount_type' => 'fixed', 'rate' => 200, 'amount' => 200],
                ['name' => 'Union Dues', 'amount_type' => 'fixed', 'rate' => 150, 'amount' => 150],
                ['name' => 'Garnishment - Child Support', 'amount_type' => 'fixed', 'rate' => 250, 'amount' => 250],
            ],
            'status' => Payroll::STATUS_APPROVED,
            'payment_method' => 'bank_transfer',
        ]);

        $payroll->markAsPaid();

        $journal = Journal::where('reference_type', Payroll::class)
            ->where('reference_id', $payroll->id)->first();

        // Tax → 2310
        $taxEntry = $journal->entries()->whereHas('account', fn($q) => $q->where('account_code', '2310'))->first();
        $this->assertEquals(1000.00, (float) $taxEntry->credit);

        // Pension → 2320
        $pensionEntry = $journal->entries()->whereHas('account', fn($q) => $q->where('account_code', '2320'))->first();
        $this->assertEquals(300.00, (float) $pensionEntry->credit);

        // Insurance → 2330
        $insuranceEntry = $journal->entries()->whereHas('account', fn($q) => $q->where('account_code', '2330'))->first();
        $this->assertEquals(200.00, (float) $insuranceEntry->credit);

        // Union Dues → 2340
        $unionEntry = $journal->entries()->whereHas('account', fn($q) => $q->where('account_code', '2340'))->first();
        $this->assertEquals(150.00, (float) $unionEntry->credit);

        // Garnishment → 2360
        $garnishEntry = $journal->entries()->whereHas('account', fn($q) => $q->where('account_code', '2360'))->first();
        $this->assertEquals(250.00, (float) $garnishEntry->credit);
    }

    public function test_employer_contributions_split_to_correct_expense_and_liability(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $employee = $this->createEmployee();
        $payroll = $this->createPayroll($employee, [
            'basic_salary' => 10000,
            'gross_salary' => 10000,
            'tax_deduction' => 0,
            'total_deductions' => 0,
            'net_salary' => 10000,
            'employer_contributions' => 1500,
            'employer_contribution_details' => [
                ['name' => 'Employer Pension', 'type' => 'fixed', 'rate' => 500, 'cap' => null, 'amount' => 500],
                ['name' => 'Employer Health Insurance', 'type' => 'fixed', 'rate' => 700, 'cap' => null, 'amount' => 700],
                ['name' => 'Workers Compensation', 'type' => 'fixed', 'rate' => 300, 'cap' => null, 'amount' => 300],
            ],
            'status' => Payroll::STATUS_APPROVED,
            'payment_method' => 'bank_transfer',
        ]);

        $payroll->markAsPaid();

        $journal = Journal::where('reference_type', Payroll::class)
            ->where('reference_id', $payroll->id)->first();

        // Expense side: debits
        $pensionExpense = $journal->entries()->whereHas('account', fn($q) => $q->where('account_code', '6050'))->first();
        $this->assertNotNull($pensionExpense);
        $this->assertEquals(500.00, (float) $pensionExpense->debit);

        $healthExpense = $journal->entries()->whereHas('account', fn($q) => $q->where('account_code', '6060'))->first();
        $this->assertNotNull($healthExpense);
        $this->assertEquals(700.00, (float) $healthExpense->debit);

        $wcExpense = $journal->entries()->whereHas('account', fn($q) => $q->where('account_code', '6070'))->first();
        $this->assertNotNull($wcExpense);
        $this->assertEquals(300.00, (float) $wcExpense->debit);

        // Liability side: credits
        $pensionPayable = $journal->entries()->whereHas('account', fn($q) => $q->where('account_code', '2320'))->first();
        $this->assertNotNull($pensionPayable);
        $this->assertEquals(500.00, (float) $pensionPayable->credit);

        $insurancePayable = $journal->entries()->whereHas('account', fn($q) => $q->where('account_code', '2330'))->first();
        $this->assertNotNull($insurancePayable);
        $this->assertEquals(700.00, (float) $insurancePayable->credit);
    }

    public function test_unrecognized_deductions_fall_back_to_payroll_liabilities(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $employee = $this->createEmployee();
        $payroll = $this->createPayroll($employee, [
            'basic_salary' => 5000,
            'gross_salary' => 5000,
            'tax_deduction' => 0,
            'other_deductions' => 200,
            'total_deductions' => 200,
            'net_salary' => 4800,
            'deduction_details' => [
                ['name' => 'Miscellaneous Deduction', 'amount_type' => 'fixed', 'rate' => 200, 'amount' => 200],
            ],
            'status' => Payroll::STATUS_APPROVED,
            'payment_method' => 'bank_transfer',
        ]);

        $payroll->markAsPaid();

        $journal = Journal::where('reference_type', Payroll::class)
            ->where('reference_id', $payroll->id)->first();

        // Unrecognized deduction falls back to 2300
        $fallbackEntry = $journal->entries()->whereHas('account', fn($q) => $q->where('account_code', '2300'))->first();
        $this->assertNotNull($fallbackEntry);
        $this->assertEquals(200.00, (float) $fallbackEntry->credit);
    }

    public function test_new_accounts_exist_in_default_chart(): void
    {
        $accounts = \App\Services\ChartOfAccountService::getDefaultAccounts();
        $codes = array_column($accounts, 'account_code');

        // Liability sub-accounts
        $this->assertContains('2210', $codes, 'Accrued Salaries missing');
        $this->assertContains('2310', $codes, 'Tax Payable missing');
        $this->assertContains('2320', $codes, 'Pension Payable missing');
        $this->assertContains('2330', $codes, 'Insurance Payable missing');
        $this->assertContains('2340', $codes, 'Union Dues Payable missing');
        $this->assertContains('2360', $codes, 'Garnishments Payable missing');

        // Employer expense accounts
        $this->assertContains('6050', $codes, 'Employer Pension missing');
        $this->assertContains('6060', $codes, 'Employer Health Insurance missing');
        $this->assertContains('6070', $codes, 'Workers Compensation missing');

        // Asset accounts
        $this->assertContains('1250', $codes, 'Employee Advances missing');
    }
}
