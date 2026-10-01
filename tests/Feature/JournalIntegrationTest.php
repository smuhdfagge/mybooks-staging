<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\Journal;
use App\Models\Payroll;
use App\Models\SalesReceipt;
use App\Models\Vendor;
use App\Services\JournalService;
use Tests\TestCase;

class JournalIntegrationTest extends TestCase
{
    /**
     * Seed the default chart of accounts used by JournalService constants.
     */
    protected function seedDefaultAccounts(int $tenantId): void
    {
        $accounts = [
            ['account_code' => '1000', 'name' => 'Cash', 'type' => 'asset'],
            ['account_code' => '1100', 'name' => 'Checking Account', 'type' => 'asset'],
            ['account_code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset'],
            ['account_code' => '1300', 'name' => 'Inventory', 'type' => 'asset'],
            ['account_code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability'],
            ['account_code' => '2300', 'name' => 'Payroll Liabilities', 'type' => 'liability'],
            ['account_code' => '2400', 'name' => 'Sales Tax Payable', 'type' => 'liability'],
            ['account_code' => '4000', 'name' => 'Sales Revenue', 'type' => 'income'],
            ['account_code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => 'expense'],
            ['account_code' => '6000', 'name' => 'Salaries & Wages', 'type' => 'expense'],
            ['account_code' => '6020', 'name' => 'Payroll Taxes', 'type' => 'expense'],
            ['account_code' => '6030', 'name' => 'Allowances Expense', 'type' => 'expense'],
            ['account_code' => '6040', 'name' => 'Overtime Expense', 'type' => 'expense'],
        ];

        foreach ($accounts as $acct) {
            $account = ChartOfAccount::firstOrCreate(
                ['tenant_id' => $tenantId, 'account_code' => $acct['account_code']],
                array_merge($acct, [
                    'tenant_id' => $tenantId,
                    'is_system' => true,
                    'is_active' => true,
                    'current_balance' => 0,
                ])
            );
            // Reset balance for clean test state
            $account->update(['current_balance' => 0]);
        }
    }

    // ── SalesReceipt Journal Tests ──────────────────────────────

    public function test_sales_receipt_creates_journal_on_save(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $receipt = SalesReceipt::withoutEvents(function () {
            return SalesReceipt::create([
                'tenant_id' => $this->tenant->id,
                'receipt_number' => 'SR-000001',
                'receipt_date' => now(),
                'payment_method' => 'cash',
                'subtotal' => 1000.00,
                'tax_amount' => 50.00,
                'discount_amount' => 0,
                'total' => 1050.00,
                'created_by' => $this->user->id,
            ]);
        });

        // Manually trigger journal creation (since we used withoutEvents)
        $journal = $receipt->createJournalEntry();

        $this->assertNotNull($journal);
        $this->assertEquals('posted', $journal->status);
        $this->assertEquals(SalesReceipt::class, $journal->reference_type);
        $this->assertEquals($receipt->id, $journal->reference_id);

        // Verify debit entries (Cash = 1050)
        $cashEntry = $journal->entries()->whereHas('account', fn ($q) => $q->where('account_code', '1000'))->first();
        $this->assertEquals(1050.00, (float) $cashEntry->debit);

        // Verify credit entries (Revenue = 1000, Sales Tax = 50)
        $revenueEntry = $journal->entries()->whereHas('account', fn ($q) => $q->where('account_code', '4000'))->first();
        $this->assertEquals(1000.00, (float) $revenueEntry->credit);

        $taxEntry = $journal->entries()->whereHas('account', fn ($q) => $q->where('account_code', '2400'))->first();
        $this->assertEquals(50.00, (float) $taxEntry->credit);
    }

    public function test_sales_receipt_with_zero_total_skips_journal(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $receipt = SalesReceipt::withoutEvents(function () {
            return SalesReceipt::create([
                'tenant_id' => $this->tenant->id,
                'receipt_number' => 'SR-000002',
                'receipt_date' => now(),
                'payment_method' => 'cash',
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total' => 0,
                'created_by' => $this->user->id,
            ]);
        });

        $journal = $receipt->createJournalEntry();
        $this->assertNull($journal);
    }

    public function test_sales_receipt_updates_account_balances(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $receipt = SalesReceipt::withoutEvents(function () {
            return SalesReceipt::create([
                'tenant_id' => $this->tenant->id,
                'receipt_number' => 'SR-000003',
                'receipt_date' => now(),
                'payment_method' => 'cash',
                'subtotal' => 500.00,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total' => 500.00,
                'created_by' => $this->user->id,
            ]);
        });

        $receipt->createJournalEntry();

        $cashAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('account_code', '1000')
            ->first();
        $revenueAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('account_code', '4000')
            ->first();

        $this->assertEquals(500.00, (float) $cashAccount->current_balance);
        $this->assertEquals(500.00, (float) $revenueAccount->current_balance);
    }

    // ── Payroll Journal Tests ───────────────────────────────────

    public function test_payroll_creates_journal_on_mark_as_paid(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $employee = Employee::withoutEvents(function () {
            return Employee::create([
                'tenant_id' => $this->tenant->id,
                'employee_id' => 'EMP-001',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'hire_date' => now()->subYear(),
            ]);
        });

        $payroll = Payroll::withoutEvents(function () use ($employee) {
            return Payroll::create([
                'tenant_id' => $this->tenant->id,
                'employee_id' => $employee->id,
                'payroll_number' => 'PAY-000001',
                'pay_period_start' => now()->startOfMonth(),
                'pay_period_end' => now()->endOfMonth(),
                'pay_date' => now(),
                'basic_salary' => 5000.00,
                'allowances' => 500.00,
                'overtime_hours' => 0,
                'overtime_amount' => 0,
                'gross_salary' => 5500.00,
                'tax_deduction' => 550.00,
                'other_deductions' => 100.00,
                'total_deductions' => 650.00,
                'net_salary' => 4850.00,
                'status' => Payroll::STATUS_APPROVED,
                'payment_method' => 'bank_transfer',
                'created_by' => $this->user->id,
            ]);
        });

        $result = $payroll->markAsPaid();

        $this->assertTrue($result);
        $this->assertEquals(Payroll::STATUS_PAID, $payroll->fresh()->status);

        $journal = Journal::where('reference_type', Payroll::class)
            ->where('reference_id', $payroll->id)
            ->orderBy('id')->first();

        $this->assertNotNull($journal);
        $this->assertEquals('posted', $journal->status);

        // Verify entries: DR Salaries 5000, DR Allowances 500, CR Cash 4850, CR Payroll Liabilities 550 + 100
        $salaryEntry = $journal->entries()->whereHas('account', fn ($q) => $q->where('account_code', '6000'))->first();
        $this->assertEquals(5000.00, (float) $salaryEntry->debit);

        $allowanceEntry = $journal->entries()->whereHas('account', fn ($q) => $q->where('account_code', '6030'))->first();
        $this->assertEquals(500.00, (float) $allowanceEntry->debit);

        // Net pay is owed on approval (Accrued Salaries) and paid by a
        // separate payment journal (A10).
        $owedEntry = $journal->entries()->whereHas('account', fn ($q) => $q->where('account_code', '2210'))->first();
        $this->assertEquals(4850.00, (float) $owedEntry->credit);
        $payment = Journal::where('reference_type', Payroll::class)->where('reference_id', $payroll->id)
            ->where('journal_type', JournalService::PAYROLL_PAYMENT)->sole();
        $cashEntry = $payment->entries()->whereHas('account', fn ($q) => $q->where('account_code', '1100'))->first();
        $this->assertEquals(4850.00, (float) $cashEntry->credit);

        // Tax withheld goes to 2310 (Tax Payable)
        $taxPayableEntries = $journal->entries()->whereHas('account', fn ($q) => $q->where('account_code', '2310'))->get();
        $this->assertEquals(550.00, (float) $taxPayableEntries->sum('credit'));

        // Other deductions (no detail breakdown) go to 2300 (Payroll Liabilities)
        $liabilityEntries = $journal->entries()->whereHas('account', fn ($q) => $q->where('account_code', '2300'))->get();
        $this->assertEquals(100.00, (float) $liabilityEntries->sum('credit'));
    }

    public function test_payroll_mark_as_paid_requires_approved_status(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        $employee = Employee::withoutEvents(function () {
            return Employee::create([
                'tenant_id' => $this->tenant->id,
                'employee_id' => 'EMP-002',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'hire_date' => now()->subYear(),
            ]);
        });

        $payroll = Payroll::withoutEvents(function () use ($employee) {
            return Payroll::create([
                'tenant_id' => $this->tenant->id,
                'employee_id' => $employee->id,
                'payroll_number' => 'PAY-000002',
                'pay_period_start' => now()->startOfMonth(),
                'pay_period_end' => now()->endOfMonth(),
                'pay_date' => now(),
                'basic_salary' => 3000.00,
                'allowances' => 0,
                'overtime_hours' => 0,
                'overtime_amount' => 0,
                'gross_salary' => 3000.00,
                'tax_deduction' => 300.00,
                'other_deductions' => 0,
                'total_deductions' => 300.00,
                'net_salary' => 2700.00,
                'status' => Payroll::STATUS_DRAFT,
                'created_by' => $this->user->id,
            ]);
        });

        $result = $payroll->markAsPaid();

        $this->assertFalse($result);
        $this->assertEquals(Payroll::STATUS_DRAFT, $payroll->fresh()->status);
        $this->assertNull(Journal::where('reference_type', Payroll::class)->where('reference_id', $payroll->id)->orderBy('id')->first());
    }

    public function test_payroll_is_paid_helper(): void
    {
        $this->createAuthenticatedUser();

        $employee = Employee::withoutEvents(function () {
            return Employee::create([
                'tenant_id' => $this->tenant->id,
                'employee_id' => 'EMP-003',
                'first_name' => 'Bob',
                'last_name' => 'Smith',
                'hire_date' => now()->subYear(),
            ]);
        });

        $payroll = Payroll::withoutEvents(function () use ($employee) {
            return Payroll::create([
                'tenant_id' => $this->tenant->id,
                'employee_id' => $employee->id,
                'payroll_number' => 'PAY-000003',
                'pay_period_start' => now()->startOfMonth(),
                'pay_period_end' => now()->endOfMonth(),
                'pay_date' => now(),
                'basic_salary' => 1000,
                'allowances' => 0,
                'overtime_hours' => 0,
                'overtime_amount' => 0,
                'gross_salary' => 1000,
                'tax_deduction' => 0,
                'other_deductions' => 0,
                'total_deductions' => 0,
                'net_salary' => 1000,
                'status' => Payroll::STATUS_DRAFT,
                'created_by' => $this->user->id,
            ]);
        });

        $this->assertFalse($payroll->isPaid());

        $payroll->update(['status' => Payroll::STATUS_PAID]);
        $this->assertTrue($payroll->fresh()->isPaid());
    }

    // ── Bill Draft Status Guard ─────────────────────────────────

    public function test_bill_draft_does_not_create_journal(): void
    {
        $this->createAuthenticatedUser();
        $this->seedDefaultAccounts($this->tenant->id);

        // Create a draft bill — journal should NOT be created
        $bill = Bill::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => Vendor::withoutEvents(fn () => Vendor::factory()->create(['tenant_id' => $this->tenant->id]))->id,
            'bill_number' => 'BIL-000001',
            'bill_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'draft',
            'subtotal' => 500.00,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 500.00,
            'amount_paid' => 0,
            'balance_due' => 500.00,
        ]);

        $journal = Journal::where('reference_type', Bill::class)
            ->where('reference_id', $bill->id)
            ->first();

        $this->assertNull($journal, 'Draft bill should not generate a journal entry');
    }
}
