<?php

namespace App\Http\Controllers\Reports;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Receivables, sales by customer and item, with their exports. Customer
 * statements moved to StatementController (session 10).
 *
 * Split out of the old 3,400-line ReportController (finding L4). Route
 * names are unchanged.
 */
class SalesReportController extends ReportController
{
    public function accountsReceivable(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $asOf = $request->get('as_of', now()->format('Y-m-d'));

        // "Sent" invoices are unpaid too; they were missing (session 10). "Before
        // the next day" because SQLite keeps a time part on dates.
        $invoices = Invoice::where('tenant_id', $tenantId)
            ->where('invoice_date', '<', Carbon::parse($asOf)->addDay()->toDateString())
            ->whereIn('status', ['sent', 'unpaid', 'partial', 'overdue'])
            ->where('balance_due', '>', 0)
            ->with('customer')
            ->orderBy('due_date')
            ->get();

        // Group by aging based on as_of date for proper historical accuracy
        $asOfDate = Carbon::parse($asOf);
        $current = $invoices->filter(fn ($inv) => $inv->due_date >= $asOfDate)->sum('balance_due');
        $days30 = $invoices->filter(fn ($inv) => $inv->due_date < $asOfDate && $inv->due_date >= $asOfDate->copy()->subDays(30))->sum('balance_due');
        $days60 = $invoices->filter(fn ($inv) => $inv->due_date < $asOfDate->copy()->subDays(30) && $inv->due_date >= $asOfDate->copy()->subDays(60))->sum('balance_due');
        $days90 = $invoices->filter(fn ($inv) => $inv->due_date < $asOfDate->copy()->subDays(60) && $inv->due_date >= $asOfDate->copy()->subDays(90))->sum('balance_due');
        $days120 = $invoices->filter(fn ($inv) => $inv->due_date < $asOfDate->copy()->subDays(90) && $inv->due_date >= $asOfDate->copy()->subDays(120))->sum('balance_due');
        $over120 = $invoices->filter(fn ($inv) => $inv->due_date < $asOfDate->copy()->subDays(120))->sum('balance_due');

        $totalReceivable = $invoices->sum('balance_due');

        return view('reports.accounts-receivable', compact(
            'invoices', 'current', 'days30', 'days60', 'days90', 'days120', 'over120', 'totalReceivable', 'asOf'
        ));
    }

