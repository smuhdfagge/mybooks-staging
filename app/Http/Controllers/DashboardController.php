<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Invoice;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;

        $data = [];

        // Total Revenue card
        if ($user->can('total-revenue dashboard-widgets')) {
            $data['totalRevenue'] = Invoice::where('tenant_id', $tenantId)
                ->whereIn('status', ['paid', 'partial'])
                ->sum('amount_paid');

            $data['monthlyRevenue'] = Invoice::where('tenant_id', $tenantId)
                ->whereIn('status', ['paid', 'partial'])
                ->whereMonth('invoice_date', now()->month)
                ->whereYear('invoice_date', now()->year)
                ->sum('amount_paid');
        }

        // Outstanding Receivables card
        if ($user->can('outstanding-receivables dashboard-widgets')) {
            $data['accountsReceivable'] = Invoice::where('tenant_id', $tenantId)
                ->whereIn('status', ['unpaid', 'partial', 'overdue'])
                ->sum('balance_due');

            $data['overdueInvoices'] = Invoice::where('tenant_id', $tenantId)
                ->where(function ($query) {
                    $query->where('status', 'overdue')
                        ->orWhere(function ($q) {
                            $q->whereIn('status', ['unpaid', 'partial'])
                                ->where('due_date', '<', now());
                        });
                })
                ->count();
        }

        // Monthly Expenses card
        if ($user->can('monthly-expenses dashboard-widgets')) {
            $data['monthlyExpenses'] = Expense::where('tenant_id', $tenantId)
                ->where('status', Expense::STATUS_PAID)
                ->whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->sum('total');
        }

        // Employees count card
        if ($user->can('employees-count dashboard-widgets')) {
            $data['totalEmployees'] = Employee::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->count();
        }

        // Revenue vs Expenses chart
        if ($user->can('revenue-chart dashboard-widgets')) {
            $currentYear = now()->year;
            $monthlyTrends = [];
            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            for ($month = 1; $month <= 12; $month++) {
                $revenue = Invoice::where('tenant_id', $tenantId)
                    ->whereIn('status', ['paid', 'partial'])
                    ->whereMonth('invoice_date', $month)
                    ->whereYear('invoice_date', $currentYear)
                    ->sum('amount_paid');

                $expenses = Expense::where('tenant_id', $tenantId)
                    ->where('status', Expense::STATUS_PAID)
                    ->whereMonth('expense_date', $month)
                    ->whereYear('expense_date', $currentYear)
                    ->sum('total');

                $billsPaid = Bill::where('tenant_id', $tenantId)
                    ->whereMonth('bill_date', $month)
                    ->whereYear('bill_date', $currentYear)
                    ->sum('amount_paid');

                $monthlyTrends[] = [
                    'month' => $months[$month - 1],
                    'revenue' => (float) $revenue,
                    'expenses' => (float) ($expenses + $billsPaid),
                ];
            }

            $data['monthlyTrends'] = $monthlyTrends;
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
            $data['lowStockItems'] = Inventory::where('tenant_id', $tenantId)
                ->with('item')
                ->whereHas('item', function ($query) {
                    $query->where('track_inventory', true)
                        ->whereColumn('inventories.quantity', '<=', 'items.reorder_level');
                })
                ->take(5)
                ->get();
        }

        return view('dashboard', $data);
    }
}
