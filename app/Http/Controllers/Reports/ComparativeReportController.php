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
 * Period-on-period profit and loss, balance sheet and cash flow.
 *
 * Split out of the old 3,400-line ReportController (finding L4). Route
 * names are unchanged.
 */
class ComparativeReportController extends ReportController
{
    /**
     * Comparative Profit & Loss Report
     */
    public function comparativeProfitLoss(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $comparisonType = $request->get('comparison_type', 'month');
        
        // Determine periods based on comparison type
        $periods = $this->getComparisonPeriods($comparisonType, $request);
        
        $periodData = [];
        
        foreach ($periods as $key => $period) {
            // Get P&L data using accrual-based accounting from journal entries
            $plData = $this->calculateProfitLossFromJournals($tenantId, $period['start'], $period['end']);

            $revenue = $plData['revenue'];
            $costOfGoodsSold = $plData['costOfGoodsSold'];
            $grossProfit = $revenue - $costOfGoodsSold;
            $operatingExpenses = $plData['operatingExpenses'];
            $payroll = $plData['payrollExpenses'];
            $totalExpenses = $plData['totalExpenses'];
            $netProfit = $plData['netProfit'];

            $periodData[$key] = [
                'label' => $period['label'],
                'start' => $period['start'],
                'end' => $period['end'],
                'revenue' => $revenue,
                'costOfGoodsSold' => $costOfGoodsSold,
                'grossProfit' => $grossProfit,
                'expenses' => $operatingExpenses,
                'operatingExpenses' => $operatingExpenses,
                'billsPaid' => $costOfGoodsSold,
                'payroll' => $payroll,
                'totalExpenses' => $totalExpenses,
                'netProfit' => $netProfit,
                'grossMargin' => $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0,
                'profitMargin' => $revenue > 0 ? ($netProfit / $revenue) * 100 : 0,
            ];
        }

        // Calculate changes between periods
        $changes = $this->calculatePeriodChanges($periodData);

        return view('reports.comparative-profit-loss', compact(
            'periodData', 'changes', 'comparisonType'
        ));
    }

    /**
     * Comparative Balance Sheet Report
     * Uses double-entry accounting from journal entries for accurate historical balances
     */
    public function comparativeBalanceSheet(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $comparisonType = $request->get('comparison_type', 'month');
        
        $periods = $this->getComparisonPeriods($comparisonType, $request);
        
        $periodData = [];
        
        foreach ($periods as $key => $period) {
            $asOf = $period['end'];
            
            // Calculate Total Assets from journal entries (Debit - Credit for asset accounts)
            $assetData = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $asOf) {
                    $query->where('tenant_id', $tenantId)
                        ->where('journal_date', '<=', $asOf)
                        ->where('is_posted', true);
                })
                ->whereHas('account', function ($query) {
                    $query->where('type', 'asset');
                })
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();
            
            $totalAssets = ($assetData->total_debit ?? 0) - ($assetData->total_credit ?? 0);
            