    public function salesByCustomer(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $customers = Customer::where('tenant_id', $tenantId)
            ->whereHas('invoices', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('invoice_date', [$startDate, $endDate]);
            })
            ->withCount(['invoices' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('invoice_date', [$startDate, $endDate]);
            }])
            ->withSum(['invoices' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('invoice_date', [$startDate, $endDate]);
            }], 'total')
            ->withSum(['invoices' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('invoice_date', [$startDate, $endDate]);
            }], 'amount_paid')
            ->orderByDesc('invoices_sum_total')
            ->get();

        $totalSales = $customers->sum('invoices_sum_total');
        $totalPaid = $customers->sum('invoices_sum_amount_paid');

        return view('reports.sales-by-customer', compact(
            'customers', 'totalSales', 'totalPaid', 'startDate', 'endDate'
        ));
    }

    public function salesByItem(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $items = Item::where('tenant_id', $tenantId)
            ->with(['invoiceItems' => function ($q) use ($tenantId, $startDate, $endDate) {
                $q->whereHas('invoice', function ($iq) use ($tenantId, $startDate, $endDate) {
                    $iq->where('tenant_id', $tenantId)
                        ->whereBetween('invoice_date', [$startDate, $endDate]);
                });
            }])
            ->get()
            ->map(function ($item) {
                $item->quantity_sold = $item->invoiceItems->sum('quantity');
                $item->total_sales = $item->invoiceItems->sum('total');

                return $item;
            })
            ->filter(fn ($item) => $item->quantity_sold > 0)
            ->sortByDesc('total_sales')
            ->values();

        $totalQuantity = $items->sum('quantity_sold');
        $totalSales = $items->sum('total_sales');

        return view('reports.sales-by-item', compact(
            'items', 'totalQuantity', 'totalSales', 'startDate', 'endDate'
        ));
    }

    /**
     * Export Accounts Receivable Report
     */
    public function exportAccountsReceivable(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $asOf = $request->get('as_of', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        // "Sent" invoices are unpaid too; they were missing (session 10). "Before
        // the next day" because SQLite keeps a time part on dates.
        $invoices = Invoice::where('tenant_id', $tenantId)
            ->where('invoice_date', '<', Carbon::parse($asOf)->addDay()->toDateString())
            ->whereIn('status', ['sent', 'unpaid', 'partial', 'overdue'])
            ->where('balance_due', '>', 0)
            ->with('customer')
            ->orderBy('due_date')
            ->get();

        $asOfDate = Carbon::parse($asOf);
        $current = $invoices->filter(fn ($inv) => $inv->due_date >= $asOfDate)->sum('balance_due');
        $days30 = $invoices->filter(fn ($inv) => $inv->due_date < $asOfDate && $inv->due_date >= $asOfDate->copy()->subDays(30))->sum('balance_due');
        $days60 = $invoices->filter(fn ($inv) => $inv->due_date < $asOfDate->copy()->subDays(30) && $inv->due_date >= $asOfDate->copy()->subDays(60))->sum('balance_due');
        $days90 = $invoices->filter(fn ($inv) => $inv->due_date < $asOfDate->copy()->subDays(60) && $inv->due_date >= $asOfDate->copy()->subDays(90))->sum('balance_due');
        $days120 = $invoices->filter(fn ($inv) => $inv->due_date < $asOfDate->copy()->subDays(90) && $inv->due_date >= $asOfDate->copy()->subDays(120))->sum('balance_due');
        $over120 = $invoices->filter(fn ($inv) => $inv->due_date < $asOfDate->copy()->subDays(120))->sum('balance_due');
        $totalReceivable = $invoices->sum('balance_due');

        $data = compact('invoices', 'current', 'days30', 'days60', 'days90', 'days120', 'over120', 'totalReceivable', 'asOf');

        if ($format === 'csv') {
            $exportData = $this->exportService->accountsReceivableData($invoices, [
                'current' => $current,
                'days30' => $days30,
                'days60' => $days60,
                'days90' => $days90,
                'days120' => $days120,
                'over120' => $over120,
                'total' => $totalReceivable,
            ]);

            return $this->exportService
                ->setTitle('Accounts Receivable Aging')
                ->setFilters(['As of' => $asOf])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Accounts Receivable Aging')
            ->setFilters(['As of' => $asOf])
            ->exportToPdf('reports.pdf.accounts-receivable', $data);
    }

    /**
     * Export Sales by Customer Report
     */
    public function exportSalesByCustomer(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        $customers = Customer::where('tenant_id', $tenantId)
            ->whereHas('invoices', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('invoice_date', [$startDate, $endDate]);
            })
            ->withCount(['invoices' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('invoice_date', [$startDate, $endDate]);
            }])
            ->withSum(['invoices' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('invoice_date', [$startDate, $endDate]);
            }], 'total')
            ->withSum(['invoices' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('invoice_date', [$startDate, $endDate]);
            }], 'amount_paid')
            ->orderByDesc('invoices_sum_total')
            ->get();

        $totalSales = $customers->sum('invoices_sum_total');
        $totalPaid = $customers->sum('invoices_sum_amount_paid');

        $data = compact('customers', 'totalSales', 'totalPaid', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->salesByCustomerData($customers);

            return $this->exportService
                ->setTitle('Sales by Customer')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Sales by Customer')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.sales-by-customer', $data);
    }

    /**
     * Export Sales by Item Report
     */
    public function exportSalesByItem(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        $items = Item::where('tenant_id', $tenantId)
            ->with(['invoiceItems' => function ($q) use ($tenantId, $startDate, $endDate) {
                $q->whereHas('invoice', function ($iq) use ($tenantId, $startDate, $endDate) {
                    $iq->where('tenant_id', $tenantId)
                        ->whereBetween('invoice_date', [$startDate, $endDate]);
                });
            }])
            ->get()
            ->map(function ($item) {
                $item->quantity_sold = $item->invoiceItems->sum('quantity');
                $item->total_sales = $item->invoiceItems->sum('total');

                return $item;
            })
            ->filter(fn ($item) => $item->quantity_sold > 0)
            ->sortByDesc('total_sales')
            ->values();

        $totalQuantity = $items->sum('quantity_sold');
        $totalSales = $items->sum('total_sales');

        $data = compact('items', 'totalQuantity', 'totalSales', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->salesByItemData($items);

            return $this->exportService
                ->setTitle('Sales by Item')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Sales by Item')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.sales-by-item', $data);
    }
}
