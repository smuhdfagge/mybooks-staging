<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Actions\Invoices\SaveInvoice;
use App\Jobs\ProcessPayrollBatch;
use App\Livewire\Banks\BanksTable;
use App\Models\Bank;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Journal;
use App\Models\Payroll;
use App\Models\PayrollBatch;
use App\Models\StatutoryRemittance;
use App\Models\User;
use App\Services\Accounting\FinancialStatements;
use App\Services\BankReconciliationService;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 9 (MB-08, part 1): the reconcile screen's
 * book balance was banks.current_balance, a counter only payments and
 * expenses moved, so a bank-charges journal left it at 4,000 while the
 * ledger said 3,850. Net pay, payroll remittances and loans always left
 * the payment method's default account, whichever bank paid them.
 */
class Session9BankLedgerTest extends TestCase
{
    private const PERMISSIONS = [
        'view banks', 'reconcile banks', 'view invoices', 'create invoices', 'view payments-received', 'create payments-received',
        'view journals', 'create journals', 'post journals', 'view payroll', 'edit payroll', 'approve payroll',
    ];

    private ChartOfAccount $gtbLedger;

    private Bank $gtb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-10 09:00:00');
        $this->createAuthenticatedUser(self::PERMISSIONS);
        $this->subscription->update(['ends_at' => '2030-12-31']);

