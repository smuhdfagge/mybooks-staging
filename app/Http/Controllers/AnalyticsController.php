<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Bill;
use App\Models\Expense;
use App\Models\PaymentReceived;
use App\Models\PaymentMade;
use App\Models\SalesOrder;
use App\Models\SalesReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AnalyticsController extends Controller
{
    /**
     * Display the advanced analytics dashboard.
     */
    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        
        // Date range handling
        $period = $request->get('period', 'this_month');
        $dates = $this->getDateRange($period, $request);
        $startDate = $dates['start'];
        $endDate = $dates['end'];
        $previousStartDate = $dates['previous_start'];
        $previousEndDate = $dates['previous_end'];

        // Get all analytics data
        $kpis = $this->getKeyPerformanceIndicators($tenantId, $startDate, $endDate, $previousStartDate, $previousEndDate);
        $revenueTrends = $this->getRevenueTrends($tenantId, $startDate, $endDate, $period);
        $salesByCustomer = $this->getSalesByCustomer($tenantId, $startDate, $endDate);
        $topSellingItems = $this->getTopSellingItems($tenantId, $startDate, $endDate);
        $invoiceStatusDistribution = $this->getInvoiceStatusDistribution($tenantId, $startDate, $endDate);
        $paymentMethodDistribution = $this->getPaymentMethodDistribution($tenantId, $startDate, $endDate);
        $customerAcquisition = $this->getCustomerAcquisition($tenantId, $startDate, $endDate);
        $averageOrderValue = $this->getAverageOrderValueTrend($tenantId, $startDate, $endDate, $period);
        $overdueAnalysis = $this->getOverdueAnalysis($tenantId);
        $customerRetention = $this->getCustomerRetention($tenantId, $startDate, $endDate);
        $dailySalesHeatmap = $this->getDailySalesHeatmap($tenantId, $startDate, $endDate);

        return view('analytics.index', compact(
            'period',
            'startDate',
            'endDate',
            'kpis',
            'revenueTrends',
            'salesByCustomer',
            'topSellingItems',
            'invoiceStatusDistribution',
            'paymentMethodDistribution',
            'customerAcquisition',
            'averageOrderValue',
            'overdueAnalysis',
            'customerRetention',
            'dailySalesHeatmap'
        ));
    }

    /**
     * Get date range based on period selection.
     */
    private function getDateRange(string $period, Request $request): array
    {
        $now = Carbon::now();
        
        switch ($period) {
            case 'today':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                $previousStart = $now->copy()->subDay()->startOfDay();
                $previousEnd = $now->copy()->subDay()->endOfDay();
                break;
            case 'yesterday':
                $start = $now->copy()->subDay()->startOfDay();
                $end = $now->copy()->subDay()->endOfDay();
                $previousStart = $now->copy()->subDays(2)->startOfDay();
                $previousEnd = $now->copy()->subDays(2)->endOfDay();
                break;
            case 'this_week':
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                $previousStart = $now->copy()->subWeek()->startOfWeek();
                $previousEnd = $now->copy()->subWeek()->endOfWeek();
                break;
            case 'last_week':
                $start = $now->copy()->subWeek()->startOfWeek();
                $end = $now->copy()->subWeek()->endOfWeek();
                $previousStart = $now->copy()->subWeeks(2)->startOfWeek();
                $previousEnd = $now->copy()->subWeeks(2)->endOfWeek();
                break;
            case 'this_month':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $previousStart = $now->copy()->subMonth()->startOfMonth();
                $previousEnd = $now->copy()->subMonth()->endOfMonth();
                break;
            case 'last_month':
                $start = $now->copy()->subMonth()->startOfMonth();
                $end = $now->copy()->subMonth()->endOfMonth();
                $previousStart = $now->copy()->subMonths(2)->startOfMonth();
                $previousEnd = $now->copy()->subMonths(2)->endOfMonth();
                break;
            case 'this_quarter':
                $start = $now->copy()->startOfQuarter();
                $end = $now->copy()->endOfQuarter();
                $previousStart = $now->copy()->subQuarter()->startOfQuarter();
                $previousEnd = $now->copy()->subQuarter()->endOfQuarter();
                break;
            case 'last_quarter':
                $start = $now->copy()->subQuarter()->startOfQuarter();
                $end = $now->copy()->subQuarter()->endOfQuarter();
                $previousStart = $now->copy()->subQuarters(2)->startOfQuarter();
                $previousEnd = $now->copy()->subQuarters(2)->endOfQuarter();
                break;
            case 'this_year':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                $previousStart = $now->copy()->subYear()->startOfYear();
                $previousEnd = $now->copy()->subYear()->endOfYear();
                break;
            case 'last_year':
                $start = $now->copy()->subYear()->startOfYear();
                $end = $now->copy()->subYear()->endOfYear();
                $previousStart = $now->copy()->subYears(2)->startOfYear();
                $previousEnd = $now->copy()->subYears(2)->endOfYear();
                break;
            case 'custom':
                $start = Carbon::parse($request->get('start_date', $now->copy()->startOfMonth()));
                $end = Carbon::parse($request->get('end_date', $now));
                $daysDiff = $start->diffInDays($end);
                $previousStart = $start->copy()->subDays($daysDiff + 1);
                $previousEnd = $start->copy()->subDay();
                break;
            default:
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $previousStart = $now->copy()->subMonth()->startOfMonth();
                $previousEnd = $now->copy()->subMonth()->endOfMonth();
        }

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'previous_start' => $previousStart->format('Y-m-d'),
            'previous_end' => $previousEnd->format('Y-m-d'),
        ];
    }

    /**
     * Get key performance indicators with comparison.
     */
    private function getKeyPerformanceIndicators($tenantId, $startDate, $endDate, $previousStartDate, $previousEndDate): array
    {
        // Current period metrics
        $currentRevenue = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->whereIn('status', ['paid', 'partial'])
            ->sum('amount_paid');

        $currentInvoiceCount = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->count();

        $currentInvoiceTotal = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->sum('total');

        $currentNewCustomers = Customer::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $currentPayments = PaymentReceived::where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('is_deposit', false)
            ->sum('amount');

        // Previous period metrics
        $previousRevenue = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$previousStartDate, $previousEndDate])
            ->whereIn('status', ['paid', 'partial'])
            ->sum('amount_paid');

        $previousInvoiceCount = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$previousStartDate, $previousEndDate])
            ->count();

        $previousNewCustomers = Customer::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->count();

        $previousPayments = PaymentReceived::where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$previousStartDate, $previousEndDate])
            ->where('is_deposit', false)
            ->sum('amount');

        // Calculate averages and rates
        $avgOrderValue = $currentInvoiceCount > 0 ? $currentInvoiceTotal / $currentInvoiceCount : 0;
        $previousAvgOrderValue = $previousInvoiceCount > 0 
            ? Invoice::where('tenant_id', $tenantId)
                ->whereBetween('invoice_date', [$previousStartDate, $previousEndDate])
                ->sum('total') / $previousInvoiceCount 
            : 0;

        // Collection rate
        $collectionRate = $currentInvoiceTotal > 0 ? ($currentPayments / $currentInvoiceTotal) * 100 : 0;

        // Outstanding balance
        $outstandingBalance = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->sum('balance_due');

        // Overdue amount
        $overdueAmount = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', ['unpaid', 'partial'])
            ->where('due_date', '<', now())
            ->sum('balance_due');

        return [
            'revenue' => [
                'current' => $currentRevenue,
                'previous' => $previousRevenue,
                'change' => $this->calculatePercentageChange($previousRevenue, $currentRevenue),
            ],
            'invoices' => [
                'current' => $currentInvoiceCount,
                'previous' => $previousInvoiceCount,
                'change' => $this->calculatePercentageChange($previousInvoiceCount, $currentInvoiceCount),
                'total_value' => $currentInvoiceTotal,
            ],
            'avg_order_value' => [
                'current' => $avgOrderValue,
                'previous' => $previousAvgOrderValue,
                'change' => $this->calculatePercentageChange($previousAvgOrderValue, $avgOrderValue),
            ],
            'new_customers' => [
                'current' => $currentNewCustomers,
                'previous' => $previousNewCustomers,
                'change' => $this->calculatePercentageChange($previousNewCustomers, $currentNewCustomers),
            ],
            'payments' => [
                'current' => $currentPayments,
                'previous' => $previousPayments,
                'change' => $this->calculatePercentageChange($previousPayments, $currentPayments),
            ],
            'collection_rate' => round($collectionRate, 1),
            'outstanding_balance' => $outstandingBalance,
            'overdue_amount' => $overdueAmount,
        ];
    }

    /**
     * Get revenue trends data for charts.
     */
    private function getRevenueTrends($tenantId, $startDate, $endDate, $period): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        // Determine grouping based on period
        if (in_array($period, ['today', 'yesterday'])) {
            $groupBy = 'hour';
            $format = 'H:00';
        } elseif (in_array($period, ['this_week', 'last_week'])) {
            $groupBy = 'day';
            $format = 'D';
        } elseif (in_array($period, ['this_month', 'last_month'])) {
            $groupBy = 'day';
            $format = 'd';
        } elseif (in_array($period, ['this_quarter', 'last_quarter'])) {
            $groupBy = 'week';
            $format = 'W';
        } else {
            $groupBy = 'month';
            $format = 'M';
        }

        $data = [];
        $labels = [];
        $revenueData = [];
        $invoiceCountData = [];
        $paymentsData = [];

        if ($groupBy === 'hour') {
            for ($hour = 0; $hour < 24; $hour++) {
                $hourStart = $start->copy()->hour($hour)->startOfHour();
                $hourEnd = $start->copy()->hour($hour)->endOfHour();
                
                $labels[] = sprintf('%02d:00', $hour);
                
                $revenueData[] = (float) Invoice::where('tenant_id', $tenantId)
                    ->whereBetween('invoice_date', [$hourStart, $hourEnd])
                    ->whereIn('status', ['paid', 'partial'])
                    ->sum('amount_paid');
                
                $invoiceCountData[] = Invoice::where('tenant_id', $tenantId)
                    ->whereBetween('invoice_date', [$hourStart, $hourEnd])
                    ->count();
                
                $paymentsData[] = (float) PaymentReceived::where('tenant_id', $tenantId)
                    ->whereBetween('payment_date', [$hourStart, $hourEnd])
                    ->sum('amount');
            }
        } elseif ($groupBy === 'day') {
            $period = CarbonPeriod::create($start, $end);
            foreach ($period as $date) {
                $labels[] = $date->format($format);
                
                $revenueData[] = (float) Invoice::where('tenant_id', $tenantId)
                    ->whereDate('invoice_date', $date)
                    ->whereIn('status', ['paid', 'partial'])
                    ->sum('amount_paid');
                
                $invoiceCountData[] = Invoice::where('tenant_id', $tenantId)
                    ->whereDate('invoice_date', $date)
                    ->count();
                
                $paymentsData[] = (float) PaymentReceived::where('tenant_id', $tenantId)
                    ->whereDate('payment_date', $date)
                    ->sum('amount');
            }
        } elseif ($groupBy === 'week') {
            $current = $start->copy()->startOfWeek();
            while ($current <= $end) {
                $weekEnd = $current->copy()->endOfWeek();
                $labels[] = 'W' . $current->weekOfYear;
                
                $revenueData[] = (float) Invoice::where('tenant_id', $tenantId)
                    ->whereBetween('invoice_date', [$current, $weekEnd])
                    ->whereIn('status', ['paid', 'partial'])
                    ->sum('amount_paid');
                
                $invoiceCountData[] = Invoice::where('tenant_id', $tenantId)
                    ->whereBetween('invoice_date', [$current, $weekEnd])
                    ->count();
                
                $paymentsData[] = (float) PaymentReceived::where('tenant_id', $tenantId)
                    ->whereBetween('payment_date', [$current, $weekEnd])
                    ->sum('amount');
                
                $current->addWeek();
            }
        } else {
            // Monthly grouping
            $current = $start->copy()->startOfMonth();
            while ($current <= $end) {
                $monthEnd = $current->copy()->endOfMonth();
                $labels[] = $current->format('M Y');
                
                $revenueData[] = (float) Invoice::where('tenant_id', $tenantId)
                    ->whereBetween('invoice_date', [$current, $monthEnd])
                    ->whereIn('status', ['paid', 'partial'])
                    ->sum('amount_paid');
                
                $invoiceCountData[] = Invoice::where('tenant_id', $tenantId)
                    ->whereBetween('invoice_date', [$current, $monthEnd])
                    ->count();
                
                $paymentsData[] = (float) PaymentReceived::where('tenant_id', $tenantId)
                    ->whereBetween('payment_date', [$current, $monthEnd])
                    ->sum('amount');
                
                $current->addMonth();
            }
        }

        return [
            'labels' => $labels,
            'revenue' => $revenueData,
            'invoice_count' => $invoiceCountData,
            'payments' => $paymentsData,
        ];
    }

    /**
     * Get top customers by sales.
     */
    private function getSalesByCustomer($tenantId, $startDate, $endDate, $limit = 10): array
    {
        $customers = Customer::where('tenant_id', $tenantId)
            ->withSum(['invoices' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('invoice_date', [$startDate, $endDate]);
            }], 'total')
            ->withSum(['invoices' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('invoice_date', [$startDate, $endDate]);
            }], 'amount_paid')
            ->withCount(['invoices' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('invoice_date', [$startDate, $endDate]);
            }])
            ->having('invoices_sum_total', '>', 0)
            ->orderByDesc('invoices_sum_total')
            ->limit($limit)
            ->get();

        return $customers->map(function ($customer) {
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'company' => $customer->company_name,
                'total_sales' => (float) $customer->invoices_sum_total ?? 0,
                'amount_paid' => (float) $customer->invoices_sum_amount_paid ?? 0,
                'invoice_count' => $customer->invoices_count ?? 0,
                'outstanding' => ($customer->invoices_sum_total ?? 0) - ($customer->invoices_sum_amount_paid ?? 0),
            ];
        })->toArray();
    }

    /**
     * Get top selling items.
     */
    private function getTopSellingItems($tenantId, $startDate, $endDate, $limit = 10): array
    {
        $items = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('items', 'invoice_items.item_id', '=', 'items.id')
            ->where('invoices.tenant_id', $tenantId)
            ->whereBetween('invoices.invoice_date', [$startDate, $endDate])
            ->whereNull('invoices.deleted_at')
            ->select(
                'items.id',
                'items.name',
                'items.sku',
                DB::raw('SUM(invoice_items.quantity) as quantity_sold'),
                DB::raw('SUM(invoice_items.total) as total_revenue'),
                DB::raw('COUNT(DISTINCT invoices.id) as order_count'),
                DB::raw('AVG(invoice_items.unit_price) as avg_price')
            )
            ->groupBy('items.id', 'items.name', 'items.sku')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();

        return $items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity_sold' => (float) $item->quantity_sold,
                'total_revenue' => (float) $item->total_revenue,
                'order_count' => $item->order_count,
                'avg_price' => (float) $item->avg_price,
            ];
        })->toArray();
    }

    /**
     * Get invoice status distribution.
     */
    private function getInvoiceStatusDistribution($tenantId, $startDate, $endDate): array
    {
        $statuses = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statusLabels = [
            'draft' => 'Draft',
            'sent' => 'Sent',
            'unpaid' => 'Unpaid',
            'partial' => 'Partially Paid',
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'cancelled' => 'Cancelled',
        ];

        $data = [];
        foreach ($statusLabels as $status => $label) {
            $data[] = [
                'status' => $status,
                'label' => $label,
                'count' => $statuses->get($status)->count ?? 0,
                'total' => (float) ($statuses->get($status)->total ?? 0),
            ];
        }

        return $data;
    }

    /**
     * Get payment method distribution.
     */
    private function getPaymentMethodDistribution($tenantId, $startDate, $endDate): array
    {
        $payments = PaymentReceived::where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('is_deposit', false)
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get();

        return $payments->map(function ($payment) {
            return [
                'method' => ucfirst(str_replace('_', ' ', $payment->payment_method)),
                'count' => $payment->count,
                'total' => (float) $payment->total,
            ];
        })->toArray();
    }

    /**
     * Get revenue by item category.
     */
    private function getRevenueByCategory($tenantId, $startDate, $endDate): array
    {
        $categories = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->leftJoin('items', 'invoice_items.item_id', '=', 'items.id')
            ->leftJoin('item_categories', 'items.category_id', '=', 'item_categories.id')
            ->where('invoices.tenant_id', $tenantId)
            ->whereBetween('invoices.invoice_date', [$startDate, $endDate])
            ->whereNull('invoices.deleted_at')
            ->select(
                DB::raw('COALESCE(item_categories.name, "Uncategorized") as category'),
                DB::raw('SUM(invoice_items.total) as total'),
                DB::raw('SUM(invoice_items.quantity) as quantity')
            )
            ->groupBy('item_categories.name')
            ->orderByDesc('total')
            ->get();

        return $categories->map(function ($cat) {
            return [
                'category' => $cat->category,
                'total' => (float) $cat->total,
                'quantity' => (float) $cat->quantity,
            ];
        })->toArray();
    }

    /**
     * Get customer acquisition trends.
     */
    private function getCustomerAcquisition($tenantId, $startDate, $endDate): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        $data = [];
        $current = $start->copy()->startOfMonth();
        
        while ($current <= $end) {
            $monthEnd = $current->copy()->endOfMonth();
            
            $newCustomers = Customer::where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$current, $monthEnd])
                ->count();
            
            // Customers who made their first purchase this month
            $firstTimeBuyers = Customer::where('tenant_id', $tenantId)
                ->whereHas('invoices', function ($q) use ($current, $monthEnd) {
                    $q->whereBetween('invoice_date', [$current, $monthEnd]);
                })
                ->whereDoesntHave('invoices', function ($q) use ($current) {
                    $q->where('invoice_date', '<', $current);
                })
                ->count();
            
            $data[] = [
                'month' => $current->format('M Y'),
                'new_customers' => $newCustomers,
                'first_time_buyers' => $firstTimeBuyers,
            ];
            
            $current->addMonth();
        }

        return $data;
    }

    /**
     * Get average order value trend.
     */
    private function getAverageOrderValueTrend($tenantId, $startDate, $endDate, $period): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        $data = [];
        $current = $start->copy()->startOfMonth();
        
        while ($current <= $end) {
            $monthEnd = $current->copy()->endOfMonth();
            
            $invoices = Invoice::where('tenant_id', $tenantId)
                ->whereBetween('invoice_date', [$current, $monthEnd]);
            
            $count = $invoices->count();
            $total = $invoices->sum('total');
            $avgValue = $count > 0 ? $total / $count : 0;
            
            $data[] = [
                'month' => $current->format('M Y'),
                'avg_value' => round($avgValue, 2),
                'invoice_count' => $count,
                'total_value' => (float) $total,
            ];
            
            $current->addMonth();
        }

        return $data;
    }

    /**
     * Get collection efficiency metrics.
     */
    private function getCollectionEfficiency($tenantId, $startDate, $endDate): array
    {
        // Days Sales Outstanding (DSO)
        $avgReceivables = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->avg('balance_due') ?? 0;
        
        $dailySales = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->sum('total');
        
        $daysDiff = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) ?: 1;
        $avgDailySales = $dailySales / $daysDiff;
        
        $dso = $avgDailySales > 0 ? round($avgReceivables / $avgDailySales, 1) : 0;

        // Collection by aging bucket
        $aging = [
            'current' => Invoice::where('tenant_id', $tenantId)
                ->whereIn('status', ['unpaid', 'partial'])
                ->where('due_date', '>=', now())
                ->sum('balance_due'),
            '1_30' => Invoice::where('tenant_id', $tenantId)
                ->whereIn('status', ['unpaid', 'partial'])
                ->where('due_date', '<', now())
                ->where('due_date', '>=', now()->subDays(30))
                ->sum('balance_due'),
            '31_60' => Invoice::where('tenant_id', $tenantId)
                ->whereIn('status', ['unpaid', 'partial'])
                ->where('due_date', '<', now()->subDays(30))
                ->where('due_date', '>=', now()->subDays(60))
                ->sum('balance_due'),
            '61_90' => Invoice::where('tenant_id', $tenantId)
                ->whereIn('status', ['unpaid', 'partial'])
                ->where('due_date', '<', now()->subDays(60))
                ->where('due_date', '>=', now()->subDays(90))
                ->sum('balance_due'),
            'over_90' => Invoice::where('tenant_id', $tenantId)
                ->whereIn('status', ['unpaid', 'partial'])
                ->where('due_date', '<', now()->subDays(90))
                ->sum('balance_due'),
        ];

        // Payment velocity - average days to payment
        $paidInvoices = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->where('status', 'paid')
            ->whereNotNull('updated_at')
            ->get();
        
        $avgDaysToPayment = 0;
        if ($paidInvoices->count() > 0) {
            $totalDays = $paidInvoices->sum(function ($invoice) {
                return $invoice->invoice_date->diffInDays($invoice->updated_at);
            });
            $avgDaysToPayment = round($totalDays / $paidInvoices->count(), 1);
        }

        return [
            'dso' => $dso,
            'avg_days_to_payment' => $avgDaysToPayment,
            'aging' => $aging,
            'total_outstanding' => array_sum($aging),
        ];
    }

    /**
     * Get sales funnel data.
     */
    private function getSalesFunnel($tenantId, $startDate, $endDate): array
    {
        // Sales Orders
        $salesOrders = SalesOrder::where('tenant_id', $tenantId)
            ->whereBetween('order_date', [$startDate, $endDate])
            ->count();
        
        $salesOrdersValue = SalesOrder::where('tenant_id', $tenantId)
            ->whereBetween('order_date', [$startDate, $endDate])
            ->sum('total');

        // Invoices created
        $invoicesCreated = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->count();
        
        $invoicesValue = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->sum('total');

        // Invoices sent
        $invoicesSent = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->whereIn('status', ['sent', 'unpaid', 'partial', 'paid', 'overdue'])
            ->count();

        // Invoices paid
        $invoicesPaid = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->where('status', 'paid')
            ->count();
        
        $paidValue = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->where('status', 'paid')
            ->sum('total');

        return [
            ['stage' => 'Sales Orders', 'count' => $salesOrders, 'value' => (float) $salesOrdersValue],
            ['stage' => 'Invoices Created', 'count' => $invoicesCreated, 'value' => (float) $invoicesValue],
            ['stage' => 'Invoices Sent', 'count' => $invoicesSent, 'value' => (float) $invoicesValue],
            ['stage' => 'Invoices Paid', 'count' => $invoicesPaid, 'value' => (float) $paidValue],
        ];
    }

    /**
     * Get overdue analysis.
     */
    private function getOverdueAnalysis($tenantId): array
    {
        $overdueInvoices = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', ['unpaid', 'partial'])
            ->where('due_date', '<', now())
            ->with('customer')
            ->orderBy('due_date')
            ->limit(20)
            ->get();

        return $overdueInvoices->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer' => $invoice->customer->name ?? 'N/A',
                'total' => (float) $invoice->total,
                'balance_due' => (float) $invoice->balance_due,
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'days_overdue' => $invoice->due_date->diffInDays(now()),
            ];
        })->toArray();
    }

    /**
     * Get customer retention metrics.
     */
    private function getCustomerRetention($tenantId, $startDate, $endDate): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        // Total active customers (made a purchase in the period)
        $activeCustomers = Customer::where('tenant_id', $tenantId)
            ->whereHas('invoices', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('invoice_date', [$startDate, $endDate]);
            })
            ->count();

        // Repeat customers (made more than one purchase ever)
        $repeatCustomers = Customer::where('tenant_id', $tenantId)
            ->whereHas('invoices', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('invoice_date', [$startDate, $endDate]);
            })
            ->withCount('invoices')
            ->get()
            ->filter(fn($c) => $c->invoices_count > 1)
            ->count();

        // Customer lifetime value (average)
        $avgLifetimeValue = Customer::where('tenant_id', $tenantId)
            ->withSum('invoices', 'total')
            ->get()
            ->avg('invoices_sum_total') ?? 0;

        // Churn - customers who haven't purchased in 90+ days but purchased before
        $churnedCustomers = Customer::where('tenant_id', $tenantId)
            ->whereHas('invoices', function ($q) {
                $q->where('invoice_date', '<', now()->subDays(90));
            })
            ->whereDoesntHave('invoices', function ($q) {
                $q->where('invoice_date', '>=', now()->subDays(90));
            })
            ->count();

        $totalCustomersWithPurchases = Customer::where('tenant_id', $tenantId)
            ->has('invoices')
            ->count();

        $retentionRate = $totalCustomersWithPurchases > 0 
            ? round((($totalCustomersWithPurchases - $churnedCustomers) / $totalCustomersWithPurchases) * 100, 1)
            : 0;

        return [
            'active_customers' => $activeCustomers,
            'repeat_customers' => $repeatCustomers,
            'repeat_rate' => $activeCustomers > 0 ? round(($repeatCustomers / $activeCustomers) * 100, 1) : 0,
            'avg_lifetime_value' => round($avgLifetimeValue, 2),
            'churned_customers' => $churnedCustomers,
            'retention_rate' => $retentionRate,
        ];
    }

    /**
     * Get daily sales heatmap data.
     */
    private function getDailySalesHeatmap($tenantId, $startDate, $endDate): array
    {
        $salesByDayHour = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->select(
                DB::raw('DAYOFWEEK(invoice_date) as day_of_week'),
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total) as total')
            )
            ->groupBy('day_of_week', 'hour')
            ->get();

        // Transform to heatmap format
        $heatmap = [];
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        
        foreach ($salesByDayHour as $sale) {
            $heatmap[] = [
                'day' => $days[$sale->day_of_week - 1] ?? 'Unknown',
                'hour' => sprintf('%02d:00', $sale->hour),
                'count' => $sale->count,
                'total' => (float) $sale->total,
            ];
        }

        return $heatmap;
    }

    /**
     * Calculate percentage change between two values.
     */
    private function calculatePercentageChange($previous, $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * API endpoint for chart data (for real-time updates).
     */
    public function chartData(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $type = $request->get('type', 'revenue');
        $period = $request->get('period', 'this_month');
        $dates = $this->getDateRange($period, $request);

        switch ($type) {
            case 'revenue':
                $data = $this->getRevenueTrends($tenantId, $dates['start'], $dates['end'], $period);
                break;
            case 'customers':
                $data = $this->getSalesByCustomer($tenantId, $dates['start'], $dates['end']);
                break;
            case 'items':
                $data = $this->getTopSellingItems($tenantId, $dates['start'], $dates['end']);
                break;
            case 'status':
                $data = $this->getInvoiceStatusDistribution($tenantId, $dates['start'], $dates['end']);
                break;
            default:
                $data = [];
        }

        return response()->json(['data' => $data]);
    }
}
