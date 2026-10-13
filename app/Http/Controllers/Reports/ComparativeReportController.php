<?php

namespace App\Http\Controllers\Reports;

use App\Models\Journal;
use App\Services\Accounting\FinancialStatements;
use Carbon\Carbon;
use Illuminate\Http\Request;

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

            // Same ledger-based figures as the balance sheet (A2).
            $bs = app(FinancialStatements::class)->balanceSheet($tenantId, $asOf);
            $equity = $bs['totalEquity'];

            $periodData[$key] = [
                'label' => $period['label'],
                'asOf' => $asOf,
                'accountsReceivable' => $bs['accountsReceivable'],
                'totalAssets' => $bs['totalAssets'],
                'accountsPayable' => $bs['accountsPayable'],
                'totalLiabilities' => $bs['totalLiabilities'],
                'equity' => $equity,
                'totalLiabilitiesEquity' => $bs['totalLiabilitiesAndEquity'],
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
            // Same ledger-based figures as the cash flow statement (A7).
            $cf = app(FinancialStatements::class)->cashFlow($tenantId, $period['start'], $period['end']);

            $periodData[$key] = [
                'label' => $period['label'],
                'start' => $period['start'],
                'end' => $period['end'],
                'paymentsReceived' => $cf['paymentsReceived'],
                'paymentsMade' => $cf['paymentsMade'],
                'expensesPaid' => $cf['expensesPaid'],
                'payrollPaid' => $cf['payrollPaid'],
                'totalInflows' => $cf['totalInflows'],
                'totalOutflows' => $cf['totalOutflows'],
                'netCashFlow' => $cf['netCashFlow'],
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
                    'label' => $now->copy()->subMonthNoOverflow()->format('F Y'),
                    'start' => $now->copy()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'),
                    'end' => $now->copy()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d'),
                ];
                break;

            case 'quarter':
                // Current quarter vs previous quarter
                $periods['current'] = [
                    'label' => 'Q'.$now->quarter.' '.$now->year,
                    'start' => $now->copy()->firstOfQuarter()->format('Y-m-d'),
                    'end' => $now->copy()->lastOfQuarter()->format('Y-m-d'),
                ];
                $prevQuarter = $now->copy()->subQuarterNoOverflow();
                $periods['previous'] = [
                    'label' => 'Q'.$prevQuarter->quarter.' '.$prevQuarter->year,
                    'start' => $prevQuarter->copy()->firstOfQuarter()->format('Y-m-d'),
                    'end' => $prevQuarter->copy()->lastOfQuarter()->format('Y-m-d'),
                ];
                break;

            case 'year':
                // Current year vs previous year
                $periods['current'] = [
                    'label' => (string) $now->year,
                    'start' => $now->copy()->startOfYear()->format('Y-m-d'),
                    'end' => $now->copy()->endOfYear()->format('Y-m-d'),
                ];
                $periods['previous'] = [
                    'label' => (string) ($now->year - 1),
                    'start' => $now->copy()->subYear()->startOfYear()->format('Y-m-d'),
                    'end' => $now->copy()->subYear()->endOfYear()->format('Y-m-d'),
                ];
                break;

            case 'ytd':
                // Year to date vs same period last year
                $periods['current'] = [
                    'label' => 'Year to date '.$now->year,
                    'start' => $now->copy()->startOfYear()->format('Y-m-d'),
                    'end' => $now->format('Y-m-d'),
                ];
                $periods['previous'] = [
                    'label' => 'Same dates '.($now->year - 1),
                    'start' => $now->copy()->subYear()->startOfYear()->format('Y-m-d'),
                    'end' => $now->copy()->subYearNoOverflow()->format('Y-m-d'),
                ];
                break;

            case 'custom':
                // Two date ranges the user picks, named by their dates.
                $range = fn (string $from, string $to) => Carbon::parse($from)->format('j M Y').' to '.Carbon::parse($to)->format('j M Y');
                $cs = $request->get('current_start', $now->copy()->startOfMonth()->format('Y-m-d'));
                $ce = $request->get('current_end', $now->format('Y-m-d'));
                $ps = $request->get('previous_start', $now->copy()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'));
                $pe = $request->get('previous_end', $now->copy()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d'));
                $periods['current'] = ['label' => $range($cs, $ce), 'start' => $cs, 'end' => $ce];
                $periods['previous'] = ['label' => $range($ps, $pe), 'start' => $ps, 'end' => $pe];
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

        if (! isset($periodData['current']) || ! isset($periodData['previous'])) {
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
     * Whether a change is for the better: true, false, or null when it is
     * neither (totals that only restate others). Costs going down is better.
     */
    protected function isImprovement(string $metric, float $diff): ?bool
    {
        $moreIsBetter = ['revenue', 'grossProfit', 'grossMargin', 'netProfit', 'profitMargin', 'paymentsReceived', 'totalInflows', 'netCashFlow', 'accountsReceivable', 'totalAssets', 'equity'];
        $lessIsBetter = ['expenses', 'operatingExpenses', 'costOfGoodsSold', 'billsPaid', 'payroll', 'totalExpenses', 'paymentsMade', 'expensesPaid', 'payrollPaid', 'totalOutflows', 'accountsPayable', 'totalLiabilities'];

        return match (true) {
            in_array($metric, $moreIsBetter, true) => $diff >= 0,
            in_array($metric, $lessIsBetter, true) => $diff <= 0,
            default => null,
        };
    }
}
