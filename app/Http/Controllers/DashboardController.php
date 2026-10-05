<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Support\SqlDate;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    private const CACHE_SECONDS = 300;

    public function index()
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;

        $data = [];

        // Totals are cached per business for 5 minutes, and month filters
        // use date ranges so the date indexes can be used (P5).
        $monthStart = now()->startOfMonth()->toDateString();
        $nextMonth = now()->startOfMonth()->addMonth()->toDateString();
        $cache = fn (string $key, callable $callback) => Cache::remember("dashboard:{$tenantId}:{$key}", self::CACHE_SECONDS, $callback);

        // Total Revenue card
        if ($user->can('total-revenue dashboard-widgets')) {
            [$data['totalRevenue'], $data['monthlyRevenue']] = $cache('revenue:'.$monthStart, function () use ($tenantId, $monthStart, $nextMonth) {
                $row = Invoice::where('tenant_id', $tenantId)
                    ->whereIn('status', ['paid', 'partial'])
                    ->selectRaw('SUM(amount_paid) as total, SUM(CASE WHEN invoice_date >= ? AND invoice_date < ? THEN amount_paid ELSE 0 END) as this_month', [$monthStart, $nextMonth])
                    ->toBase()
                    ->first();

                return [$row->total ?? 0, $row->this_month ?? 0];
            });
        }

        // Outstanding Receivables card
        if ($user->can('outstanding-receivables dashboard-widgets')) {
            [$data['accountsReceivable'], $data['overdueInvoices']] = $cache('receivables', function () use ($tenantId) {
                $receivable = Invoice::where('tenant_id', $tenantId)
                    ->whereIn('status', ['unpaid', 'partial', 'overdue'])
                    ->sum('balance_due');

                $overdue = Invoice::where('tenant_id', $tenantId)
                    ->where(function ($query) {
                        $query->where('status', 'overdue')
                            ->orWhere(function ($q) {
                                $q->whereIn('status', ['unpaid', 'partial'])
                                    ->where('due_date', '<', now());
                            });
                    })
                    ->count();

                return [$receivable, $overdue];
            });
        }

        // Monthly Expenses card
        if ($user->can('monthly-expenses dashboard-widgets')) {
            $data['monthlyExpenses'] = $cache('expenses:'.$monthStart, fn () => Expense::where('tenant_id', $tenantId)
                ->where('status', Expense::STATUS_PAID)
                ->where('expense_date', '>=', $monthStart)
                ->where('expense_date', '<', $nextMonth)
                ->sum('total'));
        }

        // Employees count card
        if ($user->can('employees-count dashboard-widgets')) {
            $data['totalEmployees'] = $cache('employees', fn () => Employee::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->count());
        }

        // Revenue vs Expenses chart: three grouped queries instead of 36
        if ($user->can('revenue-chart dashboard-widgets')) {
            $data['monthlyTrends'] = $cache('trends:'.now()->year, fn () => $this->monthlyTrends($tenantId, now()->year));
        }

        // Recent Invoices widget
        if ($user->can('recent-invoices dashboard-widgets')) {
            $data['recentInvoices'] = Invoice::where('tenant_id', $tenantId)
                ->with('customer')
                ->latest()
                ->take(5)
                ->get();
        }

        // Pending Bills widget
        if ($user->can('pending-bills dashboard-widgets')) {
            $data['pendingBills'] = Bill::where('tenant_id', $tenantId)
                ->with('vendor')
                ->whereIn('status', ['unpaid', 'partial', 'overdue'])
                ->orderBy('due_date')
                ->take(5)
                ->get();
        }

        // Low Stock Items widget
        if ($user->can('low-stock dashboard-widgets')) {
            // Per item, all warehouses together (session 12).
            $data['lowStockItems'] = Item::where('tenant_id', $tenantId)
                ->where('track_inventory', true)
                ->whereHas('inventories')
                ->whereRaw(Item::onHandSql().' <= items.reorder_level')
                ->with('inventory')
                ->take(5)
                ->get()
                ->map(fn (Item $item) => $item->inventory->setRelation('item', $item));
        }

        return view('dashboard', $data);
    }

    /**
     * Revenue and expenses for each month of the year, in three queries.
     *
     * @return array<int, array{month: string, revenue: float, expenses: float}>
     */
    private function monthlyTrends($tenantId, int $year): array
    {
        $from = "{$year}-01-01";
        $to = ($year + 1).'-01-01';

        $revenue = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', ['paid', 'partial'])
            ->where('invoice_date', '>=', $from)
            ->where('invoice_date', '<', $to)
            ->selectRaw(SqlDate::month('invoice_date').' as ym, SUM(amount_paid) as total')
            ->groupBy('ym')
            ->toBase()
            ->pluck('total', 'ym');

        $expenses = Expense::where('tenant_id', $tenantId)
            ->where('status', Expense::STATUS_PAID)
            ->where('expense_date', '>=', $from)
            ->where('expense_date', '<', $to)
            ->selectRaw(SqlDate::month('expense_date').' as ym, SUM(total) as total')
            ->groupBy('ym')
            ->toBase()
            ->pluck('total', 'ym');

        $bills = Bill::where('tenant_id', $tenantId)
            ->where('bill_date', '>=', $from)
            ->where('bill_date', '<', $to)
            ->selectRaw(SqlDate::month('bill_date').' as ym, SUM(amount_paid) as total')
            ->groupBy('ym')
            ->toBase()
            ->pluck('total', 'ym');

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $trends = [];
        foreach ($months as $i => $label) {
            $key = sprintf('%d-%02d', $year, $i + 1);
            $trends[] = [
                'month' => $label,
                'revenue' => (float) ($revenue[$key] ?? 0),
                'expenses' => (float) ($expenses[$key] ?? 0) + (float) ($bills[$key] ?? 0),
            ];
        }

        return $trends;
    }
}
