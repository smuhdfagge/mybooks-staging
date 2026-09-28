<?php

namespace App\Http\Controllers\Reports;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * VAT/GST return and tax liability.
 *
 * Split out of the old 3,400-line ReportController (finding L4). Route
 * names are unchanged.
 */
class TaxReportController extends ReportController
{
    /**
     * VAT/GST Return Report
     */
    public function vatGstReturn(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfQuarter()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->endOfQuarter()->format('Y-m-d'));
        $taxRateId = $request->get('tax_rate_id');

        // Get all active tax rates for filter dropdown
        $taxRates = TaxRate::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Output Tax (Sales) - Tax collected on sales
        $outputTaxQuery = InvoiceItem::select(
            'invoice_items.tax_rate',
            DB::raw('SUM(invoice_items.quantity * invoice_items.unit_price) as taxable_amount'),
            DB::raw('SUM(invoice_items.tax_amount) as tax_amount'),
            DB::raw('COUNT(DISTINCT invoice_items.invoice_id) as transaction_count')
        )
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.tenant_id', $tenantId)
            ->whereBetween('invoices.invoice_date', [$startDate, $endDate])
            ->whereIn('invoices.status', ['sent', 'paid', 'partial', 'overdue'])
            ->where('invoice_items.tax_rate', '>', 0);

        if ($taxRateId) {
            $selectedTaxRate = TaxRate::where('tenant_id', $tenantId)->find($taxRateId);
            if ($selectedTaxRate) {
                $outputTaxQuery->where('invoice_items.tax_rate', $selectedTaxRate->rate);
            }
        }

        $outputTaxByRate = $outputTaxQuery->groupBy('invoice_items.tax_rate')
            ->orderBy('invoice_items.tax_rate')
            ->get();

        // Input Tax (Purchases) - Tax paid on purchases
        $inputTaxQuery = BillItem::select(
            'bill_items.tax_rate',
            DB::raw('SUM(bill_items.quantity * bill_items.unit_price) as taxable_amount'),
            DB::raw('SUM(bill_items.tax_amount) as tax_amount'),
            DB::raw('COUNT(DISTINCT bill_items.bill_id) as transaction_count')
        )
            ->join('bills', 'bill_items.bill_id', '=', 'bills.id')
            ->where('bills.tenant_id', $tenantId)
            ->whereBetween('bills.bill_date', [$startDate, $endDate])
            ->whereIn('bills.status', ['approved', 'paid', 'partial', 'overdue'])
            ->where('bill_items.tax_rate', '>', 0);

        if ($taxRateId) {
            $selectedTaxRate = TaxRate::where('tenant_id', $tenantId)->find($taxRateId);
            if ($selectedTaxRate) {
                $inputTaxQuery->where('bill_items.tax_rate', $selectedTaxRate->rate);
            }
        }

        $inputTaxByRate = $inputTaxQuery->groupBy('bill_items.tax_rate')
            ->orderBy('bill_items.tax_rate')
            ->get();

        // Calculate totals
        $totalOutputTax = $outputTaxByRate->sum('tax_amount');
        $totalOutputTaxable = $outputTaxByRate->sum('taxable_amount');
        $totalInputTax = $inputTaxByRate->sum('tax_amount');
        $totalInputTaxable = $inputTaxByRate->sum('taxable_amount');

        // Net VAT/GST payable (or refundable if negative)
        $netTaxPayable = $totalOutputTax - $totalInputTax;

        // Get detailed transactions for Output Tax
        $outputTransactions = Invoice::with(['customer', 'items'])
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->whereIn('status', ['sent', 'paid', 'partial', 'overdue'])
            ->where('tax_amount', '>', 0)
            ->orderBy('invoice_date', 'desc')
            ->get();

        // Get detailed transactions for Input Tax
        $inputTransactions = Bill::with(['vendor', 'items'])
            ->where('tenant_id', $tenantId)
            ->whereBetween('bill_date', [$startDate, $endDate])
            ->whereIn('status', ['approved', 'paid', 'partial', 'overdue'])
            ->where('tax_amount', '>', 0)
            ->orderBy('bill_date', 'desc')
            ->get();

