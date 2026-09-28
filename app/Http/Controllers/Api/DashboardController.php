<?php

namespace App\Http\Controllers\Api;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\PaymentReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    /**
     * Get dashboard summary data
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        // Revenue metrics
        $totalRevenue = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', ['paid', 'partial'])
            ->sum('amount_paid');

        $monthlyRevenue = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', ['paid', 'partial'])
            ->whereMonth('invoice_date', now()->month)
            ->whereYear('invoice_date', now()->year)
            ->sum('amount_paid');

        // Outstanding amounts
        $accountsReceivable = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->sum('balance_due');

        $accountsPayable = Bill::where('tenant_id', $tenantId)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->sum('balance_due');

        // Counts
        $totalCustomers = Customer::where('tenant_id', $tenantId)->count();
        $totalEmployees = Employee::where('tenant_id', $tenantId)->where('status', 'active')->count();

        // Monthly expenses (only paid expenses)
        $monthlyExpenses = Expense::where('tenant_id', $tenantId)
            ->where('status', Expense::STATUS_PAID)
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('total');

        // Recent invoices count
        $pendingInvoices = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->count();

        // Pending bills count
        $pendingBills = Bill::where('tenant_id', $tenantId)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->count();

        // Low stock items count
        $lowStockCount = Inventory::where('tenant_id', $tenantId)
            ->whereHas('item', function ($query) {
                $query->where('track_inventory', true)
                    ->whereColumn('inventories.quantity', '<=', 'items.reorder_level');
            })
            ->count();

        return $this->success([
            'revenue' => [
                'total' => (float) $totalRevenue,
                'monthly' => (float) $monthlyRevenue,
            ],
            'outstanding' => [
                'receivable' => (float) $accountsReceivable,
                'payable' => (float) $accountsPayable,
            ],
            'counts' => [
                'customers' => $totalCustomers,
                'employees' => $totalEmployees,
                'pending_invoices' => $pendingInvoices,
                'pending_bills' => $pendingBills,
                'low_stock_items' => $lowStockCount,
            ],
            'expenses' => [
                'monthly' => (float) $monthlyExpenses,
            ],
        ]);
    }

    /**
     * Get monthly trends data for charts
     */
    public function trends(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $year = $request->input('year', now()->year);

        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $monthlyTrends = [];

        for ($month = 1; $month <= 12; $month++) {
            $revenue = Invoice::where('tenant_id', $tenantId)
                ->whereIn('status', ['paid', 'partial'])
                ->whereMonth('invoice_date', $month)
                ->whereYear('invoice_date', $year)
                ->sum('amount_paid');

            $expenses = Expense::where('tenant_id', $tenantId)
                ->where('status', Expense::STATUS_PAID)
                ->whereMonth('expense_date', $month)
                ->whereYear('expense_date', $year)
                ->sum('total');

            $monthlyTrends[] = [
                'month' => $months[$month - 1],
                'month_number' => $month,
                'revenue' => (float) $revenue,
                'expenses' => (float) $expenses,
                'profit' => (float) ($revenue - $expenses),
            ];
        }

        return $this->success([
            'year' => $year,
            'trends' => $monthlyTrends,
        ]);
    }

    /**
     * Get recent transactions
     */
    public function recentTransactions(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $limit = $request->input('limit', 10);

        // Recent invoices
        $recentInvoices = Invoice::where('tenant_id', $tenantId)
            ->with('customer:id,name')
            ->latest()
            ->take($limit)
            ->get(['id', 'customer_id', 'invoice_number', 'total', 'status', 'invoice_date', 'created_at']);

        // Recent expenses
        $recentExpenses = Expense::where('tenant_id', $tenantId)
            ->with('vendor:id,name')
            ->latest()
            ->take($limit)
            ->get(['id', 'vendor_id', 'expense_number', 'name', 'total', 'status', 'expense_date', 'created_at']);

        // Recent payments received
        $recentPayments = PaymentReceived::where('tenant_id', $tenantId)
            ->with('customer:id,name')
            ->latest()
            ->take($limit)
            ->get(['id', 'customer_id', 'payment_number', 'amount', 'payment_date', 'created_at']);

        return $this->success([
            'invoices' => $recentInvoices,
            'expenses' => $recentExpenses,
            'payments' => $recentPayments,
        ]);
    }
}
