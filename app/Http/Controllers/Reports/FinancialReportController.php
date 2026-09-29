<?php

namespace App\Http\Controllers\Reports;

use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use Illuminate\Http\Request;

/**
 * Profit and loss, balance sheet, cash flow, trial balance and general ledger, with their exports.
 *
 * Split out of the old 3,400-line ReportController (finding L4). Route
 * names are unchanged.
 */
class FinancialReportController extends ReportController
{
    public function index()
    {
        return view('reports.index');
    }

    public function profitLoss(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        // Get P&L data using accrual-based accounting from journal entries
        $plData = $this->calculateProfitLossFromJournals($tenantId, $startDate, $endDate);

        $revenue = $plData['revenue'];
        $costOfGoodsSold = $plData['costOfGoodsSold'];
        $grossProfit = $revenue - $costOfGoodsSold;
        $operatingExpenses = $plData['operatingExpenses'];
        $payroll = $plData['payrollExpenses'];
        $totalExpenses = $plData['totalExpenses'];
        $netProfit = $plData['netProfit'];

        return view('reports.profit-loss', compact(
            'revenue', 'costOfGoodsSold', 'grossProfit', 'operatingExpenses', 'payroll',
            'totalExpenses', 'netProfit', 'startDate', 'endDate'
        ));
    }

    public function balanceSheet(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $asOf = $request->get('as_of', now()->format('Y-m-d'));

        // Built from the ledger at the chosen date (A2).
        return view('reports.balance-sheet', app(\App\Services\Accounting\FinancialStatements::class)->balanceSheet($tenantId, $asOf));
    }

    /**
     * Cash Flow Statement following standard accounting format
     * Uses Direct Method with Operating, Investing, and Financing sections
     */
    public function cashFlow(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        // Built from cash and bank account movements in the ledger (A7).
        return view('reports.cash-flow', app(\App\Services\Accounting\FinancialStatements::class)->cashFlow($tenantId, $startDate, $endDate));
    }

    public function trialBalance(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $asOf = $request->get('as_of', now()->format('Y-m-d'));

        $accounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get()
            ->map(function ($account) use ($tenantId, $asOf) {
                $entries = JournalEntry::whereHas('journal', function ($q) use ($tenantId, $asOf) {
                    $q->where('tenant_id', $tenantId)
                        ->where('journal_date', '<=', $asOf)
                        ->where('is_posted', true);
                })->where('account_id', $account->id)->get();

                $account->total_debit = $entries->sum('debit');
                $account->total_credit = $entries->sum('credit');

                return $account;
            })
            ->filter(function ($account) {
                return $account->total_debit > 0 || $account->total_credit > 0;
            });

        $totalDebits = $accounts->sum('total_debit');
        $totalCredits = $accounts->sum('total_credit');

        return view('reports.trial-balance', compact('accounts', 'totalDebits', 'totalCredits', 'asOf'));
    }

    public function generalLedger(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $accountId = $request->get('account_id');

        $accounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        $entries = collect();
        $selectedAccount = null;
        $openingBalance = 0;

        if ($accountId) {
            $selectedAccount = ChartOfAccount::where('tenant_id', $tenantId)->find($accountId);

            if (! $selectedAccount) {
                return back()->with('error', 'Account not found.');
            }

            // Calculate opening balance (all entries before start date)
            $openingEntries = JournalEntry::whereHas('journal', function ($q) use ($tenantId, $startDate) {
                $q->where('tenant_id', $tenantId)
                    ->where('journal_date', '<', $startDate)
                    ->where('is_posted', true);
            })
                ->where('account_id', $accountId)
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();

            // Opening balance calculation based on account type
            // Assets & Expenses have debit balances, Liabilities, Equity & Income have credit balances
            $totalDebit = $openingEntries->total_debit ?? 0;
            $totalCredit = $openingEntries->total_credit ?? 0;

            if ($selectedAccount->isDebitBalance()) {
                $openingBalance = $totalDebit - $totalCredit;
            } else {
                $openingBalance = $totalCredit - $totalDebit;
            }

            // Get entries within the selected period
            $entries = JournalEntry::whereHas('journal', function ($q) use ($tenantId, $startDate, $endDate) {
                $q->where('tenant_id', $tenantId)
                    ->whereBetween('journal_date', [$startDate, $endDate])
                    ->where('is_posted', true);
            })
                ->where('account_id', $accountId)
                ->with(['journal'])
                ->get();
        }

        return view('reports.general-ledger', compact(
            'accounts', 'entries', 'selectedAccount', 'startDate', 'endDate', 'accountId', 'openingBalance'
        ));
    }

