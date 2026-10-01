<?php

namespace App\Http\Controllers\Reports;

use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Services\Accounting\FinancialStatements;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Profit and loss, balance sheet, cash flow, trial balance and general ledger, with their exports.
 *
 * Split out of the old 3,400-line ReportController (finding L4). Route
 * names are unchanged.
 */
class FinancialReportController extends ReportController
{
    private const LEDGER_PAGE_SIZE = 100;

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
        return view('reports.balance-sheet', app(FinancialStatements::class)->balanceSheet($tenantId, $asOf));
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
        return view('reports.cash-flow', app(FinancialStatements::class)->cashFlow($tenantId, $startDate, $endDate));
    }

    public function trialBalance(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $asOf = $request->get('as_of', now()->format('Y-m-d'));

        // One grouped query instead of every line of every account (P1).
        $accounts = app(FinancialStatements::class)->trialBalance($tenantId, $asOf);

        $totalDebits = $accounts->sum('total_debit');
        $totalCredits = $accounts->sum('total_credit');

        return view('reports.trial-balance', compact('accounts', 'totalDebits', 'totalCredits', 'asOf'));
    }

    public function generalLedger(Request $request)
    {
        $request->validate(['start_date' => 'nullable|date', 'end_date' => 'nullable|date']);
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
        $pageOpeningBalance = 0;
        $totalDebit = 0;
        $totalCredit = 0;
        $closingBalance = 0;

        if ($accountId) {
            $selectedAccount = ChartOfAccount::where('tenant_id', $tenantId)->find($accountId);

            if (! $selectedAccount) {
                return back()->with('error', 'Account not found.');
            }

            // Opening balance: all posted lines before the start date,
            // debits minus credits, so "Dr"/"Cr" in the view read correctly.
            $opening = $this->ledgerLines($tenantId, $accountId, null, Carbon::parse($startDate)->subDay()->toDateString())
                ->selectRaw('SUM(journal_entries.debit) as total_debit, SUM(journal_entries.credit) as total_credit')
                ->toBase()
                ->first();
            $openingBalance = round((float) ($opening->total_debit ?? 0) - (float) ($opening->total_credit ?? 0), 2);

            // Totals for the whole period, not just the page shown.
            $totals = $this->ledgerLines($tenantId, $accountId, $startDate, $endDate)
                ->selectRaw('SUM(journal_entries.debit) as total_debit, SUM(journal_entries.credit) as total_credit')
                ->toBase()
                ->first();
            $totalDebit = round((float) ($totals->total_debit ?? 0), 2);
            $totalCredit = round((float) ($totals->total_credit ?? 0), 2);
            $closingBalance = round($openingBalance + $totalDebit - $totalCredit, 2);

            // One page at a time, in date order (P7). It used to load every
            // line unsorted, so the running balance could be wrong.
            $entries = $this->ledgerLines($tenantId, $accountId, $startDate, $endDate)
                ->select('journal_entries.*')
                ->with('journal')
                ->orderBy('journals.journal_date')
                ->orderBy('journals.id')
                ->orderBy('journal_entries.id')
                ->paginate(self::LEDGER_PAGE_SIZE)
                ->withQueryString();

            // Running balance brought forward from the earlier pages.
            $pageOpeningBalance = $openingBalance;
            if ($entries->currentPage() > 1) {
                $earlier = $this->ledgerLines($tenantId, $accountId, $startDate, $endDate)
                    ->select('journal_entries.debit', 'journal_entries.credit')
                    ->orderBy('journals.journal_date')
                    ->orderBy('journals.id')
                    ->orderBy('journal_entries.id')
                    ->limit(($entries->currentPage() - 1) * $entries->perPage())
                    ->toBase();
                $before = DB::query()->fromSub($earlier, 'earlier')
                    ->selectRaw('SUM(debit) as debit, SUM(credit) as credit')
                    ->first();
                $pageOpeningBalance = round($openingBalance + (float) ($before->debit ?? 0) - (float) ($before->credit ?? 0), 2);
            }
        }

        return view('reports.general-ledger', compact(
            'accounts', 'entries', 'selectedAccount', 'startDate', 'endDate', 'accountId', 'openingBalance',
            'pageOpeningBalance', 'totalDebit', 'totalCredit', 'closingBalance'
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

        $data = app(FinancialStatements::class)->balanceSheet($tenantId, $asOf);
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

        $data = app(FinancialStatements::class)->cashFlow($tenantId, $startDate, $endDate);

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

        // One grouped query instead of every line of every account (P1).
        $accounts = app(FinancialStatements::class)->trialBalance($tenantId, $asOf);

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
            // In date order (P7).
            $entries = $this->ledgerLines($tenantId, $accountId, $startDate, $endDate)
                ->select('journal_entries.*')
                ->with(['journal'])
                ->orderBy('journals.journal_date')
                ->orderBy('journals.id')
                ->orderBy('journal_entries.id')
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

    /**
     * Posted journal lines of one account between two dates (either may be
     * null), joined to their journal so they can be sorted by date.
     */
    private function ledgerLines($tenantId, $accountId, ?string $from, ?string $to)
    {
        return JournalEntry::query()
            ->join('journals', 'journals.id', '=', 'journal_entries.journal_id')
            ->where('journals.tenant_id', $tenantId)
            ->where('journals.is_posted', true)
            ->whereNull('journals.deleted_at')
            ->where('journal_entries.account_id', $accountId)
            ->when($from, fn ($q) => $q->where('journals.journal_date', '>=', $from))
            // "Before the next day": SQLite keeps a time part on dates.
            ->when($to, fn ($q) => $q->where('journals.journal_date', '<', Carbon::parse($to)->addDay()->toDateString()));
    }
}
