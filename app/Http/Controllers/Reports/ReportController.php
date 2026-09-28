<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Payroll;
use App\Services\ReportExportService;

/**
 * Shared set-up and ledger calculations for the report controllers
 * (finding L4). Each report family has its own controller in this folder.
 */
abstract class ReportController extends Controller
{
    protected ReportExportService $exportService;

    public function __construct(ReportExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * Calculate Profit & Loss from journal entries using accrual-based accounting
     */
    protected function calculateProfitLossFromJournals(int $tenantId, string $startDate, string $endDate): array
    {
        // Get income accounts total (revenue)
        // In double-entry accounting, income accounts are credited for revenue
        // Revenue = Credits - Debits for income type accounts
        $incomeData = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $startDate, $endDate) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'income');
            })
            ->selectRaw('SUM(credit) as total_credit, SUM(debit) as total_debit')
            ->first();

        $revenue = ($incomeData->total_credit ?? 0) - ($incomeData->total_debit ?? 0);

        // Get expense accounts total
        // In double-entry accounting, expense accounts are debited
        // Expenses = Debits - Credits for expense type accounts
        $expenseData = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $startDate, $endDate) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'expense');
            })
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $totalExpenses = ($expenseData->total_debit ?? 0) - ($expenseData->total_credit ?? 0);

        // Break down expenses by sub_type for detailed reporting
        $expenseBreakdown = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $startDate, $endDate) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->where('is_posted', true);
        })
            ->join('chart_of_accounts', 'journal_entries.account_id', '=', 'chart_of_accounts.id')
            ->where('chart_of_accounts.type', 'expense')
            ->selectRaw('chart_of_accounts.sub_type, SUM(journal_entries.debit) as total_debit, SUM(journal_entries.credit) as total_credit')
            ->groupBy('chart_of_accounts.sub_type')
            ->get()
            ->keyBy('sub_type');

        // Cost of Goods Sold (COGS)
        $cogsData = $expenseBreakdown->get('cost_of_goods_sold');
        $costOfGoodsSold = $cogsData ? ($cogsData->total_debit - $cogsData->total_credit) : 0;

        // Operating expenses (all expenses except COGS)
        $operatingExpenses = $totalExpenses - $costOfGoodsSold;

        // Payroll is typically part of operating expenses, but we can get it separately
        // from accounts that have payroll-related names
        $payrollExpenses = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $startDate, $endDate) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'expense')
                    ->where(function ($q) {
                        $q->where('name', 'like', '%salary%')
                            ->orWhere('name', 'like', '%salaries%')
                            ->orWhere('name', 'like', '%wage%')
                            ->orWhere('name', 'like', '%payroll%');
                    });
            })
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $payroll = ($payrollExpenses->total_debit ?? 0) - ($payrollExpenses->total_credit ?? 0);

        // Adjust operating expenses to exclude payroll if it was counted separately
        $operatingExpenses = $operatingExpenses - $payroll;

        // Net Profit = Revenue - Total Expenses
        $netProfit = $revenue - $totalExpenses;

        return [
            'revenue' => $revenue,
            'operatingExpenses' => $operatingExpenses,
            'costOfGoodsSold' => $costOfGoodsSold,
            'payrollExpenses' => $payroll,
            'totalExpenses' => $totalExpenses,
            'netProfit' => $netProfit,
        ];
    }

    /**
     * Calculate net income for balance sheet (from beginning of fiscal year to as-of date)
     */
    protected function calculateNetIncomeForBalanceSheet(int $tenantId, string $asOf): float
    {
        // Get beginning of fiscal year (assuming calendar year, can be made configurable)
        $fiscalYearStart = \Carbon\Carbon::parse($asOf)->startOfYear()->format('Y-m-d');

        // Income (credits - debits for income accounts)
        $incomeData = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $fiscalYearStart, $asOf) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$fiscalYearStart, $asOf])
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'income');
            })
            ->selectRaw('SUM(credit) as total_credit, SUM(debit) as total_debit')
            ->first();

        $totalIncome = ($incomeData->total_credit ?? 0) - ($incomeData->total_debit ?? 0);

        // Expenses (debits - credits for expense accounts)
        $expenseData = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $fiscalYearStart, $asOf) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$fiscalYearStart, $asOf])
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'expense');
            })
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $totalExpenses = ($expenseData->total_debit ?? 0) - ($expenseData->total_credit ?? 0);

        return $totalIncome - $totalExpenses;
    }

    /**
     * Calculate cash balance as of a specific date
     *
     * @param  bool  $beforeDate  If true, calculates balance before the date; if false, up to and including the date
     */
    protected function calculateCashBalance(int $tenantId, string $date, bool $beforeDate = false): float
    {
        $operator = $beforeDate ? '<' : '<=';

        $cashData = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $date, $operator) {
            $query->where('tenant_id', $tenantId)
                ->where('journal_date', $operator, $date)
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'asset')
                    ->whereIn('sub_type', ['cash', 'bank']);
            })
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        return ($cashData->total_debit ?? 0) - ($cashData->total_credit ?? 0);
    }
}