            // Get Accounts Receivable specifically
            $arData = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $asOf) {
                    $query->where('tenant_id', $tenantId)
                        ->where('journal_date', '<=', $asOf)
                        ->where('is_posted', true);
                })
                ->whereHas('account', function ($query) {
                    $query->where('type', 'asset')
                        ->where('sub_type', 'accounts_receivable');
                })
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();
            
            $accountsReceivable = ($arData->total_debit ?? 0) - ($arData->total_credit ?? 0);

            // Calculate Total Liabilities from journal entries (Credit - Debit for liability accounts)
            $liabilityData = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $asOf) {
                    $query->where('tenant_id', $tenantId)
                        ->where('journal_date', '<=', $asOf)
                        ->where('is_posted', true);
                })
                ->whereHas('account', function ($query) {
                    $query->where('type', 'liability');
                })
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();
            
            $totalLiabilities = ($liabilityData->total_credit ?? 0) - ($liabilityData->total_debit ?? 0);
            
            // Get Accounts Payable specifically
            $apData = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $asOf) {
                    $query->where('tenant_id', $tenantId)
                        ->where('journal_date', '<=', $asOf)
                        ->where('is_posted', true);
                })
                ->whereHas('account', function ($query) {
                    $query->where('type', 'liability')
                        ->where('sub_type', 'accounts_payable');
                })
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();

            $accountsPayable = ($apData->total_credit ?? 0) - ($apData->total_debit ?? 0);

            // Calculate Total Equity from journal entries (Credit - Debit for equity accounts)
            $equityData = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $asOf) {
                    $query->where('tenant_id', $tenantId)
                        ->where('journal_date', '<=', $asOf)
                        ->where('is_posted', true);
                })
                ->whereHas('account', function ($query) {
                    $query->where('type', 'equity');
                })
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();
            
            $equityFromAccounts = ($equityData->total_credit ?? 0) - ($equityData->total_debit ?? 0);
            
            // Add retained earnings (net income from beginning of fiscal year to as-of date)
            $netIncome = $this->calculateNetIncomeForBalanceSheet($tenantId, $asOf);
            
            // Total equity = Equity accounts + Net Income (retained earnings for the period)
            $equity = $equityFromAccounts + $netIncome;

            $periodData[$key] = [
                'label' => $period['label'],
                'asOf' => $asOf,
                'accountsReceivable' => $accountsReceivable,
                'totalAssets' => $totalAssets,
                'accountsPayable' => $accountsPayable,
                'totalLiabilities' => $totalLiabilities,
                'equity' => $equity,
                'totalLiabilitiesEquity' => $totalLiabilities + $equity,
            ];
        }

        $changes = $this->calculatePeriodChanges($periodData);

        return view('reports.comparative-balance-sheet', compact(
            'periodData', 'changes', 'comparisonType'
        ));
    }

    /**
     * Comparative Cash Flow Report
     */
    public function comparativeCashFlow(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $comparisonType = $request->get('comparison_type', 'month');
        
        $periods = $this->getComparisonPeriods($comparisonType, $request);
        
        $periodData = [];
        
        foreach ($periods as $key => $period) {
            $paymentsReceived = PaymentReceived::where('tenant_id', $tenantId)
                ->whereBetween('payment_date', [$period['start'], $period['end']])
                ->sum('amount');

            $paymentsMade = PaymentMade::where('tenant_id', $tenantId)
                ->whereBetween('payment_date', [$period['start'], $period['end']])
                ->sum('amount');

            $expensesPaid = Expense::where('tenant_id', $tenantId)
                ->whereBetween('expense_date', [$period['start'], $period['end']])
                ->sum('amount');

            $payrollPaid = Payroll::where('tenant_id', $tenantId)
                ->whereBetween('pay_date', [$period['start'], $period['end']])
                ->where('status', 'paid')
                ->sum('net_salary');

            $totalInflows = $paymentsReceived;
            $totalOutflows = $paymentsMade + $expensesPaid + $payrollPaid;
            $netCashFlow = $totalInflows - $totalOutflows;

            $periodData[$key] = [
                'label' => $period['label'],
                'start' => $period['start'],
                'end' => $period['end'],
                'paymentsReceived' => $paymentsReceived,
                'paymentsMade' => $paymentsMade,
                'expensesPaid' => $expensesPaid,
                'payrollPaid' => $payrollPaid,
                'totalInflows' => $totalInflows,
                'totalOutflows' => $totalOutflows,
                'netCashFlow' => $netCashFlow,
            ];
        }

        $changes = $this->calculatePeriodChanges($periodData);

        return view('reports.comparative-cash-flow', compact(
            'periodData', 'changes', 'comparisonType'
        ));
    }

    /**
     * Get comparison periods based on type
     */
    protected function getComparisonPeriods(string $type, Request $request): array
    {
        $periods = [];
        $now = now();

        switch ($type) {
            case 'month':
                // Current month vs previous month
                $periods['current'] = [
                    'label' => $now->format('F Y'),
                    'start' => $now->copy()->startOfMonth()->format('Y-m-d'),
                    'end' => $now->copy()->endOfMonth()->format('Y-m-d'),
                ];
                $periods['previous'] = [
                    'label' => $now->copy()->subMonth()->format('F Y'),
                    'start' => $now->copy()->subMonth()->startOfMonth()->format('Y-m-d'),
                    'end' => $now->copy()->subMonth()->endOfMonth()->format('Y-m-d'),
                ];
                break;

            case 'quarter':
                // Current quarter vs previous quarter
                $periods['current'] = [
                    'label' => 'Q' . $now->quarter . ' ' . $now->year,
                    'start' => $now->copy()->firstOfQuarter()->format('Y-m-d'),
                    'end' => $now->copy()->lastOfQuarter()->format('Y-m-d'),
                ];
                $prevQuarter = $now->copy()->subQuarter();
                $periods['previous'] = [
                    'label' => 'Q' . $prevQuarter->quarter . ' ' . $prevQuarter->year,
                    'start' => $prevQuarter->copy()->firstOfQuarter()->format('Y-m-d'),
                    'end' => $prevQuarter->copy()->lastOfQuarter()->format('Y-m-d'),
                ];
                break;

            case 'year':
                // Current year vs previous year
                $periods['current'] = [
                    'label' => $now->year,
                    'start' => $now->copy()->startOfYear()->format('Y-m-d'),
                    'end' => $now->copy()->endOfYear()->format('Y-m-d'),
                ];
                $periods['previous'] = [
                    'label' => $now->year - 1,
                    'start' => $now->copy()->subYear()->startOfYear()->format('Y-m-d'),
                    'end' => $now->copy()->subYear()->endOfYear()->format('Y-m-d'),
                ];
                break;

            case 'ytd':
                // Year to date vs same period last year
                $periods['current'] = [
                    'label' => 'YTD ' . $now->year,
                    'start' => $now->copy()->startOfYear()->format('Y-m-d'),
                    'end' => $now->format('Y-m-d'),
                ];
                $periods['previous'] = [
                    'label' => 'YTD ' . ($now->year - 1),
                    'start' => $now->copy()->subYear()->startOfYear()->format('Y-m-d'),
                    'end' => $now->copy()->subYear()->format('Y-m-d'),
                ];
                break;

            case 'custom':
                // Custom date ranges
                $periods['current'] = [
                    'label' => 'Current Period',
                    'start' => $request->get('current_start', $now->copy()->startOfMonth()->format('Y-m-d')),
                    'end' => $request->get('current_end', $now->format('Y-m-d')),
                ];
                $periods['previous'] = [
                    'label' => 'Previous Period',
                    'start' => $request->get('previous_start', $now->copy()->subMonth()->startOfMonth()->format('Y-m-d')),
                    'end' => $request->get('previous_end', $now->copy()->subMonth()->endOfMonth()->format('Y-m-d')),
                ];
                break;

            default:
                // Default to month comparison
                return $this->getComparisonPeriods('month', $request);
        }

        return $periods;
    }

    /**
     * Calculate changes between periods
     */
    protected function calculatePeriodChanges(array $periodData): array
    {
        $changes = [];
        
        if (!isset($periodData['current']) || !isset($periodData['previous'])) {
            return $changes;
        }

        $current = $periodData['current'];
        $previous = $periodData['previous'];

        foreach ($current as $key => $value) {
            if (in_array($key, ['label', 'start', 'end', 'asOf'])) {
                continue;
            }

            if (is_numeric($value) && isset($previous[$key]) && is_numeric($previous[$key])) {
                $diff = $value - $previous[$key];
                $percentChange = $previous[$key] != 0 ? (($value - $previous[$key]) / abs($previous[$key])) * 100 : ($value != 0 ? 100 : 0);
                
                $changes[$key] = [
                    'current' => $value,
                    'previous' => $previous[$key],
                    'difference' => $diff,
                    'percentChange' => $percentChange,
                    'improved' => $this->isImprovement($key, $diff),
                ];
            }
        }

        return $changes;
    }

    /**
     * Determine if a change is an improvement
     */
    protected function isImprovement(string $metric, float $diff): bool
    {
        // For revenue/profit metrics, positive change is good
        $positiveMetrics = ['revenue', 'netProfit', 'profitMargin', 'paymentsReceived', 'totalInflows', 'netCashFlow', 'accountsReceivable', 'totalAssets', 'equity'];
        
        // For expense metrics, negative change is good
        $negativeMetrics = ['expenses', 'billsPaid', 'payroll', 'totalExpenses', 'paymentsMade', 'expensesPaid', 'payrollPaid', 'totalOutflows', 'accountsPayable'];
        
        if (in_array($metric, $positiveMetrics)) {
            return $diff >= 0;
        }
        
        if (in_array($metric, $negativeMetrics)) {
            return $diff <= 0;
        }
        
        return true;
    }
}
