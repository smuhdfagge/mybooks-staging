<?php

namespace App\Services\Dashboard;

use App\Models\Bank;
use App\Models\BankFeedLine;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Journal;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VatReturnFiling;
use App\Services\Accounting\FinancialStatements;
use App\Support\Money;
use App\Support\SqlDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every figure on the dashboard (dashboard upgrade).
 *
 * Money figures come from the ledger through FinancialStatements, the same
 * code as the profit and loss, balance sheet and cash flow reports, so the
 * dashboard and the reports always agree. Who owes whom comes from the same
 * invoice and bill rules as the ageing reports. Results are plain arrays,
 * saved per business in DashboardCache.
 */
class DashboardService
{
    /** Invoices that still have money to come in (as the receivables report). */
    public const OPEN_INVOICE = ['sent', 'unpaid', 'partial', 'overdue'];

    /** Bills still to pay (as the payables report). */
    public const OPEN_BILL = ['unpaid', 'partial', 'overdue'];

    /** Invoices that count as sales. */
    private const NOT_SALES = ['draft', 'cancelled', 'void'];

    public function __construct(private FinancialStatements $statements) {}

    public function period(int $tenantId, ?string $key, ?string $compare, ?Carbon $today = null): DashboardPeriod
    {
        $today ??= now();

        return DashboardPeriod::make($key, $compare, $today, $this->statements->financialYearStart($tenantId, $today->toDateString()));
    }

    // ── The four main cards ─────────────────────────────────────

    /**
     * Cash, income, expenses and profit for the period and the compare period.
     *
     * @return array<string, mixed>
     */
    public function summary(int $tenantId, DashboardPeriod $period): array
    {
        return DashboardCache::remember($tenantId, 'summary:'.$period->cacheKey(), function () use ($tenantId, $period) {
            $pnl = $this->statements->profitAndLoss($tenantId, $period->from->toDateString(), $period->to->toDateString());
            $before = $period->compareFrom
                ? $this->statements->profitAndLoss($tenantId, $period->compareFrom->toDateString(), $period->compareTo->toDateString())
                : null;

            return [
                'cash' => $this->cashAt($tenantId, $period->to),
                'cashBefore' => $this->cashAt($tenantId, $period->from->copy()->subDay()),
                'cashSince' => $period->from->copy()->subDay()->toDateString(),
                'cashAccounts' => Bank::where('tenant_id', $tenantId)->count(),
                'income' => $pnl['revenue'],
                'expenses' => $pnl['totalExpenses'],
                'profit' => $pnl['netProfit'],
                'margin' => $pnl['revenue'] > 0 ? round($pnl['netProfit'] / $pnl['revenue'] * 100, 1) : null,
                'compare' => $before ? [
                    'income' => $before['revenue'],
                    'expenses' => $before['totalExpenses'],
                    'profit' => $before['netProfit'],
                ] : null,
            ];
        });
    }

    /** Balance of all bank and cash accounts at the end of $day, from the ledger. */
    public function cashAt(int $tenantId, Carbon $day): float
    {
        // Same rule as FinancialStatements::accountBalances() (posted, not
        // deleted, opening balances added), for the cash accounts only.
        $ids = $this->cashAccountIds($tenantId);
        if ($ids === []) {
            return 0.0;
        }
        $moves = (float) DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->where('j.tenant_id', $tenantId)
            ->where('j.is_posted', true)
            ->whereNull('j.deleted_at')
            ->where('j.journal_date', '<', $day->copy()->addDay()->toDateString())
            ->whereIn('je.account_id', $ids)
            ->sum(DB::raw('je.debit - je.credit'));
        $opening = (float) ChartOfAccount::withTrashed()->whereIn('id', $ids)->sum('opening_balance');

        return round($moves + $opening, 2);
    }

    // ── 12 months ───────────────────────────────────────────────

