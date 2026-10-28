<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Actions\Invoices\SaveInvoice;
use App\Livewire\Invoices\InvoicesTable;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Journal;
use App\Notifications\InvoiceOverdueNotification;
use App\Services\Accounting\FinancialStatements;
use App\Services\BudgetService;
use App\Services\JournalService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 13 (reporting correctness):
 * - MB-54: budget actuals counted draft and year-end closing journals, and
 *   months ignored the financial year's start;
 * - MB-55: email reminders and analytics left "sent" invoices out of the
 *   open ones;
 * - MB-21: the invoice list's money totals counted drafts;
 * - MB-22 / MB-36: the dashboard's "Expenses" and cash card labels.
 */
class Session13ReportingTest extends TestCase
{
    private const PERMISSIONS = [
        'view invoices', 'create invoices', 'view journals', 'create journals', 'post journals',
        'view dashboard', 'cash-position dashboard-widgets', 'monthly-expenses dashboard-widgets', 'view reports',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-20 09:00:00');
        $this->createAuthenticatedUser(self::PERMISSIONS);
        $this->subscription->update(['ends_at' => '2030-12-31']);
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::where('account_code', $code)->firstOrFail();
    }

    private function journal(string $date, float $amount, bool $post = true): void
    {
        $this->post(route('journals.store'), ['journal_date' => $date, 'description' => 'Stationery', 'entries' => [
            ['account_id' => $this->account('6990')->id, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $this->account('1000')->id, 'debit' => 0, 'credit' => $amount],
        ]])->assertSessionHasNoErrors();
        if ($post) {
            $this->post(route('journals.post', Journal::where('is_posted', false)->latest('id')->firstOrFail()))->assertSessionHasNoErrors();
        }
    }

    private function budget(string $year): Budget
    {
        $budget = Budget::create(['tenant_id' => $this->tenant->id, 'name' => 'Plan', 'fiscal_year' => $year, 'status' => 'active', 'created_by' => $this->user->id]);
        BudgetLine::create(['budget_id' => $budget->id, 'account_id' => $this->account('6990')->id] + array_fill_keys(array_keys(Budget::getMonthColumns()), 1000));

        return $budget;
    }

    private function invoice(float $amount, string $status = 'sent', string $due = '2026-10-31'): Invoice
    {
        return app(SaveInvoice::class)->create($this->tenant->id, [
            'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenant->id, 'email' => fake()->safeEmail()])->id,
            'invoice_date' => '2026-10-01', 'due_date' => $due, 'status' => $status,
            'items' => [['description' => 'Consulting', 'quantity' => 1, 'unit_price' => $amount, 'tax_rate' => 0]],
        ], $this->user->id);
    }

    public function test_budget_actuals_count_posted_journals_only_and_not_the_year_end_close(): void
    {
        $budget = $this->budget('2026');
        $this->journal('2026-03-10', 400);
        $this->journal('2026-03-12', 900, post: false); // a draft
        // The year-end close moves the expense out to retained earnings: not spending.
        app(JournalService::class)->postLines($budget, 'CLOSE-2026', '2026-03-31', 'Year-end close', [
            ['3200', 400.0, 0.0, 'Close'], ['6990', 0.0, 400.0, 'Close'],
        ], $this->user->id, Journal::TYPE_CLOSING);

        $march = app(BudgetService::class)->getBudgetVsActual($budget)->first()['monthly_data']['mar'];
        $this->assertEqualsWithDelta(400.0, $march['actual'], 0.001);
    }

    public function test_budget_months_follow_the_financial_year(): void
    {
        $this->tenant->update(['fiscal_year_start' => '2026-04-01']);
        $this->journal('2026-04-05', 300);
        $this->journal('2027-01-15', 700, post: false);

        $service = app(BudgetService::class);
        $budget = $this->budget('2026');
        $this->assertSame(['apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'jan', 'feb', 'mar'], array_keys($service->monthStarts($this->tenant->id, 2026)));
        $this->assertSame('2027-01-01', $service->monthStarts($this->tenant->id, 2026)['jan']->toDateString(), 'January of a year starting in April is the next calendar year');
        $this->assertSame('oct', $service->throughMonth($budget));

        $rows = $service->getBudgetVsActual($budget, 'oct')->first();
        $this->assertSame('apr', array_key_first($rows['monthly_data']));
        $this->assertArrayNotHasKey('jan', $rows['monthly_data'], 'through October: January 2027 is still to come');
        $this->assertEqualsWithDelta(300.0, $rows['actual'], 0.001);
        $this->assertEqualsWithDelta(7000.0, $rows['budgeted'], 0.001, 'April to October, 7 months');
    }

    public function test_an_overdue_sent_invoice_gets_an_email_reminder(): void
    {
        Notification::fake();
        $invoice = $this->invoice(5000, 'sent', '2026-10-05');
        $draft = $this->invoice(5000, 'draft', '2026-10-05');

        $this->assertSame(1, app(NotificationService::class)->sendOverdueReminders($this->tenant->id));
        Notification::assertSentTo($invoice->customer, InvoiceOverdueNotification::class);
        Notification::assertNotSentTo($draft->customer, InvoiceOverdueNotification::class);
        $this->assertTrue($invoice->isOpen());
        $this->assertSame([$invoice->id], Invoice::open()->pluck('id')->all());
    }

    public function test_the_invoice_list_to_collect_equals_the_receivables_ledger(): void
    {
        $this->invoice(10000);
        $this->invoice(3000, 'draft');
        $cancelled = $this->invoice(2000);
        $cancelled->update(['status' => 'cancelled']);

        $totals = Livewire::test(InvoicesTable::class)->viewData('totals');
        $ledger = app(FinancialStatements::class)->accountBalances($this->tenant->id, null, '2030-12-31')->firstWhere('account_code', '1200')->balance;

        $this->assertEqualsWithDelta(10000.0, (float) $totals->balance, 0.001);
        $this->assertEqualsWithDelta((float) $ledger, (float) $totals->balance, 0.001);
        $this->assertEqualsWithDelta(10000.0, (float) $totals->total, 0.001, 'drafts and cancelled invoices are left out');
        $this->assertSame(3, (int) $totals->n);
    }

    public function test_the_dashboard_says_costs_and_counts_the_ledger_cash_accounts(): void
    {
        $cashAccounts = ChartOfAccount::where('type', 'asset')->whereIn('sub_type', ['cash', 'bank'])->count();

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Costs')->assertSee('Cost of sales')->assertDontSee('Bills, expenses and payroll')
            ->assertSee('Cash and bank')
            ->assertSee($cashAccounts === 1 ? '1 cash or bank account' : "{$cashAccounts} cash and bank accounts");
    }
}
