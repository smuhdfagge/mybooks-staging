<?php

namespace App\Http\Controllers\Reports;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\ChartOfAccount;
use App\Models\CustomReport;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\Payroll;
use App\Models\PayrollBatch;
use App\Models\SalaryStructureVersion;
use App\Models\TaxRate;
use App\Models\Vendor;
use App\Services\ReportExportService;
use App\Services\Reports\PayrollReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Receivables, sales by customer and item, customer statements, with their exports.
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

        $invoices = Invoice::where('tenant_id', $tenantId)
            ->where('invoice_date', '<=', $asOf)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->where('balance_due', '>', 0)
            ->with('customer')
            ->orderBy('due_date')
            ->get();

        // Group by aging based on as_of date for proper historical accuracy
        $asOfDate = \Carbon\Carbon::parse($asOf);
        $current = $invoices->filter(fn($inv) => $inv->due_date >= $asOfDate)->sum('balance_due');
        $days30 = $invoices->filter(fn($inv) => $inv->due_date < $asOfDate && $inv->due_date >= $asOfDate->copy()->subDays(30))->sum('balance_due');
        $days60 = $invoices->filter(fn($inv) => $inv->due_date < $asOfDate->copy()->subDays(30) && $inv->due_date >= $asOfDate->copy()->subDays(60))->sum('balance_due');
        $days90 = $invoices->filter(fn($inv) => $inv->due_date < $asOfDate->copy()->subDays(60) && $inv->due_date >= $asOfDate->copy()->subDays(90))->sum('balance_due');
        $days120 = $invoices->filter(fn($inv) => $inv->due_date < $asOfDate->copy()->subDays(90) && $inv->due_date >= $asOfDate->copy()->subDays(120))->sum('balance_due');
        $over120 = $invoices->filter(fn($inv) => $inv->due_date < $asOfDate->copy()->subDays(120))->sum('balance_due');

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
            ->filter(fn($item) => $item->quantity_sold > 0)
            ->sortByDesc('total_sales')
            ->values();

        $totalQuantity = $items->sum('quantity_sold');
        $totalSales = $items->sum('total_sales');

        return view('reports.sales-by-item', compact(
            'items', 'totalQuantity', 'totalSales', 'startDate', 'endDate'
        ));
    }

    public function customerStatement(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $customerId = $request->get('customer_id');
        $startDate = $request->get('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        // Get all customers for dropdown
        $customers = Customer::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        if (!$customerId) {
            return view('reports.customer-statement', compact('customers', 'startDate', 'endDate'));
        }

        $customer = Customer::where('tenant_id', $tenantId)
            ->where('id', $customerId)
            ->firstOrFail();

        // Get opening balance (invoices before start date)
        $openingBalance = Invoice::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['sent', 'unpaid', 'partial', 'overdue'])
            ->where('invoice_date', '<', $startDate)
            ->sum('balance_due');

        // Get all transactions within the date range
        $invoices = Invoice::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        $payments = PaymentReceived::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->with('invoice')
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        // Combine and sort transactions
        $transactions = collect();
        
        foreach ($invoices as $invoice) {
            $transactions->push([
                'date' => $invoice->invoice_date,
                'type' => 'invoice',
                'reference' => $invoice->invoice_number,
                'description' => 'Invoice #' . $invoice->invoice_number,
                'debit' => $invoice->total,
                'credit' => 0,
                'status' => $invoice->status,
                'due_date' => $invoice->due_date,
                'model' => $invoice,
            ]);
        }

        foreach ($payments as $payment) {
            $transactions->push([
                'date' => $payment->payment_date,
                'type' => 'payment',
                'reference' => $payment->payment_number,
                'description' => 'Payment #' . $payment->payment_number . ($payment->invoice ? ' for Invoice #' . $payment->invoice->invoice_number : ''),
                'debit' => 0,
                'credit' => $payment->amount,
                'status' => 'paid',
                'due_date' => null,
                'model' => $payment,
            ]);
        }

        // Sort by date
        $transactions = $transactions->sortBy('date')->values();

        // Calculate running balance
        $runningBalance = $openingBalance;
        $transactions = $transactions->map(function ($transaction) use (&$runningBalance) {
            $runningBalance += $transaction['debit'] - $transaction['credit'];
            $transaction['balance'] = $runningBalance;
            return $transaction;
        });

        // Calculate totals
        $totalInvoices = $transactions->where('type', 'invoice')->sum('debit');
        $totalPayments = $transactions->where('type', 'payment')->sum('credit');
        $closingBalance = $openingBalance + $totalInvoices - $totalPayments;

        // Get current outstanding balance
        $currentBalance = Invoice::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['sent', 'unpaid', 'partial', 'overdue'])
            ->sum('balance_due');

        return view('reports.customer-statement', compact(
            'customers',
            'customer',
            'transactions',
            'openingBalance',
            'totalInvoices',
            'totalPayments',
            'closingBalance',
            'currentBalance',
            'startDate',
            'endDate'
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

        $invoices = Invoice::where('tenant_id', $tenantId)
            ->where('invoice_date', '<=', $asOf)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->where('balance_due', '>', 0)
            ->with('customer')
            ->orderBy('due_date')
            ->get();

        $asOfDate = \Carbon\Carbon::parse($asOf);
        $current = $invoices->filter(fn($inv) => $inv->due_date >= $asOfDate)->sum('balance_due');
        $days30 = $invoices->filter(fn($inv) => $inv->due_date < $asOfDate && $inv->due_date >= $asOfDate->copy()->subDays(30))->sum('balance_due');
        $days60 = $invoices->filter(fn($inv) => $inv->due_date < $asOfDate->copy()->subDays(30) && $inv->due_date >= $asOfDate->copy()->subDays(60))->sum('balance_due');
        $days90 = $invoices->filter(fn($inv) => $inv->due_date < $asOfDate->copy()->subDays(60) && $inv->due_date >= $asOfDate->copy()->subDays(90))->sum('balance_due');
        $days120 = $invoices->filter(fn($inv) => $inv->due_date < $asOfDate->copy()->subDays(90) && $inv->due_date >= $asOfDate->copy()->subDays(120))->sum('balance_due');
        $over120 = $invoices->filter(fn($inv) => $inv->due_date < $asOfDate->copy()->subDays(120))->sum('balance_due');
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
            ->filter(fn($item) => $item->quantity_sold > 0)
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