        $this->gtbLedger = ChartOfAccount::factory()->asset()->create(['tenant_id' => $this->tenant->id, 'account_code' => '1120', 'name' => 'GTBank current', 'sub_type' => 'bank']);
        $this->gtb = Bank::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'GTBank', 'chart_of_account_id' => $this->gtbLedger->id, 'opening_balance' => 0, 'current_balance' => 0]);
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::where('account_code', $code)->firstOrFail();
    }

    private function ledger(string $code): float
    {
        return (float) (app(FinancialStatements::class)->accountBalances($this->tenant->id, null, '2030-12-31')->firstWhere('account_code', $code)?->balance ?? 0);
    }

    private function postJournal(string $date, array $entries): void
    {
        $this->post(route('journals.store'), ['journal_date' => $date, 'description' => 'Manual', 'entries' => $entries])->assertSessionHasNoErrors();
        $this->post(route('journals.post', Journal::where('is_posted', false)->latest('id')->firstOrFail()))->assertSessionHasNoErrors();
    }

    private function approvedPayroll(array $attrs = []): Payroll
    {
        $employee = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => Employee::generateEmployeeId($this->tenant->id),
            'first_name' => 'Hauwa', 'last_name' => 'Sani', 'hire_date' => '2026-01-01',
        ]);
        $payroll = Payroll::withoutEvents(fn () => Payroll::create($attrs + [
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id, 'payroll_number' => 'PAY-S9'.random_int(1000, 9999),
            'pay_period_start' => '2026-09-01', 'pay_period_end' => '2026-09-30', 'pay_date' => '2026-09-30',
            'basic_salary' => 5000, 'allowances' => 0, 'overtime_hours' => 0, 'overtime_amount' => 0, 'gross_salary' => 5000,
            'tax_deduction' => 500, 'other_deductions' => 0, 'total_deductions' => 500, 'net_salary' => 4500,
            'status' => 'draft', 'payment_method' => 'bank_transfer', 'created_by' => User::factory()->create(['tenant_id' => $this->tenant->id])->id,
        ]));
        $this->post(route('payroll.approve', $payroll))->assertSessionHas('success');

        return $payroll->fresh();
    }

    public function test_the_book_balance_is_the_ledger_balance(): void
    {
        $invoice = app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenant->id])->id, 'invoice_date' => '2026-10-01', 'due_date' => '2026-10-31', 'status' => 'sent',
            'items' => [['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 4000, 'tax_rate' => 0]],
        ], $this->user->id);
        $this->post(route('payments-received.store'), [
            'customer_id' => $invoice->customer_id, 'invoice_id' => $invoice->id, 'payment_date' => '2026-10-05',
            'amount' => 4000, 'payment_method' => 'bank_transfer', 'bank_id' => $this->gtb->id,
        ])->assertSessionHasNoErrors();

        // Bank charges by journal: the counter never saw these.
        $this->postJournal('2026-10-06', [
            ['account_id' => $this->account('6990')->id, 'debit' => 150, 'credit' => 0],
            ['account_id' => $this->gtbLedger->id, 'debit' => 0, 'credit' => 150],
        ]);

        $this->assertSame(3850.0, $this->ledger('1120'));
        $summary = app(BankReconciliationService::class)->getSummary($this->gtb->fresh());
        $this->assertSame(3850.0, $summary['book_balance']);
        $this->assertSame(3850.0, $summary['difference'], 'nothing ticked yet: the whole balance is unreconciled');

        $this->get(route('banks.reconcile', $this->gtb))->assertOk()->assertSee('3,850.00');
        $this->get(route('banks.show', $this->gtb))->assertOk()->assertSee('3,850.00');
        Livewire::test(BanksTable::class)->assertSee('3,850.00');
    }

    public function test_the_book_balance_adds_the_opening_balance_and_counts_only_posted_journals(): void
    {
        $this->gtb->update(['opening_balance' => 1000]);
        $this->post(route('journals.store'), ['journal_date' => '2026-10-06', 'description' => 'Draft', 'entries' => [
            ['account_id' => $this->gtbLedger->id, 'debit' => 500, 'credit' => 0],
            ['account_id' => $this->account('4000')->id, 'debit' => 0, 'credit' => 500],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(1000.0, $this->gtb->fresh()->ledgerBalance(), 'a draft journal is not in the books');
        $this->post(route('journals.post', Journal::where('is_posted', false)->sole()))->assertSessionHasNoErrors();
        $this->assertSame(1500.0, $this->gtb->fresh()->ledgerBalance());
        $this->assertSame(1000.0, $this->gtb->fresh()->ledgerBalance('2026-10-05'), 'as of a date before the journal');
    }

    public function test_a_bank_without_a_ledger_account_keeps_its_counter(): void
    {
        $cashBox = Bank::factory()->create(['tenant_id' => $this->tenant->id, 'chart_of_account_id' => null, 'current_balance' => 750]);

        $this->assertSame(750.0, $cashBox->ledgerBalance());
        $this->assertSame(750.0, app(BankReconciliationService::class)->getSummary($cashBox)['book_balance']);
    }

    public function test_net_pay_leaves_the_bank_chosen(): void
    {
        $payroll = $this->approvedPayroll();
        $this->get(route('payroll.show', $payroll))->assertOk()->assertSee('Paid from GTBank');

        $this->post(route('payroll.mark-paid', $payroll), ['bank_id' => $this->gtb->id])->assertSessionHas('success');

        $this->assertSame($this->gtb->id, $payroll->fresh()->bank_id);
        $this->assertSame(-4500.0, $this->ledger('1120'));
        $this->assertSame(0.0, $this->ledger('1100'), 'not the default bank account');
        $this->assertSame(-4500.0, $this->gtb->fresh()->ledgerBalance());
    }

    public function test_without_a_bank_net_pay_still_uses_the_default_account(): void
    {
        $payroll = $this->approvedPayroll();
        $this->post(route('payroll.mark-paid', $payroll))->assertSessionHas('success');

        $this->assertSame(-4500.0, $this->ledger('1100'));
        $this->assertSame(0.0, $this->ledger('1120'));
    }

    public function test_another_tenants_bank_is_refused(): void
    {
        $payroll = $this->approvedPayroll();
        auth()->forgetGuards(); // seeding a tenant while signed in writes into this one
        [$other] = $this->createTenantWithSubscription();
        $theirs = Bank::factory()->create(['tenant_id' => $other->id, 'chart_of_account_id' => null]);
        $this->actingAs($this->user);

        $this->post(route('payroll.mark-paid', $payroll), ['bank_id' => $theirs->id])->assertSessionHasErrors('bank_id');
        $this->assertSame('approved', $payroll->fresh()->status);
    }

    public function test_a_payroll_run_leaves_the_bank_chosen_in_the_foreground_and_the_background(): void
    {
        $batch = PayrollBatch::create([
            'tenant_id' => $this->tenant->id, 'batch_number' => 'PBN-S9', 'pay_period_start' => '2026-09-01', 'pay_period_end' => '2026-09-30',
            'status' => PayrollBatch::STATUS_APPROVED, 'created_by' => $this->user->id,
        ]);
        $payroll = $this->approvedPayroll(['payroll_batch_id' => $batch->id]);

        $this->get(route('payroll-batches.show', $batch))->assertOk()->assertSee('Paid from GTBank');
        $this->post(route('payroll-batches.mark-paid', $batch), ['bank_id' => $this->gtb->id])->assertSessionHas('success');

        $this->assertSame($this->gtb->id, $batch->fresh()->bank_id);
        $this->assertSame($this->gtb->id, $payroll->fresh()->bank_id);
        $this->assertSame(-4500.0, $this->ledger('1120'));

        // A large run is paid by the job, from the bank saved on the run.
        $second = PayrollBatch::create([
            'tenant_id' => $this->tenant->id, 'batch_number' => 'PBN-S9B', 'pay_period_start' => '2026-09-01', 'pay_period_end' => '2026-09-30',
            'status' => PayrollBatch::STATUS_PROCESSING, 'created_by' => $this->user->id, 'bank_id' => $this->gtb->id,
        ]);
        $this->approvedPayroll(['payroll_batch_id' => $second->id]);
        (new ProcessPayrollBatch($second))->handle();

        $this->assertSame(PayrollBatch::STATUS_PAID, $second->fresh()->status);
        $this->assertSame(-9000.0, $this->ledger('1120'));
    }

    public function test_a_payroll_remittance_leaves_the_bank_chosen(): void
    {
        $this->approvedPayroll();
        $this->get(route('payroll.liabilities'))->assertOk()->assertSee('From bank account');

        $this->post(route('payroll.liabilities.remit'), [
            'account_code' => '2310', 'amount' => 500, 'date' => '2026-10-09', 'payment_method' => 'bank_transfer', 'reference' => 'PAYE Sep', 'bank_id' => $this->gtb->id,
        ])->assertSessionHas('success');

        $this->assertSame(-500.0, $this->ledger('1120'));
        $this->assertSame(0.0, $this->ledger('1100'));
        $this->assertSame($this->gtb->id, StatutoryRemittance::sole()->bank_id);
    }

    public function test_a_loan_is_paid_out_of_its_bank(): void
    {
        $employee = Employee::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => Employee::generateEmployeeId($this->tenant->id),
            'first_name' => 'Musa', 'last_name' => 'Bello', 'hire_date' => '2026-01-01',
        ]);
        EmployeeLoan::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id, 'loan_number' => 'LN-S9', 'type' => 'advance',
            'principal_amount' => 100000, 'interest_rate' => 0, 'total_installments' => 10, 'installment_amount' => 10000,
            'installments_paid' => 0, 'amount_repaid' => 0, 'outstanding_balance' => 100000,
            'disbursement_date' => '2026-10-01', 'first_deduction_date' => '2026-10-31', 'status' => 'active', 'bank_id' => $this->gtb->id,
        ]);

        $this->assertSame(-100000.0, $this->ledger('1120'));
        $this->assertSame(0.0, $this->ledger('1100'));
        $this->assertSame(100000.0, $this->ledger('1250'));
    }
}