        return view('reports.vat-gst-return', compact(
            'startDate', 'endDate', 'taxRates', 'taxRateId',
            'outputTaxByRate', 'inputTaxByRate',
            'totalOutputTax', 'totalOutputTaxable',
            'totalInputTax', 'totalInputTaxable',
            'netTaxPayable',
            'outputTransactions', 'inputTransactions'
        ));
    }

    /**
     * Tax Liability Report
     */
    public function taxLiability(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $groupBy = $request->get('group_by', 'month'); // month, quarter, tax_rate

        // Allowlist to prevent unexpected input flowing into SQL helpers
        if (! in_array($groupBy, ['month', 'quarter', 'year'], true)) {
            $groupBy = 'month';
        }

        // Get all tax rates for reference
        $taxRates = TaxRate::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Tax Collected (Sales Tax / Output VAT)
        $taxCollectedQuery = Invoice::select(
            DB::raw($this->getDateGrouping('invoices.invoice_date', $groupBy).' as period'),
            DB::raw('SUM(invoices.tax_amount) as tax_amount'),
            DB::raw('SUM(invoices.subtotal) as taxable_sales'),
            DB::raw('COUNT(*) as invoice_count')
        )
            ->where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->whereIn('status', ['sent', 'paid', 'partial', 'overdue'])
            ->groupBy('period')
            ->orderBy('period');

        $taxCollected = $taxCollectedQuery->get();

        // Tax Paid (Input VAT / Purchase Tax)
        $taxPaidQuery = Bill::select(
            DB::raw($this->getDateGrouping('bills.bill_date', $groupBy).' as period'),
            DB::raw('SUM(bills.tax_amount) as tax_amount'),
            DB::raw('SUM(bills.subtotal) as taxable_purchases'),
            DB::raw('COUNT(*) as bill_count')
        )
            ->where('tenant_id', $tenantId)
            ->whereBetween('bill_date', [$startDate, $endDate])
            ->whereIn('status', ['approved', 'paid', 'partial', 'overdue'])
            ->groupBy('period')
            ->orderBy('period');

        $taxPaid = $taxPaidQuery->get();

        // Tax by Rate breakdown
        $taxByRateOutput = InvoiceItem::select(
            'invoice_items.tax_rate',
            DB::raw('SUM(invoice_items.tax_amount) as tax_amount'),
            DB::raw('SUM(invoice_items.quantity * invoice_items.unit_price) as taxable_amount')
        )
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.tenant_id', $tenantId)
            ->whereBetween('invoices.invoice_date', [$startDate, $endDate])
            ->whereIn('invoices.status', ['sent', 'paid', 'partial', 'overdue'])
            ->where('invoice_items.tax_rate', '>', 0)
            ->groupBy('invoice_items.tax_rate')
            ->orderBy('invoice_items.tax_rate')
            ->get();

        $taxByRateInput = BillItem::select(
            'bill_items.tax_rate',
            DB::raw('SUM(bill_items.tax_amount) as tax_amount'),
            DB::raw('SUM(bill_items.quantity * bill_items.unit_price) as taxable_amount')
        )
            ->join('bills', 'bill_items.bill_id', '=', 'bills.id')
            ->where('bills.tenant_id', $tenantId)
            ->whereBetween('bills.bill_date', [$startDate, $endDate])
            ->whereIn('bills.status', ['approved', 'paid', 'partial', 'overdue'])
            ->where('bill_items.tax_rate', '>', 0)
            ->groupBy('bill_items.tax_rate')
            ->orderBy('bill_items.tax_rate')
            ->get();

        // Combine periods for liability calculation
        $periods = collect();
        $allPeriods = $taxCollected->pluck('period')->merge($taxPaid->pluck('period'))->unique()->sort();

        foreach ($allPeriods as $period) {
            $collected = $taxCollected->firstWhere('period', $period);
            $paid = $taxPaid->firstWhere('period', $period);

            $periods->push([
                'period' => $period,
                'period_label' => $this->formatPeriodLabel($period, $groupBy),
                'tax_collected' => $collected ? $collected->tax_amount : 0,
                'taxable_sales' => $collected ? $collected->taxable_sales : 0,
                'invoice_count' => $collected ? $collected->invoice_count : 0,
                'tax_paid' => $paid ? $paid->tax_amount : 0,
                'taxable_purchases' => $paid ? $paid->taxable_purchases : 0,
                'bill_count' => $paid ? $paid->bill_count : 0,
                'net_liability' => ($collected ? $collected->tax_amount : 0) - ($paid ? $paid->tax_amount : 0),
            ]);
        }

        // Calculate totals
        $totalTaxCollected = $taxCollected->sum('tax_amount');
        $totalTaxableSales = $taxCollected->sum('taxable_sales');
        $totalTaxPaid = $taxPaid->sum('tax_amount');
        $totalTaxablePurchases = $taxPaid->sum('taxable_purchases');
        $totalNetLiability = $totalTaxCollected - $totalTaxPaid;

        // Cumulative liability tracking
        $cumulativeLiability = 0;
        $periodsWithCumulative = $periods->map(function ($period) use (&$cumulativeLiability) {
            $cumulativeLiability += $period['net_liability'];
            $period['cumulative_liability'] = $cumulativeLiability;

            return $period;
        });

        return view('reports.tax-liability', compact(
            'startDate', 'endDate', 'groupBy', 'taxRates',
            'periodsWithCumulative', 'taxByRateOutput', 'taxByRateInput',
            'totalTaxCollected', 'totalTaxableSales',
            'totalTaxPaid', 'totalTaxablePurchases',
            'totalNetLiability'
        ));
    }

    /**
     * Get SQL date grouping based on group_by parameter
     * Supports both MySQL and SQLite
     */
    protected function getDateGrouping(string $column, string $groupBy): string
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            switch ($groupBy) {
                case 'quarter':
                    return "strftime('%Y', {$column}) || '-Q' || ((CAST(strftime('%m', {$column}) AS INTEGER) + 2) / 3)";
                case 'year':
                    return "strftime('%Y', {$column})";
                case 'month':
                default:
                    return "strftime('%Y-%m', {$column})";
            }
        }

        // MySQL
        switch ($groupBy) {
            case 'quarter':
                return "CONCAT(YEAR({$column}), '-Q', QUARTER({$column}))";
            case 'year':
                return "YEAR({$column})";
            case 'month':
            default:
                return "DATE_FORMAT({$column}, '%Y-%m')";
        }
    }

    /**
     * Format period label for display
     */
    protected function formatPeriodLabel(string $period, string $groupBy): string
    {
        switch ($groupBy) {
            case 'quarter':
                return $period; // Already formatted as "2024-Q1"
            case 'year':
                return $period;
            case 'month':
            default:
                try {
                    return \Carbon\Carbon::createFromFormat('Y-m', $period)->format('F Y');
                } catch (\Exception $e) {
                    return $period;
                }
        }
    }
}