    /**
     * Income, expenses, profit and month-end cash for the 12 months up to
     * and including the current one: two grouped ledger queries.
     *
     * @return list<array{ym: string, label: string, long: string, income: float, expenses: float, profit: float, cash: float, partial: bool}>
     */
    public function months(int $tenantId, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();

        return DashboardCache::remember($tenantId, 'months:'.$today->toDateString(), function () use ($tenantId, $today) {
            $start = $today->copy()->subMonthsNoOverflow(11)->startOfMonth();
            $end = $today->copy()->addDay()->toDateString();
            $ym = SqlDate::month('j.journal_date');

            $base = fn () => DB::table('journal_entries as je')
                ->join('journals as j', 'j.id', '=', 'je.journal_id')
                ->join('chart_of_accounts as a', 'a.id', '=', 'je.account_id')
                ->where('j.tenant_id', $tenantId)
                ->where('j.is_posted', true)
                ->whereNull('j.deleted_at')
                ->where('j.journal_date', '>=', $start->toDateString())
                ->where('j.journal_date', '<', $end);

            // Profit and loss lines, without year-end closing journals (as the report).
            $pl = $base()
                ->whereIn('a.type', [ChartOfAccount::TYPE_INCOME, ChartOfAccount::TYPE_EXPENSE])
                ->where(fn ($w) => $w->whereNull('j.journal_type')->orWhere('j.journal_type', '!=', Journal::TYPE_CLOSING))
                ->groupBy('ym', 'a.type')
                ->selectRaw("{$ym} as ym, a.type as type, SUM(je.debit) as debit, SUM(je.credit) as credit")
                ->get();

            $cashIds = $this->cashAccountIds($tenantId);
            $cashMoves = $base()
                ->whereIn('je.account_id', $cashIds ?: [0])
                ->groupBy('ym')
                ->selectRaw("{$ym} as ym, SUM(je.debit) - SUM(je.credit) as net")
                ->pluck('net', 'ym');

            $running = $this->cashAt($tenantId, $start->copy()->subDay());
            $out = [];
            for ($m = $start->copy(); $m->lte($today); $m->addMonthNoOverflow()) {
                $key = $m->format('Y-m');
                $rows = $pl->where('ym', $key);
                $income = round((float) $rows->where('type', ChartOfAccount::TYPE_INCOME)->sum(fn ($r) => $r->credit - $r->debit), 2);
                $expenses = round((float) $rows->where('type', ChartOfAccount::TYPE_EXPENSE)->sum(fn ($r) => $r->debit - $r->credit), 2);
                $running = round($running + (float) ($cashMoves[$key] ?? 0), 2);
                $out[] = [
                    'ym' => $key,
                    'label' => $m->format('M'),
                    'long' => $m->format('F Y'),
                    'income' => $income,
                    'expenses' => $expenses,
                    'profit' => round($income - $expenses, 2),
                    'cash' => $running,
                    'partial' => $m->isSameMonth($today),
                ];
            }

            return $out;
        });
    }

    // ── Cash flow ───────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function cashFlow(int $tenantId, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();

