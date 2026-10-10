<?php

namespace Tests\Feature\Dashboard;

use App\Livewire\Dashboard\LowerCards;
use App\Models\Bank;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Role;
use App\Models\VatReturnFiling;
use App\Models\Vendor;
use App\Services\Accounting\FinancialStatements;
use App\Services\Dashboard\DashboardPeriod;
use App\Services\Dashboard\DashboardService;
use App\Services\JournalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Dashboard upgrade: the figures agree with the reports, periods compare
 * like for like, cards follow their permissions, and the roles screen
 * lists one tick per card.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, ChartOfAccount> */
    private array $acct = [];

    private const ALL_CARDS = [
        'view dashboard', 'view reports', 'view invoices', 'view bills', 'view inventory', 'view customers', 'send invoices',
        'attention-list dashboard-widgets', 'cash-position dashboard-widgets', 'total-revenue dashboard-widgets',
        'monthly-expenses dashboard-widgets', 'profit dashboard-widgets', 'revenue-chart dashboard-widgets',
        'outstanding-receivables dashboard-widgets', 'pending-bills dashboard-widgets', 'top-customers dashboard-widgets',
        'expense-breakdown dashboard-widgets', 'recent-invoices dashboard-widgets', 'low-stock dashboard-widgets',
        'quick-actions dashboard-widgets', 'create invoices',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-10 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function chart(): void
    {
        $rows = [
            ['1000', 'Cash', 'asset', 'cash'],
            ['1100', 'Bank', 'asset', 'bank'],
            ['1200', 'Accounts Receivable', 'asset', 'accounts_receivable'],
            ['2000', 'Accounts Payable', 'liability', 'accounts_payable'],
            ['3000', "Owner's Capital", 'equity', 'equity'],
            ['4000', 'Sales', 'income', 'income'],
            ['5000', 'Cost of Goods Sold', 'expense', 'cost_of_goods_sold'],
            ['6100', 'Rent', 'expense', 'expense'],
            ['6200', 'Transport', 'expense', 'expense'],
        ];
        foreach ($rows as [$code, $name, $type, $sub]) {
            $this->acct[$code] = ChartOfAccount::updateOrCreate(
                ['tenant_id' => $this->tenant->id, 'account_code' => $code],
                ['name' => $name, 'type' => $type, 'sub_type' => $sub, 'is_active' => true, 'current_balance' => 0, 'opening_balance' => 0],
            );
        }
    }

    /** @param array<int, array{0: string, 1: float, 2: float}> $lines code, debit, credit */
    private function postJournal(string $date, array $lines): void
    {
        $service = app(JournalService::class);
        $journal = Journal::create([
            'tenant_id' => $this->tenant->id,
            'journal_number' => Journal::generateNumber($this->tenant->id),
            'journal_date' => $date,
            'description' => 'Test',
            'status' => 'posted',
            'is_posted' => true,
            'posted_at' => now(),
        ]);
        foreach ($lines as [$code, $debit, $credit]) {
            $service->createEntry($journal, $code, $debit, $credit, 'Test');
        }
        $journal->updateTotals();
        $journal->save();
    }

    private function books(): void
    {
        $this->chart();
        $this->postJournal('2026-01-02', [['1100', 500000, 0], ['3000', 0, 500000]]);
        $this->postJournal('2026-08-15', [['1200', 120000, 0], ['4000', 0, 120000]]);
        $this->postJournal('2026-09-05', [['1100', 80000, 0], ['4000', 0, 80000]]);
        $this->postJournal('2026-09-07', [['6100', 30000, 0], ['1100', 0, 30000]]);
        $this->postJournal('2026-09-20', [['1100', 50000, 0], ['4000', 0, 50000]]); // after 1–10 Sep
        $this->postJournal('2026-10-03', [['1100', 90000, 0], ['4000', 0, 90000]]);
        $this->postJournal('2026-10-04', [['5000', 40000, 0], ['1000', 0, 40000]]);
        $this->postJournal('2026-10-06', [['6200', 5000, 0], ['1100', 0, 5000]]);
    }

    private function dashboard(): DashboardService
    {
        return app(DashboardService::class);
    }

    public function test_figures_match_the_reports(): void
    {
        $this->createAuthenticatedUser(self::ALL_CARDS);
        $this->books();
        $tid = $this->tenant->id;
        $statements = app(FinancialStatements::class);

        $period = $this->dashboard()->period($tid, 'this_month', 'previous');
        $summary = $this->dashboard()->summary($tid, $period);
        $pnl = $statements->profitAndLoss($tid, '2026-10-01', '2026-10-10');

        $this->assertSame($pnl['revenue'], $summary['income']);
        $this->assertSame($pnl['totalExpenses'], $summary['expenses']);
        $this->assertSame($pnl['netProfit'], $summary['profit']);
        $this->assertEquals(90000, $summary['income']);
        $this->assertEquals(45000, $summary['expenses']);

        // Like for like: 1–10 September only (the 20 Sep sale is left out).
        $this->assertEquals(80000, $summary['compare']['income']);
        $this->assertEquals(30000, $summary['compare']['expenses']);

        // Cash: the bank and cash accounts on the balance sheet.
        $sheet = $statements->accountBalances($tid, null, '2026-10-10')
            ->filter(fn ($a) => in_array($a->sub_type, ['cash', 'bank'], true))->sum('balance');
        $this->assertEquals($sheet, $summary['cash']);
        $this->assertEquals(645000, $summary['cash']);
        $this->assertEquals(600000, $summary['cashBefore']);

        // Each month of the chart equals the profit and loss for that month.
        foreach ($this->dashboard()->months($tid) as $m) {
            $start = Carbon::parse($m['ym'].'-01');
            $end = $m['partial'] ? Carbon::parse('2026-10-10') : $start->copy()->endOfMonth();
            $month = $statements->profitAndLoss($tid, $start->toDateString(), $end->toDateString());
            $this->assertEquals($month['revenue'], $m['income'], $m['ym']);
            $this->assertEquals($month['totalExpenses'], $m['expenses'], $m['ym']);
        }
        $months = collect($this->dashboard()->months($tid))->keyBy('ym');
        $this->assertCount(12, $months);
        $this->assertEquals(600000, $months['2026-09']['cash']);
        $this->assertTrue($months['2026-10']['partial']);
    }

    public function test_periods_compare_like_for_like(): void
    {
        $jan1 = Carbon::parse('2026-01-01');
        $p = DashboardPeriod::make('this_month', 'previous', Carbon::parse('2026-10-10'), $jan1);
        $this->assertSame(['2026-10-01', '2026-10-10', '2026-09-01', '2026-09-10'], [$p->from->toDateString(), $p->to->toDateString(), $p->compareFrom->toDateString(), $p->compareTo->toDateString()]);
        $this->assertSame('This month (1–10 Oct)', $p->label());

        // The 31st compares with the whole of a 30-day month.
        $p = DashboardPeriod::make('this_month', 'previous', Carbon::parse('2026-10-31'), $jan1);
        $this->assertSame('2026-09-30', $p->compareTo->toDateString());

        $p = DashboardPeriod::make('last_month', 'previous', Carbon::parse('2026-10-10'), $jan1);
        $this->assertSame(['2026-09-01', '2026-09-30', '2026-08-01', '2026-08-31'], [$p->from->toDateString(), $p->to->toDateString(), $p->compareFrom->toDateString(), $p->compareTo->toDateString()]);

        // Financial year starting in April.
        $p = DashboardPeriod::make('this_year', 'last_year', Carbon::parse('2026-10-10'), Carbon::parse('2026-04-01'));
        $this->assertSame(['2026-04-01', '2026-10-10', '2025-04-01', '2025-10-10'], [$p->from->toDateString(), $p->to->toDateString(), $p->compareFrom->toDateString(), $p->compareTo->toDateString()]);

        $p = DashboardPeriod::make('nonsense', 'none', Carbon::parse('2026-10-10'), $jan1);
        $this->assertSame('this_month', $p->key);
        $this->assertNull($p->compareFrom);
    }

    public function test_who_owes_whom_matches_the_ageing_reports(): void
    {
        $this->createAuthenticatedUser(self::ALL_CARDS);
        $tid = $this->tenant->id;
        $customer = Customer::factory()->create(['tenant_id' => $tid, 'name' => 'Bala Stores']);
        $vendor = Vendor::factory()->create(['tenant_id' => $tid, 'name' => 'Dangote Flour Mills']);
        Invoice::withoutEvents(function () use ($tid, $customer) {
            foreach ([['2026-10-20', 'sent', 100], ['2026-10-01', 'unpaid', 200], ['2026-08-25', 'partial', 300], ['2026-06-01', 'overdue', 400], ['2026-10-01', 'paid', 0], ['2026-09-01', 'draft', 999]] as $n => [$due, $status, $balance]) {
                Invoice::factory()->create(['tenant_id' => $tid, 'customer_id' => $customer->id, 'invoice_number' => "INV-T{$n}", 'invoice_date' => '2026-05-01', 'due_date' => $due, 'status' => $status, 'total' => 1000, 'balance_due' => $balance]);
            }
        });
        Bill::withoutEvents(function () use ($tid, $vendor) {
            foreach ([['2026-10-05', 'unpaid', 50], ['2026-10-14', 'unpaid', 70], ['2026-11-30', 'partial', 90]] as $n => [$due, $status, $balance]) {
                Bill::factory()->create(['tenant_id' => $tid, 'vendor_id' => $vendor->id, 'bill_number' => "BILL-T{$n}", 'bill_date' => '2026-09-01', 'due_date' => $due, 'status' => $status, 'total' => 500, 'balance_due' => $balance]);
            }
        });

        $ar = $this->dashboard()->receivables($tid);
        $this->assertEquals(1000, $ar['total']);
        $this->assertEquals([100, 200, 300, 0, 400], array_column($ar['buckets'], 'amount'));
        $this->assertEquals(900, $ar['overdue']);
        $this->assertSame('Bala Stores', $ar['late'][0]['name']);
        $this->assertSame(131, $ar['late'][0]['days']);
        $report = $this->get(route('reports.accounts-receivable'))->assertOk();
        $this->assertEquals($report->viewData('totalReceivable'), $ar['total']);

        $ap = $this->dashboard()->payables($tid);
        $this->assertEquals(210, $ap['total']);
        $this->assertEquals(50, $ap['overdue']);
        $this->assertEquals(70, $ap['week']);
        $this->assertSame('Dangote Flour Mills', $ap['next'][0]['name']);
        $this->assertSame(5, $ap['next'][0]['daysLate']);
    }

    public function test_figures_refresh_as_soon_as_something_is_posted(): void
    {
        $this->createAuthenticatedUser(self::ALL_CARDS);
        $this->books();
        $period = $this->dashboard()->period($this->tenant->id, 'this_month', 'previous');
        $this->assertEquals(90000, $this->dashboard()->summary($this->tenant->id, $period)['income']);

        $this->postJournal('2026-10-09', [['1100', 10000, 0], ['4000', 0, 10000]]);

        $this->assertEquals(100000, $this->dashboard()->summary($this->tenant->id, $period)['income']);
    }

    public function test_page_shows_the_cards_and_the_period_choice(): void
    {
        $this->createAuthenticatedUser(self::ALL_CARDS);
        $this->books();

        $html = $this->get(route('dashboard', ['period' => 'last_month', 'compare' => 'last_year']))->assertOk()->getContent();
        foreach (['cash-position', 'total-revenue', 'monthly-expenses', 'profit'] as $tile) {
            $this->assertStringContainsString('data-tile="'.$tile.'"', $html);
        }
        $this->assertStringContainsString('Last month (1–30 Sep)', $html);
        $this->assertStringContainsString('Income and expenses', $html);
        $this->assertStringContainsString('Needs your attention', $html);
        $this->assertStringContainsString('dash-new-menu', $html);
        $this->assertStringNotContainsString('Employees', $html);

        // The choice is remembered for the rest of the visit.
        $this->assertStringContainsString('Last month (1–30 Sep)', $this->get(route('dashboard'))->getContent());

        Livewire::test(LowerCards::class)
            ->assertSee('Cash flow')->assertSee('Customers owe you')->assertSee('You owe')
            ->assertSee('Top customers')->assertSee('Where the money goes')->assertSee('Recent activity');
    }

    public function test_cards_follow_their_permissions(): void
    {
        $this->createAuthenticatedUser(['view dashboard', 'total-revenue dashboard-widgets', 'outstanding-receivables dashboard-widgets']);
        $this->books();

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('data-tile="total-revenue"', $html);
        $this->assertStringNotContainsString('data-tile="cash-position"', $html);
        $this->assertStringNotContainsString('data-tile="profit"', $html);
        $this->assertStringNotContainsString('Needs your attention', $html);
        $this->assertStringNotContainsString('dash-new-menu', $html);

        Livewire::test(LowerCards::class)
            ->assertSee('Customers owe you')
            ->assertDontSee('Cash flow')->assertDontSee('You owe')->assertDontSee('Recent activity');
    }

    public function test_attention_list_shows_only_what_is_true_and_allowed(): void
    {
        $user = $this->createAuthenticatedUser(self::ALL_CARDS);
        $tid = $this->tenant->id;
        $customer = Customer::factory()->create(['tenant_id' => $tid]);
        Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'tenant_id' => $tid, 'customer_id' => $customer->id, 'invoice_date' => '2026-09-05', 'due_date' => '2026-10-01',
            'status' => 'unpaid', 'subtotal' => 1000, 'tax_amount' => 75, 'total' => 1075, 'balance_due' => 1075,
        ]));

        $titles = array_column($this->dashboard()->attention($user), 'title');
        $this->assertContains('1 invoice overdue', $titles);
        $this->assertContains('VAT return for September due 21 Oct', $titles);

        // Filing September's return takes it off the list.
        VatReturnFiling::create(['tenant_id' => $tid, 'month' => '2026-09', 'lines' => [], 'filed_at' => now()]);
        $this->assertNotContains('VAT return for September due 21 Oct', array_column($this->dashboard()->attention($user), 'title'));

        // Someone who can't open invoices doesn't see the invoice line.
        $user->revokePermissionTo('view invoices');
        $user->forgetCachedPermissions();
        $this->assertNotContains('1 invoice overdue', array_column($this->dashboard()->attention($user->fresh()), 'title'));
    }

    public function test_getting_started_for_a_new_business_and_it_can_be_hidden(): void
    {
        $this->createAuthenticatedUser(array_merge(self::ALL_CARDS, ['view settings', 'create banks', 'create customers', 'create expenses', 'create users']));

        $this->get(route('dashboard'))->assertOk()->assertSee('Getting started')->assertSee('Add your bank account');
        Bank::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->get(route('dashboard'))->assertSee('Add your bank account</span><span class="sr-only"> (done)', false);

        $this->post(route('dashboard.getting-started.hide'))->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Getting started');
    }

    public function test_roles_screen_has_one_tick_per_dashboard_card(): void
    {
        $this->createAuthenticatedUser(['view roles', 'create roles']);
        foreach (array_keys(config('dashboard.widgets')) as $key) {
            Permission::findOrCreate("{$key} dashboard-widgets", 'web');
        }

        $html = $this->get(route('settings.roles.create'))->assertOk()->getContent();
        $this->assertStringContainsString('Dashboard cards', $html);
        foreach (config('dashboard.widgets') as $key => $widget) {
            $this->assertStringContainsString('value="'.$key.' dashboard-widgets"', $html);
            $this->assertStringContainsString(e($widget['label']), $html);
        }
        // Not also squeezed into the table as "Other" ticks.
        $this->assertSame(1, substr_count($html, 'value="profit dashboard-widgets"'));
    }

    public function test_existing_roles_get_the_new_cards_without_losing_any(): void
    {
        $this->createTenantWithSubscription();
        foreach (['view dashboard', 'view banks', 'total-revenue dashboard-widgets', 'monthly-expenses dashboard-widgets', 'revenue-chart dashboard-widgets', 'employees-count dashboard-widgets'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $accountant = Role::create(['name' => 'accountant', 'guard_name' => 'web', 'tenant_id' => null]);
        $accountant->givePermissionTo(['view dashboard', 'total-revenue dashboard-widgets', 'monthly-expenses dashboard-widgets', 'revenue-chart dashboard-widgets']);
        $sales = Role::create(['name' => 'sales', 'guard_name' => 'web', 'tenant_id' => null]);
        $sales->givePermissionTo(['view dashboard', 'total-revenue dashboard-widgets']);
        $hr = Role::create(['name' => 'hr-manager', 'guard_name' => 'web', 'tenant_id' => null]);
        $hr->givePermissionTo(['view dashboard', 'employees-count dashboard-widgets']);

        (require database_path('migrations/2026_10_20_100001_update_dashboard_widget_permissions.php'))->up();

        $names = fn (Role $r) => $r->fresh()->permissions->pluck('name')->all();
        $this->assertContains('cash-position dashboard-widgets', $names($accountant));
        $this->assertContains('profit dashboard-widgets', $names($accountant));
        $this->assertContains('expense-breakdown dashboard-widgets', $names($accountant));
        $this->assertContains('top-customers dashboard-widgets', $names($sales));
        $this->assertContains('attention-list dashboard-widgets', $names($sales));
        $this->assertNotContains('profit dashboard-widgets', $names($sales));
        $this->assertNotContains('cash-position dashboard-widgets', $names($sales));
        $this->assertContains('attention-list dashboard-widgets', $names($hr));
        $this->assertFalse(Permission::where('name', 'employees-count dashboard-widgets')->exists());
    }

    public function test_the_employees_count_moved_to_the_employees_page(): void
    {
        $this->createAuthenticatedUser(['view employees']);
        foreach (['Hauwa', 'Sani'] as $i => $name) {
            Employee::withoutEvents(fn () => Employee::create([
                'tenant_id' => $this->tenant->id, 'employee_id' => "EMP-00{$i}", 'first_name' => $name, 'last_name' => 'Musa',
                'email' => "{$name}@example.com", 'hire_date' => now()->subYear(), 'status' => 'active',
            ]));
        }

        $this->get(route('employees.index'))->assertOk()->assertSee('2 active employees');
    }
}