    /**
     * Export Profit & Loss Report
     */
    public function exportProfitLoss(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        // Get P&L data using accrual-based accounting from journal entries
        $plData = $this->calculateProfitLossFromJournals($tenantId, $startDate, $endDate);

        $revenue = $plData['revenue'];
        $costOfGoodsSold = $plData['costOfGoodsSold'];
        $grossProfit = $revenue - $costOfGoodsSold;
        $operatingExpenses = $plData['operatingExpenses'];
        $payroll = $plData['payrollExpenses'];
        $totalExpenses = $plData['totalExpenses'];
        $netProfit = $plData['netProfit'];

        $data = compact('revenue', 'costOfGoodsSold', 'grossProfit', 'operatingExpenses', 'payroll', 'totalExpenses', 'netProfit', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->profitLossData($data);

            return $this->exportService
                ->setTitle('Profit & Loss Statement')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Profit & Loss Statement')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.profit-loss', $data);
    }

    /**
     * Export Balance Sheet Report
     */
    public function exportBalanceSheet(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $asOf = $request->get('as_of', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        $data = app(\App\Services\Accounting\FinancialStatements::class)->balanceSheet($tenantId, $asOf);
        unset($data['assetDetails'], $data['liabilityDetails'], $data['equityDetails']);

        if ($format === 'csv') {
            $exportData = $this->exportService->balanceSheetData($data);

            return $this->exportService
                ->setTitle('Balance Sheet')
                ->setFilters(['As of' => $asOf])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Balance Sheet')
            ->setFilters(['As of' => $asOf])
            ->exportToPdf('reports.pdf.balance-sheet', $data);
    }

    /**
     * Export Cash Flow Report
     */
    public function exportCashFlow(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        $data = app(\App\Services\Accounting\FinancialStatements::class)->cashFlow($tenantId, $startDate, $endDate);

        if ($format === 'csv') {
            $exportData = $this->exportService->cashFlowData($data);

            return $this->exportService
                ->setTitle('Cash Flow Statement')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Cash Flow Statement')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.cash-flow', $data);
    }

    /**
     * Export Trial Balance Report
     */
    public function exportTrialBalance(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $asOf = $request->get('as_of', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        $accounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get()
            ->map(function ($account) use ($tenantId, $asOf) {
                $entries = JournalEntry::whereHas('journal', function ($q) use ($tenantId, $asOf) {
                    $q->where('tenant_id', $tenantId)
                        ->where('journal_date', '<=', $asOf)
                        ->where('is_posted', true);
                })->where('account_id', $account->id)->get();

                $account->total_debit = $entries->sum('debit');
                $account->total_credit = $entries->sum('credit');

                return $account;
            })
            ->filter(function ($account) {
                return $account->total_debit > 0 || $account->total_credit > 0;
            });

        $totalDebits = $accounts->sum('total_debit');
        $totalCredits = $accounts->sum('total_credit');

        $data = compact('accounts', 'totalDebits', 'totalCredits', 'asOf');

        if ($format === 'csv') {
            $exportData = $this->exportService->trialBalanceData($accounts, $totalDebits, $totalCredits);

            return $this->exportService
                ->setTitle('Trial Balance')
                ->setFilters(['As of' => $asOf])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Trial Balance')
            ->setFilters(['As of' => $asOf])
            ->exportToPdf('reports.pdf.trial-balance', $data);
    }

    /**
     * Export General Ledger Report
     */
    public function exportGeneralLedger(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $accountId = $request->get('account_id');
        $format = $request->get('format', 'pdf');

        $accounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        $entries = collect();
        $selectedAccount = null;

        if ($accountId) {
            $selectedAccount = ChartOfAccount::where('tenant_id', $tenantId)->find($accountId);
            $entries = JournalEntry::whereHas('journal', function ($q) use ($tenantId, $startDate, $endDate) {
                $q->where('tenant_id', $tenantId)
                    ->whereBetween('journal_date', [$startDate, $endDate])
                    ->where('is_posted', true);
            })
                ->where('account_id', $accountId)
                ->with(['journal'])
                ->get();
        }

        $data = compact('accounts', 'entries', 'selectedAccount', 'startDate', 'endDate', 'accountId');

        if ($format === 'csv') {
            $exportData = $this->exportService->generalLedgerData($entries, $selectedAccount);

            return $this->exportService
                ->setTitle('General Ledger'.($selectedAccount ? ' - '.$selectedAccount->name : ''))
                ->setFilters(['Period' => "$startDate to $endDate", 'Account' => $selectedAccount?->name ?? 'All'])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('General Ledger'.($selectedAccount ? ' - '.$selectedAccount->name : ''))
            ->setFilters(['Period' => "$startDate to $endDate", 'Account' => $selectedAccount?->name ?? 'All'])
            ->exportToPdf('reports.pdf.general-ledger', $data);
    }
}