        return DashboardCache::remember($tenantId, 'cashflow:'.$today->toDateString(), function () use ($tenantId, $today) {
            $from = $today->copy()->subDays(29);
            $cf = $this->statements->cashFlow($tenantId, $from->toDateString(), $today->toDateString());
            $lines = [
                ['label' => 'From customers', 'amount' => $cf['paymentsReceived']],
                ['label' => 'To suppliers', 'amount' => -$cf['paymentsMade']],
                ['label' => 'Salaries and PAYE', 'amount' => -$cf['payrollPaid']],
                ['label' => 'Other running costs', 'amount' => -$cf['expensesPaid']],
                ['label' => 'Assets, loans and owner', 'amount' => round($cf['netInvestingCashFlow'] + $cf['netFinancingCashFlow'], 2)],
            ];

            $horizon = $today->copy()->addDays(30);
            $dueIn = (float) Invoice::where('tenant_id', $tenantId)
                ->whereIn('status', self::OPEN_INVOICE)->where('balance_due', '>', 0)
                ->where('due_date', '<', $horizon->copy()->addDay()->toDateString())
                ->sum('balance_due');
            $dueOut = (float) Bill::where('tenant_id', $tenantId)
                ->whereIn('status', self::OPEN_BILL)->where('balance_due', '>', 0)
                ->where('due_date', '<', $horizon->copy()->addDay()->toDateString())
                ->sum('balance_due');

            return [
                'from' => $from->toDateString(),
                'to' => $today->toDateString(),
                'net' => $cf['netCashFlow'],
                // The last line only when assets, loans or the owner moved money.
                'lines' => abs($lines[4]['amount']) >= 0.005 ? $lines : array_slice($lines, 0, 4),
                'horizon' => $horizon->toDateString(),
                'dueIn' => round($dueIn, 2),
                'dueOut' => round($dueOut, 2),
            ];
        });
    }

    // ── Who owes whom ───────────────────────────────────────────

    /** @return array<string, mixed> */
    public function receivables(int $tenantId, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();

        return DashboardCache::remember($tenantId, 'receivables:'.$today->toDateString(), function () use ($tenantId, $today) {
            $d = fn (int $days) => $today->copy()->subDays($days)->toDateString();
            $t = $today->toDateString();
            $open = fn () => Invoice::where('tenant_id', $tenantId)
                ->whereIn('status', self::OPEN_INVOICE)
                ->where('balance_due', '>', 0)
                ->where('invoice_date', '<', $today->copy()->addDay()->toDateString());

            $row = $open()->toBase()->selectRaw(
                'SUM(CASE WHEN due_date >= ? THEN balance_due ELSE 0 END) as b0,
                 SUM(CASE WHEN due_date < ? AND due_date >= ? THEN balance_due ELSE 0 END) as b1,
                 SUM(CASE WHEN due_date < ? AND due_date >= ? THEN balance_due ELSE 0 END) as b2,
                 SUM(CASE WHEN due_date < ? AND due_date >= ? THEN balance_due ELSE 0 END) as b3,
                 SUM(CASE WHEN due_date < ? THEN balance_due ELSE 0 END) as b4,
                 SUM(CASE WHEN due_date < ? THEN 1 ELSE 0 END) as overdue_count',
                [$t, $t, $d(30), $d(30), $d(60), $d(60), $d(90), $d(90), $t]
            )->first();

            $buckets = [
                ['label' => 'Current', 'amount' => round((float) $row->b0, 2)],
                ['label' => '1–30 days', 'amount' => round((float) $row->b1, 2)],
                ['label' => '31–60', 'amount' => round((float) $row->b2, 2)],
                ['label' => '61–90', 'amount' => round((float) $row->b3, 2)],
                ['label' => 'Over 90', 'amount' => round((float) $row->b4, 2)],
            ];

            $late = $open()->where('due_date', '<', $t)
                ->groupBy('customer_id')
                ->selectRaw('customer_id, SUM(balance_due) as owed, MIN(due_date) as oldest, COUNT(*) as invoices')
                ->orderByDesc('owed')
                ->limit(3)
                ->toBase()
                ->get();
            $names = Customer::whereIn('id', $late->pluck('customer_id'))->pluck('name', 'id');

            return [
                'total' => round(array_sum(array_column($buckets, 'amount')), 2),
                'overdue' => round(array_sum(array_column(array_slice($buckets, 1), 'amount')), 2),
                'overdueCount' => (int) $row->overdue_count,
                'buckets' => $buckets,
                'late' => $late->map(fn ($r) => [
                    'customer_id' => (int) $r->customer_id,
                    'name' => $names[$r->customer_id] ?? 'Customer',
                    'owed' => round((float) $r->owed, 2),
                    'days' => (int) Carbon::parse($r->oldest)->startOfDay()->diffInDays($today),
                    'invoices' => (int) $r->invoices,
                ])->all(),
            ];
        });
    }

    /** @return array<string, mixed> */
    public function payables(int $tenantId, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();

        return DashboardCache::remember($tenantId, 'payables:'.$today->toDateString(), function () use ($tenantId, $today) {
            $t = $today->toDateString();
            $week = $today->copy()->addDays(8)->toDateString();
            $open = fn () => Bill::where('tenant_id', $tenantId)
                ->whereIn('status', self::OPEN_BILL)
                ->where('balance_due', '>', 0)
                ->where('bill_date', '<', $today->copy()->addDay()->toDateString());

            $row = $open()->toBase()->selectRaw(
                'SUM(balance_due) as total,
                 SUM(CASE WHEN due_date < ? THEN balance_due ELSE 0 END) as overdue,
                 SUM(CASE WHEN due_date >= ? AND due_date < ? THEN balance_due ELSE 0 END) as week,
                 SUM(CASE WHEN due_date >= ? AND due_date < ? THEN 1 ELSE 0 END) as week_count',
                [$t, $t, $week, $t, $week]
            )->first();

            $next = $open()->with('vendor:id,name')->orderBy('due_date')->limit(4)
                ->get(['id', 'vendor_id', 'bill_number', 'due_date', 'balance_due']);

            return [
                'total' => round((float) $row->total, 2),
                'overdue' => round((float) $row->overdue, 2),
                'week' => round((float) $row->week, 2),
                'weekCount' => (int) $row->week_count,
                'next' => $next->map(fn (Bill $b) => [
                    'id' => $b->id,
                    'name' => $b->vendor->name ?? $b->bill_number,
                    'amount' => round((float) $b->balance_due, 2),
                    'due' => $b->due_date->toDateString(),
                    'daysLate' => $b->due_date->lt($today) ? (int) $b->due_date->copy()->startOfDay()->diffInDays($today) : 0,
                ])->all(),
            ];
        });
    }

    // ── Last 12 months, by customer and by expense ─────────────

    /** @return array{rows: list<array{name: string, amount: float}>, total: float} */
    public function topCustomers(int $tenantId, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();

        return DashboardCache::remember($tenantId, 'customers:'.$today->toDateString(), function () use ($tenantId, $today) {
            // Sales before VAT, as on the profit and loss.
            $rows = Invoice::where('tenant_id', $tenantId)
                ->whereNotIn('status', self::NOT_SALES)
                ->where('invoice_date', '>=', $today->copy()->subMonthsNoOverflow(11)->startOfMonth()->toDateString())
                ->where('invoice_date', '<', $today->copy()->addDay()->toDateString())
                ->groupBy('customer_id')
                ->selectRaw('customer_id, SUM(total - COALESCE(tax_amount, 0)) as sales')
                ->orderByDesc('sales')
                ->toBase()
                ->get();

            return $this->topFive(
                $rows->map(fn ($r) => ['id' => (int) $r->customer_id, 'amount' => round((float) $r->sales, 2)])->all(),
                Customer::whereIn('id', $rows->take(5)->pluck('customer_id'))->pluck('name', 'id')->all(),
                'All others'
            );
        });
    }

    /** @return array{rows: list<array{name: string, amount: float}>, total: float} */
    public function expenseBreakdown(int $tenantId, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();

        return DashboardCache::remember($tenantId, 'spend:'.$today->toDateString(), function () use ($tenantId, $today) {
            $accounts = $this->statements->accountBalances(
                $tenantId,
                $today->copy()->subMonthsNoOverflow(11)->startOfMonth()->toDateString(),
                $today->toDateString(),
                includeClosing: false
            )->where('type', ChartOfAccount::TYPE_EXPENSE)
                ->filter(fn ($a) => $a->balance > 0)
                ->sortByDesc('balance');

            return $this->topFive(
                $accounts->map(fn ($a) => ['id' => $a->id, 'amount' => (float) $a->balance])->values()->all(),
                $accounts->pluck('name', 'id')->all(),
                'Everything else'
            );
        });
    }

    /**
     * @param  list<array{id: int, amount: float}>  $rows  largest first
     * @param  array<int, string>  $names
     * @return array{rows: list<array{name: string, amount: float, other: bool}>, total: float}
     */
    private function topFive(array $rows, array $names, string $otherLabel): array
    {
        $top = array_slice($rows, 0, 5);
        $rest = round(array_sum(array_column(array_slice($rows, 5), 'amount')), 2);
        $out = array_map(fn ($r) => ['name' => $names[$r['id']] ?? '—', 'amount' => $r['amount'], 'other' => false], $top);
        if ($rest > 0) {
            $out[] = ['name' => $otherLabel, 'amount' => $rest, 'other' => true];
        }

        return ['rows' => $out, 'total' => round(array_sum(array_column($rows, 'amount')), 2)];
    }

    // ── Recent activity ─────────────────────────────────────────

    /**
     * The last things recorded, newest first, only of kinds $user can open.
     *
     * @return list<array{kind: string, text: string, amount: float, at: string, url: string}>
     */
    public function recentActivity(User $user, int $limit = 6): array
    {
        $tenantId = (int) $user->tenant_id;
        $all = DashboardCache::remember($tenantId, 'recent', function () use ($tenantId) {
            $take = 6;
            $rows = collect();

            Invoice::where('tenant_id', $tenantId)->with('customer:id,name')->latest('id')->take($take)
                ->get(['id', 'customer_id', 'invoice_number', 'total', 'created_at'])
                ->each(fn ($i) => $rows->push(['kind' => 'invoice', 'text' => "Invoice {$i->invoice_number} · ".($i->customer->name ?? ''), 'amount' => (float) $i->total, 'at' => (string) $i->created_at, 'id' => $i->id]));
            PaymentReceived::where('tenant_id', $tenantId)->with('customer:id,name')->latest('id')->take($take)
                ->get(['id', 'customer_id', 'amount', 'created_at'])
                ->each(fn ($p) => $rows->push(['kind' => 'payment-received', 'text' => 'Payment from '.($p->customer->name ?? 'a customer'), 'amount' => (float) $p->amount, 'at' => (string) $p->created_at, 'id' => $p->id]));
            Bill::where('tenant_id', $tenantId)->with('vendor:id,name')->latest('id')->take($take)
                ->get(['id', 'vendor_id', 'bill_number', 'total', 'created_at'])
                ->each(fn ($b) => $rows->push(['kind' => 'bill', 'text' => 'Bill from '.($b->vendor->name ?? $b->bill_number), 'amount' => (float) $b->total, 'at' => (string) $b->created_at, 'id' => $b->id]));
            Expense::where('tenant_id', $tenantId)->latest('id')->take($take)
                ->get(['id', 'description', 'total', 'created_at'])
                ->each(fn ($e) => $rows->push(['kind' => 'expense', 'text' => 'Expense · '.str((string) $e->description)->limit(40), 'amount' => (float) $e->total, 'at' => (string) $e->created_at, 'id' => $e->id]));
            PaymentMade::where('tenant_id', $tenantId)->with('vendor:id,name')->latest('id')->take($take)
                ->get(['id', 'vendor_id', 'amount', 'created_at'])
                ->each(fn ($p) => $rows->push(['kind' => 'payment-made', 'text' => 'Payment to '.($p->vendor->name ?? 'a supplier'), 'amount' => (float) $p->amount, 'at' => (string) $p->created_at, 'id' => $p->id]));

            return $rows->sortByDesc('at')->values()->take(20)->all();
        });

        $routes = [
            'invoice' => ['view invoices', 'invoices.show'],
            'payment-received' => ['view payments-received', 'payments-received.show'],
            'bill' => ['view bills', 'bills.show'],
            'expense' => ['view expenses', 'expenses.show'],
            'payment-made' => ['view payments-made', 'payments-made.show'],
        ];

        return collect($all)
            ->filter(fn ($r) => $user->can($routes[$r['kind']][0]))
            ->take($limit)
            ->map(fn ($r) => [
                'kind' => $r['kind'],
                'text' => $r['text'],
                'amount' => $r['amount'],
                'at' => $r['at'],
                'url' => route($routes[$r['kind']][1], $r['id']),
            ])
            ->values()
            ->all();
    }

    // ── Needs your attention ────────────────────────────────────

    /**
     * Things to act on today. Each line only appears when it is true and only
     * to people who can open the page it links to.
     *
     * @return list<array{tone: string, title: string, text: string, url: string, action?: string}>
     */
    public function attention(User $user, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $tenantId = (int) $user->tenant_id;
        $facts = DashboardCache::remember($tenantId, 'attention:'.$today->toDateString(), fn () => $this->attentionFacts($tenantId, $today));
        $money = fn (float $v) => Money::whole($v);
        $out = [];

        if ($facts['overdueInvoices'] > 0 && $user->can('view invoices')) {
            $n = $facts['overdueInvoices'];
            $out[] = [
                'tone' => 'bad',
                'title' => $n === 1 ? '1 invoice overdue' : "{$n} invoices overdue",
                'text' => $money($facts['overdueAmount']).' waiting.'.(config('mybooks.features.sms_whatsapp') && $user->can('send invoices') ? ' Send reminders by SMS or WhatsApp.' : ''),
                'url' => $user->can('view reports') ? route('reports.accounts-receivable') : route('invoices.index'),
            ];
        }

        if (($facts['billsOverdue'] > 0 || $facts['billsWeek'] > 0) && $user->can('view bills')) {
            $parts = [];
            if ($facts['billsOverdue'] > 0) {
                $parts[] = $facts['billsOverdue'] === 1 ? '1 bill overdue' : "{$facts['billsOverdue']} bills overdue";
            }
            if ($facts['billsWeek'] > 0) {
                $parts[] = ($facts['billsWeek'] === 1 ? '1 bill' : "{$facts['billsWeek']} bills").' due this week';
            }
            $out[] = [
                'tone' => $facts['billsOverdue'] > 0 ? 'bad' : 'warn',
                'title' => ucfirst(implode(', ', $parts)),
                'text' => $money($facts['billsAmount']).' to pay.',
                'url' => route('bills.index'),
            ];
        }

        if ($facts['vat'] && $user->can('view reports')) {
            $due = Carbon::parse($facts['vat']['due']);
            $late = $due->lt($today);
            $out[] = [
                'tone' => $late ? 'bad' : 'warn',
                'title' => 'VAT return for '.Carbon::parse($facts['vat']['month'].'-01')->format('F').($late ? ' is late' : ' due '.$due->format('j M')),
                'text' => $late ? 'It was due on '.$due->format('j F').'. Review and file it.' : 'Review it and mark it filed once submitted.',
                'url' => route('reports.vat-return', ['month' => $facts['vat']['month']]),
            ];
        }

        if ($facts['bankLines'] > 0 && $user->can('view banks') && config('mybooks.features.bank_feeds')) {
            $n = $facts['bankLines'];
            $out[] = [
                'tone' => 'info',
                'title' => $n === 1 ? '1 bank line to match' : "{$n} bank lines to match",
                'text' => 'From your bank feed.',
                'url' => route('bank-feeds.lines'),
            ];
        }

        if ($facts['lowStock'] > 0 && $user->can('low-stock dashboard-widgets') && $user->can('view inventory')) {
            $n = $facts['lowStock'];
            $out[] = [
                'tone' => 'warn',
                'title' => $n === 1 ? '1 item low on stock' : "{$n} items low on stock",
                'text' => implode(', ', $facts['lowStockNames']).($n > count($facts['lowStockNames']) ? ' and more.' : '.'),
                'url' => route('inventory.index'),
            ];
        }

        if ($facts['subscriptionPending'] && $user->can('manage subscription')) {
            $out[] = [
                'tone' => 'warn',
                'title' => 'Your plan is waiting for payment',
                'text' => 'Pay to keep using MyBooks without interruption.',
                'url' => route('settings.subscription'),
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function attentionFacts(int $tenantId, Carbon $today): array
    {
        $t = $today->toDateString();
        $inv = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', self::OPEN_INVOICE)->where('balance_due', '>', 0)->where('due_date', '<', $t)
            ->toBase()->selectRaw('COUNT(*) as n, SUM(balance_due) as amount')->first();

        $week = $today->copy()->addDays(8)->toDateString();
        $bills = Bill::where('tenant_id', $tenantId)
            ->whereIn('status', self::OPEN_BILL)->where('balance_due', '>', 0)->where('due_date', '<', $week)
            ->toBase()->selectRaw('SUM(CASE WHEN due_date < ? THEN 1 ELSE 0 END) as overdue, SUM(CASE WHEN due_date >= ? THEN 1 ELSE 0 END) as week, SUM(balance_due) as amount', [$t, $t])->first();

        $low = Item::where('tenant_id', $tenantId)
            ->where('track_inventory', true)
            ->whereHas('inventories')
            ->whereRaw(Item::onHandSql().' <= items.reorder_level')
            ->orderBy('name');

        return [
            'overdueInvoices' => (int) ($inv->n ?? 0),
            'overdueAmount' => round((float) ($inv->amount ?? 0), 2),
            'billsOverdue' => (int) ($bills->overdue ?? 0),
            'billsWeek' => (int) ($bills->week ?? 0),
            'billsAmount' => round((float) ($bills->amount ?? 0), 2),
            'vat' => $this->vatDue($tenantId, $today),
            'bankLines' => BankFeedLine::where('tenant_id', $tenantId)->where('status', 'new')->count(),
            'lowStock' => (clone $low)->count(),
            'lowStockNames' => (clone $low)->limit(3)->pluck('name')->all(),
            'subscriptionPending' => Subscription::where('tenant_id', $tenantId)->latest('id')->value('status') === Subscription::STATUS_PENDING,
        ];
    }

    /**
     * Last month's VAT return, if the business charged or paid VAT that
     * month and has not marked it filed. Due on the 21st of this month.
     *
     * @return array{month: string, due: string}|null
     */
    private function vatDue(int $tenantId, Carbon $today): ?array
    {
        $month = $today->copy()->subMonthNoOverflow()->startOfMonth();
        $from = $month->toDateString();
        $to = $month->copy()->addMonthNoOverflow()->toDateString();

        $hadVat = Invoice::where('tenant_id', $tenantId)->whereNotIn('status', self::NOT_SALES)
            ->where('invoice_date', '>=', $from)->where('invoice_date', '<', $to)->where('tax_amount', '>', 0)->exists()
            || Bill::where('tenant_id', $tenantId)->where('bill_date', '>=', $from)->where('bill_date', '<', $to)->where('tax_amount', '>', 0)->exists();
        if (! $hadVat) {
            return null;
        }

        $filed = VatReturnFiling::where('tenant_id', $tenantId)->where('month', $month->format('Y-m'))->whereNotNull('filed_at')->exists();

        return $filed ? null : ['month' => $month->format('Y-m'), 'due' => $today->copy()->day(21)->toDateString()];
    }

    // ── Getting started ─────────────────────────────────────────

    /**
     * First steps for a new business, or null once they are done, the
     * business is clearly up and running, or the owner hid the card.
     *
     * @return list<array{label: string, done: bool, url: string}>|null
     */
    public function gettingStarted(User $user): ?array
    {
        $tenant = Tenant::find($user->tenant_id);
        if (! $tenant || ($tenant->settings['dashboard_start_hidden'] ?? false)) {
            return null;
        }

        $steps = DashboardCache::remember((int) $tenant->id, 'start', function () use ($tenant) {
            $id = $tenant->id;

            return [
                'details' => filled($tenant->address) || filled($tenant->logo),
                'bank' => Bank::where('tenant_id', $id)->exists(),
                'customer' => Customer::where('tenant_id', $id)->exists(),
                'invoice' => Invoice::where('tenant_id', $id)->where('status', '!=', 'draft')->exists(),
                'spend' => Expense::where('tenant_id', $id)->exists() || Bill::where('tenant_id', $id)->exists(),
                'team' => User::where('tenant_id', $id)->count() > 1,
                'established' => Invoice::where('tenant_id', $id)->count() >= 10,
            ];
        });

        if ($steps['established']) {
            return null;
        }

        $list = [
            ['label' => 'Add your business address and logo', 'done' => $steps['details'], 'url' => route('settings.company'), 'can' => 'view settings'],
            ['label' => 'Add your bank account', 'done' => $steps['bank'], 'url' => route('banks.create'), 'can' => 'create banks'],
            ['label' => 'Add your first customer', 'done' => $steps['customer'], 'url' => route('customers.create'), 'can' => 'create customers'],
            ['label' => 'Send your first invoice', 'done' => $steps['invoice'], 'url' => route('invoices.create'), 'can' => 'create invoices'],
            ['label' => 'Record an expense or a bill', 'done' => $steps['spend'], 'url' => route('expenses.create'), 'can' => 'create expenses'],
            ['label' => 'Invite someone from your team', 'done' => $steps['team'], 'url' => route('settings.users.create'), 'can' => 'create users'],
        ];
        $list = array_values(array_filter($list, fn ($s) => $user->can($s['can'])));

        if ($list === [] || collect($list)->every('done')) {
            return null;
        }

        return array_map(fn ($s) => ['label' => $s['label'], 'done' => $s['done'], 'url' => $s['url']], $list);
    }

    // ── Helpers ─────────────────────────────────────────────────

    /** @return list<int> */
    private function cashAccountIds(int $tenantId): array
    {
        return ChartOfAccount::withTrashed()->where('tenant_id', $tenantId)
            ->where('type', ChartOfAccount::TYPE_ASSET)->whereIn('sub_type', ['cash', 'bank'])
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
