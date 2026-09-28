<?php

namespace App\Http\Controllers\Reports;

use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\Payroll;
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

        // Get all account balances from Chart of Accounts grouped by type and sub_type
        $accounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        // ========== ASSETS ==========
        // Current Assets
        $cashAccounts = $accounts->where('type', 'asset')->whereIn('sub_type', ['cash', 'bank']);
        $accountsReceivableAccounts = $accounts->where('type', 'asset')->where('sub_type', 'accounts_receivable');
        $inventoryAccounts = $accounts->where('type', 'asset')->where('sub_type', 'inventory');
        $otherCurrentAssetAccounts = $accounts->where('type', 'asset')->where('sub_type', 'other_current_asset');

        // Fixed Assets
        $fixedAssetAccounts = $accounts->where('type', 'asset')->where('sub_type', 'fixed_asset');

        // Calculate asset totals
        $cashAndBank = $cashAccounts->sum('current_balance');
        $accountsReceivable = $accountsReceivableAccounts->sum('current_balance');
        $inventory = $inventoryAccounts->sum('current_balance');
        $otherCurrentAssets = $otherCurrentAssetAccounts->sum('current_balance');
        $fixedAssets = $fixedAssetAccounts->sum('current_balance');

        $totalCurrentAssets = $cashAndBank + $accountsReceivable + $inventory + $otherCurrentAssets;
        $totalAssets = $totalCurrentAssets + $fixedAssets;

        // ========== LIABILITIES ==========
        // Current Liabilities
        $accountsPayableAccounts = $accounts->where('type', 'liability')->where('sub_type', 'accounts_payable');
        $creditCardAccounts = $accounts->where('type', 'liability')->where('sub_type', 'credit_card');
        $otherCurrentLiabilityAccounts = $accounts->where('type', 'liability')->where('sub_type', 'other_current_liability');

        // Long-term Liabilities
        $longTermLiabilityAccounts = $accounts->where('type', 'liability')->where('sub_type', 'long_term_liability');

        // Calculate liability totals
        $accountsPayable = $accountsPayableAccounts->sum('current_balance');
        $creditCardPayable = $creditCardAccounts->sum('current_balance');
        $otherCurrentLiabilities = $otherCurrentLiabilityAccounts->sum('current_balance');
        $longTermLiabilities = $longTermLiabilityAccounts->sum('current_balance');

        $totalCurrentLiabilities = $accountsPayable + $creditCardPayable + $otherCurrentLiabilities;
        $totalLiabilities = $totalCurrentLiabilities + $longTermLiabilities;

        // ========== EQUITY ==========
        $equityAccounts = $accounts->where('type', 'equity')->where('sub_type', 'equity');
        $retainedEarningsAccounts = $accounts->where('type', 'equity')->where('sub_type', 'retained_earnings');

        $ownersEquity = $equityAccounts->sum('current_balance');
        $retainedEarnings = $retainedEarningsAccounts->sum('current_balance');

        // Calculate Net Income for the period (Income - Expenses from journal entries)
        $netIncome = $this->calculateNetIncomeForBalanceSheet($tenantId, $asOf);

        $totalEquity = $ownersEquity + $retainedEarnings + $netIncome;
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity;

        // Prepare detailed account lists for the view
        $assetDetails = [
            'cash' => $cashAccounts,
            'accounts_receivable' => $accountsReceivableAccounts,
            'inventory' => $inventoryAccounts,
            'other_current' => $otherCurrentAssetAccounts,
            'fixed' => $fixedAssetAccounts,
        ];

        $liabilityDetails = [
            'accounts_payable' => $accountsPayableAccounts,
            'credit_card' => $creditCardAccounts,
            'other_current' => $otherCurrentLiabilityAccounts,
            'long_term' => $longTermLiabilityAccounts,
        ];

        $equityDetails = [
            'capital' => $equityAccounts,
            'retained_earnings' => $retainedEarningsAccounts,
        ];

        return view('reports.balance-sheet', compact(
            'asOf',
            // Asset totals
            'cashAndBank', 'accountsReceivable', 'inventory', 'otherCurrentAssets', 'fixedAssets',
            'totalCurrentAssets', 'totalAssets',
            // Liability totals
            'accountsPayable', 'creditCardPayable', 'otherCurrentLiabilities', 'longTermLiabilities',
            'totalCurrentLiabilities', 'totalLiabilities',
            // Equity totals
            'ownersEquity', 'retainedEarnings', 'netIncome', 'totalEquity',
            'totalLiabilitiesAndEquity',
            // Account details for expandable view
            'assetDetails', 'liabilityDetails', 'equityDetails'
        ));
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

        // Calculate Beginning Cash Balance from cash/bank accounts
        $beginningCash = $this->calculateCashBalance($tenantId, $startDate, true);

        // ========== OPERATING ACTIVITIES ==========
        // Cash received from customers
        $paymentsReceived = PaymentReceived::where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');

        // Cash paid to suppliers/vendors
        $paymentsMade = PaymentMade::where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');

        // Cash paid for operating expenses
        $expensesPaid = Expense::where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->where('status', Expense::STATUS_PAID)
            ->sum('total');

        // Cash paid for salaries/wages
        $payrollPaid = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->where('status', 'paid')
            ->sum('net_salary');

        // Net cash from operating activities
        $operatingInflows = $paymentsReceived;
        $operatingOutflows = $paymentsMade + $expensesPaid + $payrollPaid;
        $netOperatingCashFlow = $operatingInflows - $operatingOutflows;

        // ========== INVESTING ACTIVITIES ==========
        // Cash spent on fixed assets (from journal entries debiting fixed asset accounts)
        $fixedAssetPurchases = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $startDate, $endDate) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'asset')
                    ->where('sub_type', 'fixed_asset');
            })
            ->sum('debit');

        // Cash received from sale of assets (credits to fixed asset accounts)
        $fixedAssetSales = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $startDate, $endDate) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'asset')
                    ->where('sub_type', 'fixed_asset');
            })
            ->sum('credit');

        $netInvestingCashFlow = $fixedAssetSales - $fixedAssetPurchases;

        // ========== FINANCING ACTIVITIES ==========
        // Long-term borrowings received (credits to long-term liability accounts)
        $borrowingsReceived = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $startDate, $endDate) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'liability')
                    ->where('sub_type', 'long_term_liability');
            })
            ->sum('credit');

        // Loan repayments (debits to long-term liability accounts)
        $loanRepayments = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $startDate, $endDate) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'liability')
                    ->where('sub_type', 'long_term_liability');
            })
            ->sum('debit');

        // Owner's capital contributions (credits to equity accounts)
        $capitalContributions = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $startDate, $endDate) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'equity')
                    ->where('sub_type', 'equity');
            })
            ->sum('credit');

        // Owner's drawings/dividends (debits to equity accounts)
        $drawings = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $startDate, $endDate) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'equity')
                    ->where('sub_type', 'equity');
            })
            ->sum('debit');

        $netFinancingCashFlow = $borrowingsReceived - $loanRepayments + $capitalContributions - $drawings;

        // ========== SUMMARY ==========
        $totalInflows = $paymentsReceived + $fixedAssetSales + $borrowingsReceived + $capitalContributions;
        $totalOutflows = $paymentsMade + $expensesPaid + $payrollPaid + $fixedAssetPurchases + $loanRepayments + $drawings;
        $netCashFlow = $netOperatingCashFlow + $netInvestingCashFlow + $netFinancingCashFlow;
        $endingCash = $beginningCash + $netCashFlow;

        return view('reports.cash-flow', compact(
            // Operating
            'paymentsReceived', 'paymentsMade', 'expensesPaid', 'payrollPaid',
            'operatingInflows', 'operatingOutflows', 'netOperatingCashFlow',
            // Investing
            'fixedAssetPurchases', 'fixedAssetSales', 'netInvestingCashFlow',
            // Financing
            'borrowingsReceived', 'loanRepayments', 'capitalContributions', 'drawings', 'netFinancingCashFlow',
            // Summary
            'totalInflows', 'totalOutflows', 'netCashFlow',
            'beginningCash', 'endingCash',
            'startDate', 'endDate'
        ));
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

        // Get all account balances from Chart of Accounts grouped by type and sub_type
        $accounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        // Assets
        $cashAndBank = $accounts->where('type', 'asset')->whereIn('sub_type', ['cash', 'bank'])->sum('current_balance');
        $accountsReceivable = $accounts->where('type', 'asset')->where('sub_type', 'accounts_receivable')->sum('current_balance');
        $inventory = $accounts->where('type', 'asset')->where('sub_type', 'inventory')->sum('current_balance');
        $otherCurrentAssets = $accounts->where('type', 'asset')->where('sub_type', 'other_current_asset')->sum('current_balance');
        $fixedAssets = $accounts->where('type', 'asset')->where('sub_type', 'fixed_asset')->sum('current_balance');

        $totalCurrentAssets = $cashAndBank + $accountsReceivable + $inventory + $otherCurrentAssets;
        $totalAssets = $totalCurrentAssets + $fixedAssets;

        // Liabilities
        $accountsPayable = $accounts->where('type', 'liability')->where('sub_type', 'accounts_payable')->sum('current_balance');
        $creditCardPayable = $accounts->where('type', 'liability')->where('sub_type', 'credit_card')->sum('current_balance');
        $otherCurrentLiabilities = $accounts->where('type', 'liability')->where('sub_type', 'other_current_liability')->sum('current_balance');
        $longTermLiabilities = $accounts->where('type', 'liability')->where('sub_type', 'long_term_liability')->sum('current_balance');

        $totalCurrentLiabilities = $accountsPayable + $creditCardPayable + $otherCurrentLiabilities;
        $totalLiabilities = $totalCurrentLiabilities + $longTermLiabilities;

        // Equity
        $ownersEquity = $accounts->where('type', 'equity')->where('sub_type', 'equity')->sum('current_balance');
        $retainedEarnings = $accounts->where('type', 'equity')->where('sub_type', 'retained_earnings')->sum('current_balance');
        $netIncome = $this->calculateNetIncomeForBalanceSheet($tenantId, $asOf);

        $totalEquity = $ownersEquity + $retainedEarnings + $netIncome;
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity;

        $data = compact(
            'asOf',
            'cashAndBank', 'accountsReceivable', 'inventory', 'otherCurrentAssets', 'fixedAssets',
            'totalCurrentAssets', 'totalAssets',
            'accountsPayable', 'creditCardPayable', 'otherCurrentLiabilities', 'longTermLiabilities',
            'totalCurrentLiabilities', 'totalLiabilities',
            'ownersEquity', 'retainedEarnings', 'netIncome', 'totalEquity',
            'totalLiabilitiesAndEquity'
        );

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

        $paymentsReceived = PaymentReceived::where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');

        $paymentsMade = PaymentMade::where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');

        $expensesPaid = Expense::where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->where('status', Expense::STATUS_PAID)
            ->sum('total');

        $payrollPaid = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->where('status', 'paid')
            ->sum('net_salary');

        $operatingInflows = $paymentsReceived;
        $operatingOutflows = $paymentsMade + $expensesPaid + $payrollPaid;
        $netOperatingCashFlow = $operatingInflows - $operatingOutflows;

        // Investing Activities
        $fixedAssetPurchases = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $startDate, $endDate) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'asset')->where('sub_type', 'fixed_asset');
            })
            ->sum('debit');

        $fixedAssetSales = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $startDate, $endDate) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'asset')->where('sub_type', 'fixed_asset');
            })
            ->sum('credit');

        $netInvestingCashFlow = $fixedAssetSales - $fixedAssetPurchases;

        // Financing Activities
        $borrowingsReceived = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $startDate, $endDate) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'liability')->where('sub_type', 'long_term_liability');
            })
            ->sum('credit');

        $loanRepayments = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $startDate, $endDate) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'liability')->where('sub_type', 'long_term_liability');
            })
            ->sum('debit');

        $capitalContributions = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $startDate, $endDate) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'equity')->where('sub_type', 'equity');
            })
            ->sum('credit');

        $drawings = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $startDate, $endDate) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->where('is_posted', true);
        })
            ->whereHas('account', function ($query) {
                $query->where('type', 'equity')->where('sub_type', 'equity');
            })
            ->sum('debit');

        $netFinancingCashFlow = $borrowingsReceived - $loanRepayments + $capitalContributions - $drawings;

        // Summary
        $beginningCash = $this->calculateCashBalance($tenantId, $startDate, true);
        $totalInflows = $paymentsReceived + $fixedAssetSales + $borrowingsReceived + $capitalContributions;
        $totalOutflows = $paymentsMade + $expensesPaid + $payrollPaid + $fixedAssetPurchases + $loanRepayments + $drawings;
        $netCashFlow = $netOperatingCashFlow + $netInvestingCashFlow + $netFinancingCashFlow;
        $endingCash = $beginningCash + $netCashFlow;

        $data = compact(
            'paymentsReceived', 'paymentsMade', 'expensesPaid', 'payrollPaid',
            'operatingInflows', 'operatingOutflows', 'netOperatingCashFlow',
            'fixedAssetPurchases', 'fixedAssetSales', 'netInvestingCashFlow',
            'borrowingsReceived', 'loanRepayments', 'capitalContributions', 'drawings', 'netFinancingCashFlow',
            'totalInflows', 'totalOutflows', 'netCashFlow',
            'beginningCash', 'endingCash',
            'startDate', 'endDate'
        );

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
