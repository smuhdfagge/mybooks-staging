<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Expense;
use App\Models\Employee;
use App\Models\Customer;
use App\Models\Vendor;
use App\Models\Payroll;
use App\Models\PayrollBatch;
use App\Models\SalaryStructureVersion;
use App\Models\Item;
use App\Models\Inventory;
use App\Models\Department;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\PaymentReceived;
use App\Models\PaymentMade;
use App\Models\TaxRate;
use App\Models\CustomReport;
use App\Services\ReportExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

use App\Services\Reports\PayrollReportService;

class ReportController extends Controller
{
    protected ReportExportService $exportService;

    public function __construct(ReportExportService $exportService)
    {
        $this->exportService = $exportService;
    }

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

    /**
     * Calculate Profit & Loss from journal entries using accrual-based accounting
     *
     * @param int $tenantId
     * @param string $startDate
     * @param string $endDate
     * @return array
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

    /**
     * Calculate cash balance as of a specific date
     * 
     * @param int $tenantId
     * @param string $date
     * @param bool $beforeDate If true, calculates balance before the date; if false, up to and including the date
     * @return float
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
            
            if (!$selectedAccount) {
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

    public function accountsPayable(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $asOf = $request->get('as_of', now()->format('Y-m-d'));

        $bills = Bill::where('tenant_id', $tenantId)
            ->where('bill_date', '<=', $asOf)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->where('balance_due', '>', 0)
            ->with('vendor')
            ->orderBy('due_date')
            ->get();

        // Group by aging based on as_of date for proper historical accuracy
        $asOfDate = \Carbon\Carbon::parse($asOf);
        $current = $bills->filter(fn($bill) => $bill->due_date >= $asOfDate)->sum('balance_due');
        $days30 = $bills->filter(fn($bill) => $bill->due_date < $asOfDate && $bill->due_date >= $asOfDate->copy()->subDays(30))->sum('balance_due');
        $days60 = $bills->filter(fn($bill) => $bill->due_date < $asOfDate->copy()->subDays(30) && $bill->due_date >= $asOfDate->copy()->subDays(60))->sum('balance_due');
        $days90 = $bills->filter(fn($bill) => $bill->due_date < $asOfDate->copy()->subDays(60) && $bill->due_date >= $asOfDate->copy()->subDays(90))->sum('balance_due');
        $days120 = $bills->filter(fn($bill) => $bill->due_date < $asOfDate->copy()->subDays(90) && $bill->due_date >= $asOfDate->copy()->subDays(120))->sum('balance_due');
        $over120 = $bills->filter(fn($bill) => $bill->due_date < $asOfDate->copy()->subDays(120))->sum('balance_due');

        $totalPayable = $bills->sum('balance_due');

        return view('reports.accounts-payable', compact(
            'bills', 'current', 'days30', 'days60', 'days90', 'days120', 'over120', 'totalPayable', 'asOf'
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

    public function purchaseByVendor(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $vendors = Vendor::where('tenant_id', $tenantId)
            ->whereHas('bills', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('bill_date', [$startDate, $endDate]);
            })
            ->withCount(['bills' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('bill_date', [$startDate, $endDate]);
            }])
            ->withSum(['bills' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('bill_date', [$startDate, $endDate]);
            }], 'total')
            ->withSum(['bills' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('bill_date', [$startDate, $endDate]);
            }], 'amount_paid')
            ->orderByDesc('bills_sum_total')
            ->get();

        $totalPurchases = $vendors->sum('bills_sum_total');
        $totalPaid = $vendors->sum('bills_sum_amount_paid');

        return view('reports.purchase-by-vendor', compact(
            'vendors', 'totalPurchases', 'totalPaid', 'startDate', 'endDate'
        ));
    }

    public function inventorySummary(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $items = Item::where('tenant_id', $tenantId)
            ->where('track_inventory', true)
            ->with('inventory')
            ->get()
            ->map(function ($item) {
                $item->stock_quantity = $item->inventory->quantity ?? 0;
                $item->stock_value = $item->stock_quantity * $item->cost_price;
                $item->is_low_stock = $item->stock_quantity <= $item->reorder_level;
                return $item;
            });

        $totalItems = $items->count();
        $totalValue = $items->sum('stock_value');
        $lowStockItems = $items->where('is_low_stock', true)->count();

        return view('reports.inventory-summary', compact(
            'items', 'totalItems', 'totalValue', 'lowStockItems'
        ));
    }

    public function payrollSummary(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $payrolls = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee')
            ->get();

        $totalGross = $payrolls->sum('gross_salary');
        $totalDeductions = $payrolls->sum('total_deductions');
        $totalNet = $payrolls->sum('net_salary');

        // Group by employee
        $byEmployee = $payrolls->groupBy('employee_id')->map(function ($records) {
            return [
                'employee' => $records->first()->employee,
                'gross' => $records->sum('gross_salary'),
                'deductions' => $records->sum('total_deductions'),
                'net' => $records->sum('net_salary'),
                'count' => $records->count(),
            ];
        })->values();

        return view('reports.payroll-summary', compact(
            'payrolls', 'byEmployee', 'totalGross', 'totalDeductions', 
            'totalNet', 'startDate', 'endDate'
        ));
    }

    public function payrollByDepartment(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $payrolls = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee.department')
            ->get();

        $totalGross = $payrolls->sum('gross_salary');
        $totalDeductions = $payrolls->sum('total_deductions');
        $totalNet = $payrolls->sum('net_salary');

        $byDepartment = $payrolls->groupBy(function ($payroll) {
            return $payroll->employee?->department_id ?? 0;
        })->map(function ($records) {
            $department = $records->first()->employee?->department;
            return [
                'department' => $department,
                'department_name' => $department?->name ?? 'Unassigned',
                'employee_count' => $records->unique('employee_id')->count(),
                'gross' => $records->sum('gross_salary'),
                'allowances' => $records->sum('allowances'),
                'overtime' => $records->sum('overtime_amount'),
                'tax' => $records->sum('tax_deduction'),
                'deductions' => $records->sum('total_deductions'),
                'net' => $records->sum('net_salary'),
                'records_count' => $records->count(),
            ];
        })->sortByDesc('gross')->values();

        return view('reports.payroll-by-department', compact(
            'byDepartment', 'totalGross', 'totalDeductions', 'totalNet',
            'startDate', 'endDate'
        ));
    }

    public function employeeEarnings(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $employeeId = $request->get('employee_id');

        $employees = Employee::where('tenant_id', $tenantId)
            ->orderBy('first_name')
            ->get();

        $query = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee.department');

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $payrolls = $query->orderBy('pay_date', 'desc')->get();

        $totalGross = $payrolls->sum('gross_salary');
        $totalAllowances = $payrolls->sum('allowances');
        $totalOvertime = $payrolls->sum('overtime_amount');
        $totalTax = $payrolls->sum('tax_deduction');
        $totalDeductions = $payrolls->sum('total_deductions');
        $totalNet = $payrolls->sum('net_salary');

        return view('reports.employee-earnings', compact(
            'payrolls', 'employees', 'totalGross', 'totalAllowances',
            'totalOvertime', 'totalTax', 'totalDeductions', 'totalNet',
            'startDate', 'endDate', 'employeeId'
        ));
    }

    /**
     * Payroll Register — Detailed monthly register with all salary components
     */
    public function payrollRegister(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $month = $request->get('month', now()->format('Y-m'));
        $status = $request->get('status');
        $departmentId = $request->get('department_id');
        $departments = Department::where('tenant_id', $tenantId)->orderBy('name')->get();

        return view('reports.payroll-register', app(PayrollReportService::class)
            ->payrollRegister($tenantId, $month, $status, $departmentId)
            + compact('departments', 'month', 'status', 'departmentId'));
    }

    /**
     * Year-to-Date (YTD) Earnings — Cumulative earnings per employee
     */
    public function ytdEarnings(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $year = (int) $request->get('year', now()->year);
        $employeeId = $request->get('employee_id');
        $employees = Employee::where('tenant_id', $tenantId)->orderBy('first_name')->get();

        return view('reports.ytd-earnings', app(PayrollReportService::class)
            ->ytdEarnings($tenantId, $year, $employeeId)
            + compact('employees', 'year', 'employeeId'));
    }

    /**
     * Tax Liability Report — Tax deductions summary for filing
     */
    public function taxLiabilityPayroll(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        return view('reports.tax-liability-payroll', app(PayrollReportService::class)
            ->taxLiability(auth()->user()->tenant_id, $startDate, $endDate)
            + compact('startDate', 'endDate'));
    }

    /**
     * Employer Contribution Summary — Employer-side costs breakdown
     */
    public function employerContributions(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        return view('reports.employer-contributions', app(PayrollReportService::class)
            ->employerContributions(auth()->user()->tenant_id, $startDate, $endDate)
            + compact('startDate', 'endDate'));
    }

    /**
     * Bank Disbursement Report — Payment list for bank transfers
     */
    public function bankDisbursement(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $status = $request->get('status', Payroll::STATUS_APPROVED);
        $paymentMethod = $request->get('payment_method');

        return view('reports.bank-disbursement', app(PayrollReportService::class)
            ->bankDisbursement(auth()->user()->tenant_id, $startDate, $endDate, $status, $paymentMethod)
            + compact('startDate', 'endDate', 'status', 'paymentMethod'));
    }

    /**
     * Salary Revision History — Track salary structure changes over time
     */
    public function salaryRevisionHistory(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $employeeId = $request->get('employee_id');
        $employees = Employee::where('tenant_id', $tenantId)->orderBy('first_name')->get();

        return view('reports.salary-revision-history', app(PayrollReportService::class)
            ->salaryRevisionHistory($tenantId, $employeeId)
            + compact('employees', 'employeeId'));
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
                ->setTitle('General Ledger' . ($selectedAccount ? ' - ' . $selectedAccount->name : ''))
                ->setFilters(['Period' => "$startDate to $endDate", 'Account' => $selectedAccount?->name ?? 'All'])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('General Ledger' . ($selectedAccount ? ' - ' . $selectedAccount->name : ''))
            ->setFilters(['Period' => "$startDate to $endDate", 'Account' => $selectedAccount?->name ?? 'All'])
            ->exportToPdf('reports.pdf.general-ledger', $data);
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
     * Export Accounts Payable Report
     */
    public function exportAccountsPayable(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $asOf = $request->get('as_of', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        $bills = Bill::where('tenant_id', $tenantId)
            ->where('bill_date', '<=', $asOf)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->where('balance_due', '>', 0)
            ->with('vendor')
            ->orderBy('due_date')
            ->get();

        $asOfDate = \Carbon\Carbon::parse($asOf);
        $current = $bills->filter(fn($bill) => $bill->due_date >= $asOfDate)->sum('balance_due');
        $days30 = $bills->filter(fn($bill) => $bill->due_date < $asOfDate && $bill->due_date >= $asOfDate->copy()->subDays(30))->sum('balance_due');
        $days60 = $bills->filter(fn($bill) => $bill->due_date < $asOfDate->copy()->subDays(30) && $bill->due_date >= $asOfDate->copy()->subDays(60))->sum('balance_due');
        $days90 = $bills->filter(fn($bill) => $bill->due_date < $asOfDate->copy()->subDays(60) && $bill->due_date >= $asOfDate->copy()->subDays(90))->sum('balance_due');
        $days120 = $bills->filter(fn($bill) => $bill->due_date < $asOfDate->copy()->subDays(90) && $bill->due_date >= $asOfDate->copy()->subDays(120))->sum('balance_due');
        $over120 = $bills->filter(fn($bill) => $bill->due_date < $asOfDate->copy()->subDays(120))->sum('balance_due');
        $totalPayable = $bills->sum('balance_due');

        $data = compact('bills', 'current', 'days30', 'days60', 'days90', 'days120', 'over120', 'totalPayable', 'asOf');

        if ($format === 'csv') {
            $exportData = $this->exportService->accountsPayableData($bills, [
                'current' => $current,
                'days30' => $days30,
                'days60' => $days60,
                'days90' => $days90,
                'days120' => $days120,
                'over120' => $over120,
                'total' => $totalPayable,
            ]);
            return $this->exportService
                ->setTitle('Accounts Payable Aging')
                ->setFilters(['As of' => $asOf])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Accounts Payable Aging')
            ->setFilters(['As of' => $asOf])
            ->exportToPdf('reports.pdf.accounts-payable', $data);
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

    /**
     * Export Purchase by Vendor Report
     */
    public function exportPurchaseByVendor(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        $vendors = Vendor::where('tenant_id', $tenantId)
            ->whereHas('bills', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('bill_date', [$startDate, $endDate]);
            })
            ->withCount(['bills' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('bill_date', [$startDate, $endDate]);
            }])
            ->withSum(['bills' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('bill_date', [$startDate, $endDate]);
            }], 'total')
            ->withSum(['bills' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('bill_date', [$startDate, $endDate]);
            }], 'amount_paid')
            ->orderByDesc('bills_sum_total')
            ->get();

        $totalPurchases = $vendors->sum('bills_sum_total');
        $totalPaid = $vendors->sum('bills_sum_amount_paid');

        $data = compact('vendors', 'totalPurchases', 'totalPaid', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->purchaseByVendorData($vendors);
            return $this->exportService
                ->setTitle('Purchase by Vendor')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Purchase by Vendor')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.purchase-by-vendor', $data);
    }

    /**
     * Export Inventory Summary Report
     */
    public function exportInventorySummary(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $format = $request->get('format', 'pdf');

        $items = Item::where('tenant_id', $tenantId)
            ->where('track_inventory', true)
            ->with('inventory')
            ->get()
            ->map(function ($item) {
                $item->stock_quantity = $item->inventory->quantity ?? 0;
                $item->stock_value = $item->stock_quantity * $item->cost_price;
                $item->is_low_stock = $item->stock_quantity <= $item->reorder_level;
                return $item;
            });

        $totalItems = $items->count();
        $totalValue = $items->sum('stock_value');
        $lowStockItems = $items->where('is_low_stock', true)->count();

        $data = compact('items', 'totalItems', 'totalValue', 'lowStockItems');

        if ($format === 'csv') {
            $exportData = $this->exportService->inventorySummaryData($items);
            return $this->exportService
                ->setTitle('Inventory Summary')
                ->setFilters(['Generated' => now()->format('Y-m-d')])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Inventory Summary')
            ->setFilters(['Generated' => now()->format('Y-m-d')])
            ->exportToPdf('reports.pdf.inventory-summary', $data);
    }

    /**
     * Export Payroll Summary Report
     */
    public function exportPayrollSummary(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        $payrolls = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee')
            ->get();

        $totalGross = $payrolls->sum('gross_salary');
        $totalDeductions = $payrolls->sum('total_deductions');
        $totalNet = $payrolls->sum('net_salary');

        $byEmployee = $payrolls->groupBy('employee_id')->map(function ($records) {
            return [
                'employee' => $records->first()->employee,
                'gross' => $records->sum('gross_salary'),
                'deductions' => $records->sum('total_deductions'),
                'net' => $records->sum('net_salary'),
                'count' => $records->count(),
            ];
        })->values();

        $data = compact('payrolls', 'byEmployee', 'totalGross', 'totalDeductions', 'totalNet', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->payrollSummaryData($payrolls, $byEmployee);
            return $this->exportService
                ->setTitle('Payroll Summary')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Payroll Summary')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.payroll-summary', $data);
    }

    /**
     * Export Payroll by Department Report
     */
    public function exportPayrollByDepartment(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        $payrolls = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee.department')
            ->get();

        $totalGross = $payrolls->sum('gross_salary');
        $totalDeductions = $payrolls->sum('total_deductions');
        $totalNet = $payrolls->sum('net_salary');

        $byDepartment = $payrolls->groupBy(function ($payroll) {
            return $payroll->employee?->department_id ?? 0;
        })->map(function ($records) {
            $department = $records->first()->employee?->department;
            return [
                'department' => $department,
                'department_name' => $department?->name ?? 'Unassigned',
                'employee_count' => $records->unique('employee_id')->count(),
                'gross' => $records->sum('gross_salary'),
                'allowances' => $records->sum('allowances'),
                'overtime' => $records->sum('overtime_amount'),
                'tax' => $records->sum('tax_deduction'),
                'deductions' => $records->sum('total_deductions'),
                'net' => $records->sum('net_salary'),
                'records_count' => $records->count(),
            ];
        })->sortByDesc('gross')->values();

        $data = compact('byDepartment', 'totalGross', 'totalDeductions', 'totalNet', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->payrollByDepartmentData($byDepartment);
            return $this->exportService
                ->setTitle('Payroll by Department')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Payroll by Department')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.payroll-by-department', $data);
    }

    /**
     * Export Employee Earnings Report
     */
    public function exportEmployeeEarnings(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $employeeId = $request->get('employee_id');
        $format = $request->get('format', 'pdf');

        $query = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee.department');

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $payrolls = $query->orderBy('pay_date', 'desc')->get();

        $totalGross = $payrolls->sum('gross_salary');
        $totalAllowances = $payrolls->sum('allowances');
        $totalOvertime = $payrolls->sum('overtime_amount');
        $totalTax = $payrolls->sum('tax_deduction');
        $totalDeductions = $payrolls->sum('total_deductions');
        $totalNet = $payrolls->sum('net_salary');

        $data = compact('payrolls', 'totalGross', 'totalAllowances', 'totalOvertime', 'totalTax', 'totalDeductions', 'totalNet', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->employeeEarningsData($payrolls);
            return $this->exportService
                ->setTitle('Employee Earnings')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Employee Earnings')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.employee-earnings', $data);
    }

    /**
     * Export Payroll Register
     */
    public function exportPayrollRegister(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $month = $request->get('month', now()->format('Y-m'));
        $status = $request->get('status');
        $departmentId = $request->get('department_id');
        $format = $request->get('format', 'pdf');

        $startDate = \Carbon\Carbon::parse($month . '-01')->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $query = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_period_start', [$startDate, $endDate])
            ->with(['employee.department', 'salaryStructure']);

        if ($status) {
            $query->where('status', $status);
        }
        if ($departmentId) {
            $query->whereHas('employee', fn($q) => $q->where('department_id', $departmentId));
        }

        $payrolls = $query->orderBy('payroll_number')->get();

        $totals = [
            'basic_salary' => $payrolls->sum('basic_salary'),
            'allowances' => $payrolls->sum('allowances'),
            'overtime_amount' => $payrolls->sum('overtime_amount'),
            'gross_salary' => $payrolls->sum('gross_salary'),
            'tax_deduction' => $payrolls->sum('tax_deduction'),
            'other_deductions' => $payrolls->sum('other_deductions'),
            'total_deductions' => $payrolls->sum('total_deductions'),
            'net_salary' => $payrolls->sum('net_salary'),
            'employer_contributions' => $payrolls->sum('employer_contributions'),
        ];

        $data = compact('payrolls', 'totals', 'month', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->payrollRegisterData($payrolls);
            return $this->exportService
                ->setTitle('Payroll Register')
                ->setFilters(['Month' => $month])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Payroll Register')
            ->setFilters(['Month' => $month])
            ->setOrientation('landscape')
            ->exportToPdf('reports.pdf.payroll-register', $data);
    }

    /**
     * Export YTD Earnings
     */
    public function exportYtdEarnings(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $year = $request->get('year', now()->year);
        $employeeId = $request->get('employee_id');
        $format = $request->get('format', 'pdf');

        $startDate = \Carbon\Carbon::create($year, 1, 1)->startOfYear();
        $endDate = \Carbon\Carbon::create($year, 12, 31)->endOfYear();

        $query = Payroll::where('tenant_id', $tenantId)
            ->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PAID])
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee.department');

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $payrolls = $query->orderBy('pay_date')->get();

        $byEmployee = $payrolls->groupBy('employee_id')->map(function ($records) {
            $employee = $records->first()->employee;
            return [
                'employee' => $employee,
                'ytd_basic' => (float) $records->sum('basic_salary'),
                'ytd_allowances' => (float) $records->sum('allowances'),
                'ytd_overtime' => (float) $records->sum('overtime_amount'),
                'ytd_gross' => (float) $records->sum('gross_salary'),
                'ytd_tax' => (float) $records->sum('tax_deduction'),
                'ytd_total_deductions' => (float) $records->sum('total_deductions'),
                'ytd_net' => (float) $records->sum('net_salary'),
                'ytd_employer_contributions' => (float) $records->sum('employer_contributions'),
                'pay_periods' => $records->count(),
            ];
        })->sortBy(fn($r) => $r['employee']->first_name)->values();

        $grandTotals = [
            'basic' => $byEmployee->sum('ytd_basic'),
            'allowances' => $byEmployee->sum('ytd_allowances'),
            'overtime' => $byEmployee->sum('ytd_overtime'),
            'gross' => $byEmployee->sum('ytd_gross'),
            'tax' => $byEmployee->sum('ytd_tax'),
            'deductions' => $byEmployee->sum('ytd_total_deductions'),
            'net' => $byEmployee->sum('ytd_net'),
            'employer_contributions' => $byEmployee->sum('ytd_employer_contributions'),
        ];

        $data = compact('byEmployee', 'grandTotals', 'year', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->ytdEarningsData($byEmployee);
            return $this->exportService
                ->setTitle('Year-to-Date Earnings')
                ->setFilters(['Year' => $year])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Year-to-Date Earnings')
            ->setFilters(['Year' => $year])
            ->setOrientation('landscape')
            ->exportToPdf('reports.pdf.ytd-earnings', $data);
    }

    /**
     * Export Tax Liability Payroll Report
     */
    public function exportTaxLiabilityPayroll(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        $payrolls = Payroll::where('tenant_id', $tenantId)
            ->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PAID])
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee.department')
            ->orderBy('pay_date')
            ->get();

        $byEmployee = $payrolls->groupBy('employee_id')->map(function ($records) {
            $employee = $records->first()->employee;
            return [
                'employee' => $employee,
                'taxable_income' => (float) $records->sum('gross_salary'),
                'tax_deducted' => (float) $records->sum('tax_deduction'),
                'pay_periods' => $records->count(),
                'effective_rate' => $records->sum('gross_salary') > 0
                    ? round(($records->sum('tax_deduction') / $records->sum('gross_salary')) * 100, 2)
                    : 0,
            ];
        })->sortByDesc('tax_deducted')->values();

        $totals = [
            'total_taxable' => (float) $payrolls->sum('gross_salary'),
            'total_tax' => (float) $payrolls->sum('tax_deduction'),
        ];

        $data = compact('byEmployee', 'totals', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->taxLiabilityPayrollData($byEmployee);
            return $this->exportService
                ->setTitle('Tax Liability - Payroll')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Tax Liability - Payroll')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.tax-liability-payroll', $data);
    }

    /**
     * Export Employer Contributions Report
     */
    public function exportEmployerContributions(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $format = $request->get('format', 'pdf');

        $payrolls = Payroll::where('tenant_id', $tenantId)
            ->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PAID])
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee.department')
            ->get();

        $contributionTypes = [];
        foreach ($payrolls as $payroll) {
            if (!empty($payroll->employer_contribution_details)) {
                foreach ($payroll->employer_contribution_details as $detail) {
                    $name = $detail['name'] ?? 'Unknown';
                    if (!isset($contributionTypes[$name])) {
                        $contributionTypes[$name] = ['name' => $name, 'total' => 0, 'count' => 0];
                    }
                    $contributionTypes[$name]['total'] += (float) ($detail['amount'] ?? 0);
                    $contributionTypes[$name]['count']++;
                }
            }
        }
        $contributionTypes = collect($contributionTypes)->sortByDesc('total')->values();

        $byEmployee = $payrolls->where('employer_contributions', '>', 0)->groupBy('employee_id')->map(function ($records) {
            $employee = $records->first()->employee;
            return [
                'employee' => $employee,
                'gross_salary' => (float) $records->sum('gross_salary'),
                'employer_contributions' => (float) $records->sum('employer_contributions'),
            ];
        })->sortByDesc('employer_contributions')->values();

        $totals = [
            'total_gross' => (float) $payrolls->sum('gross_salary'),
            'total_employer_contributions' => (float) $payrolls->sum('employer_contributions'),
        ];

        $data = compact('contributionTypes', 'byEmployee', 'totals', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->employerContributionsData($byEmployee, $contributionTypes);
            return $this->exportService
                ->setTitle('Employer Contributions')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Employer Contributions')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.employer-contributions', $data);
    }

    /**
     * Export Bank Disbursement Report
     */
    public function exportBankDisbursement(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $status = $request->get('status', Payroll::STATUS_APPROVED);
        $format = $request->get('format', 'csv');

        $query = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee')
            ->orderBy('payroll_number');

        if ($status) {
            $query->where('status', $status);
        }

        $payrolls = $query->get();
        $totals = ['count' => $payrolls->count(), 'total_net' => (float) $payrolls->sum('net_salary')];

        $data = compact('payrolls', 'totals', 'startDate', 'endDate');

        if ($format === 'csv') {
            $exportData = $this->exportService->bankDisbursementData($payrolls);
            return $this->exportService
                ->setTitle('Bank Disbursement')
                ->setFilters(['Period' => "$startDate to $endDate"])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Bank Disbursement')
            ->setFilters(['Period' => "$startDate to $endDate"])
            ->exportToPdf('reports.pdf.bank-disbursement', $data);
    }

    /**
     * Export Salary Revision History
     */
    public function exportSalaryRevisionHistory(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $format = $request->get('format', 'pdf');

        $versions = SalaryStructureVersion::whereHas('salaryStructure', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })
            ->with(['salaryStructure', 'changedByUser'])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = compact('versions');

        if ($format === 'csv') {
            $exportData = $this->exportService->salaryRevisionHistoryData($versions);
            return $this->exportService
                ->setTitle('Salary Revision History')
                ->setFilters([])
                ->exportToCsv($exportData['rows'], $exportData['headers']);
        }

        return $this->exportService
            ->setTitle('Salary Revision History')
            ->setFilters([])
            ->exportToPdf('reports.pdf.salary-revision-history', $data);
    }

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
        if (!in_array($groupBy, ['month', 'quarter', 'year'], true)) {
            $groupBy = 'month';
        }

        // Get all tax rates for reference
        $taxRates = TaxRate::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Tax Collected (Sales Tax / Output VAT)
        $taxCollectedQuery = Invoice::select(
                DB::raw($this->getDateGrouping('invoices.invoice_date', $groupBy) . ' as period'),
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
                DB::raw($this->getDateGrouping('bills.bill_date', $groupBy) . ' as period'),
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
    private function getDateGrouping(string $column, string $groupBy): string
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
    private function formatPeriodLabel(string $period, string $groupBy): string
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

    /**
     * Get comparison periods based on type
     */
    private function getComparisonPeriods(string $type, Request $request): array
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
    private function calculatePeriodChanges(array $periodData): array
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
    private function isImprovement(string $metric, float $diff): bool
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

    /**
     * Custom Report Builder - List saved reports
     */
    public function customReportIndex()
    {
        $tenantId = auth()->user()->tenant_id;
        $userId = auth()->id();

        $myReports = CustomReport::where('tenant_id', $tenantId)
            ->where('created_by', $userId)
            ->orderBy('is_favorite', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        $sharedReports = CustomReport::where('tenant_id', $tenantId)
            ->where('is_public', true)
            ->where('created_by', '!=', $userId)
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('reports.custom.index', compact('myReports', 'sharedReports'));
    }

    /**
     * Custom Report Builder - Create form
     */
    public function customReportCreate()
    {
        $dataSources = CustomReport::getDataSources();
        $aggregations = CustomReport::getAggregations();
        $operators = CustomReport::getFilterOperators();

        return view('reports.custom.create', compact('dataSources', 'aggregations', 'operators'));
    }

    /**
     * Custom Report Builder - Store new report
     */
    public function customReportStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'data_source' => 'required|string',
            'columns' => 'required|array|min:1',
        ]);

        $customReport = CustomReport::create([
            'tenant_id' => auth()->user()->tenant_id,
            'created_by' => auth()->id(),
            'name' => $request->name,
            'description' => $request->description,
            'data_source' => $request->data_source,
            'columns' => $request->columns,
            'filters' => $request->filters ?? [],
            'group_by' => $request->group_by,
            'sort_by' => $request->sort_by,
            'aggregations' => $request->aggregations ?? [],
            'date_field' => $request->date_field,
            'is_public' => $request->boolean('is_public'),
        ]);

        return redirect()->route('reports.custom.run', $customReport)
            ->with('success', 'Custom report created successfully.');
    }

    /**
     * Custom Report Builder - Edit form
     */
    public function customReportEdit(CustomReport $customReport)
    {
        $this->authorizeReport($customReport);

        $dataSources = CustomReport::getDataSources();
        $aggregations = CustomReport::getAggregations();
        $operators = CustomReport::getFilterOperators();

        return view('reports.custom.edit', compact('customReport', 'dataSources', 'aggregations', 'operators'));
    }

    /**
     * Custom Report Builder - Update report
     */
    public function customReportUpdate(Request $request, CustomReport $customReport)
    {
        $this->authorizeReport($customReport);

        $request->validate([
            'name' => 'required|string|max:255',
            'data_source' => 'required|string',
            'columns' => 'required|array|min:1',
        ]);

        $customReport->update([
            'name' => $request->name,
            'description' => $request->description,
            'data_source' => $request->data_source,
            'columns' => $request->columns,
            'filters' => $request->filters ?? [],
            'group_by' => $request->group_by,
            'sort_by' => $request->sort_by,
            'aggregations' => $request->aggregations ?? [],
            'date_field' => $request->date_field,
            'is_public' => $request->boolean('is_public'),
        ]);

        return redirect()->route('reports.custom.run', $customReport)
            ->with('success', 'Custom report updated successfully.');
    }

    /**
     * Custom Report Builder - Delete report
     */
    public function customReportDestroy(CustomReport $customReport)
    {
        $this->authorizeReport($customReport);
        
        $customReport->delete();

        return redirect()->route('reports.custom.index')
            ->with('success', 'Custom report deleted successfully.');
    }

    /**
     * Custom Report Builder - Toggle favorite
     */
    public function customReportToggleFavorite(CustomReport $customReport)
    {
        $this->authorizeReport($customReport);
        
        $customReport->update(['is_favorite' => !$customReport->is_favorite]);

        return back()->with('success', $customReport->is_favorite ? 'Report added to favorites.' : 'Report removed from favorites.');
    }

    /**
     * Custom Report Builder - Run report
     */
    public function customReportRun(Request $request, CustomReport $customReport)
    {
        $this->authorizeReport($customReport, true);

        $tenantId = auth()->user()->tenant_id;
        $dataSources = CustomReport::getDataSources();
        $sourceConfig = $dataSources[$customReport->data_source] ?? null;

        if (!$sourceConfig) {
            return back()->with('error', 'Invalid data source.');
        }

        // Get date range from request or use defaults
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        // Build the query
        $modelClass = $sourceConfig['model'];
        $query = $modelClass::where('tenant_id', $tenantId);

        // Apply date filter if date field is set
        if ($customReport->date_field && isset($sourceConfig['date_fields']) && in_array($customReport->date_field, $sourceConfig['date_fields'])) {
            $query->whereBetween($customReport->date_field, [$startDate, $endDate]);
        }

        // Load necessary relations
        $relations = $this->extractRelations($customReport->columns, $sourceConfig['columns']);
        if (!empty($relations)) {
            $query->with($relations);
        }

        // Apply filters
        if (!empty($customReport->filters)) {
            $query = $this->applyFilters($query, $customReport->filters, $sourceConfig['columns']);
        }

        // Apply sorting
        if (!empty($customReport->sort_by)) {
            foreach ($customReport->sort_by as $sort) {
                if (isset($sort['column']) && isset($sort['direction'])) {
                    // Handle relation sorting
                    if (strpos($sort['column'], '.') !== false) {
                        // For simplicity, skip relation sorting in raw query
                        continue;
                    }
                    $query->orderBy($sort['column'], $sort['direction']);
                }
            }
        }

        // Get data
        $data = $query->get();

        // Apply grouping if needed
        $groupedData = null;
        $aggregatedData = null;
        if ($customReport->group_by) {
            $groupedData = $this->groupData($data, $customReport->group_by);
            
            // Calculate aggregations per group
            if (!empty($customReport->aggregations)) {
                $aggregatedData = $this->calculateAggregations($groupedData, $customReport->aggregations);
            }
        }

        // Calculate overall aggregations
        $totals = [];
        if (!empty($customReport->aggregations)) {
            foreach ($customReport->aggregations as $agg) {
                if (isset($agg['column']) && isset($agg['function'])) {
                    $column = $agg['column'];
                    $function = $agg['function'];
                    $value = $this->calculateSingleAggregation($data, $column, $function);
                    $totals[$column . '_' . $function] = $value;
                }
            }
        }

        // Update last run timestamp
        $customReport->update(['last_run_at' => now()]);

        return view('reports.custom.run', compact(
            'customReport', 'data', 'groupedData', 'aggregatedData', 
            'totals', 'sourceConfig', 'startDate', 'endDate'
        ));
    }

    /**
     * Get data source columns (AJAX)
     */
    public function customReportGetColumns(Request $request)
    {
        $dataSource = $request->get('data_source');
        $dataSources = CustomReport::getDataSources();

        if (!isset($dataSources[$dataSource])) {
            return response()->json(['error' => 'Invalid data source'], 400);
        }

        return response()->json([
            'columns' => $dataSources[$dataSource]['columns'],
            'date_fields' => $dataSources[$dataSource]['date_fields'],
            'group_fields' => $dataSources[$dataSource]['group_fields'],
        ]);
    }

    /**
     * Authorize access to custom report
     */
    private function authorizeReport(CustomReport $customReport, bool $allowShared = false): void
    {
        $tenantId = auth()->user()->tenant_id;
        $userId = auth()->id();

        if ($customReport->tenant_id !== $tenantId) {
            abort(403);
        }

        if (!$allowShared && $customReport->created_by !== $userId) {
            abort(403);
        }

        if ($allowShared && $customReport->created_by !== $userId && !$customReport->is_public) {
            abort(403);
        }
    }

    /**
     * Extract relations from column definitions
     */
    private function extractRelations(array $selectedColumns, array $columnConfig): array
    {
        $relations = [];
        
        foreach ($selectedColumns as $column) {
            if (isset($columnConfig[$column]['relation'])) {
                $relation = $columnConfig[$column]['relation'];
                if (!in_array($relation, $relations)) {
                    $relations[] = $relation;
                }
            }
        }

        return $relations;
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, array $filters, array $columnConfig)
    {
        foreach ($filters as $filter) {
            if (!isset($filter['column']) || !isset($filter['operator'])) {
                continue;
            }

            $column = $filter['column'];
            $operator = $filter['operator'];
            $value = $filter['value'] ?? null;

            // Skip relation columns for now
            if (strpos($column, '.') !== false) {
                continue;
            }

            switch ($operator) {
                case 'equals':
                    $query->where($column, '=', $value);
                    break;
                case 'not_equals':
                    $query->where($column, '!=', $value);
                    break;
                case 'greater_than':
                    $query->where($column, '>', $value);
                    break;
                case 'less_than':
                    $query->where($column, '<', $value);
                    break;
                case 'greater_or_equal':
                    $query->where($column, '>=', $value);
                    break;
                case 'less_or_equal':
                    $query->where($column, '<=', $value);
                    break;
                case 'contains':
                    $query->where($column, 'LIKE', "%{$value}%");
                    break;
                case 'starts_with':
                    $query->where($column, 'LIKE', "{$value}%");
                    break;
                case 'ends_with':
                    $query->where($column, 'LIKE', "%{$value}");
                    break;
                case 'is_null':
                    $query->whereNull($column);
                    break;
                case 'is_not_null':
                    $query->whereNotNull($column);
                    break;
                case 'between':
                    if (is_array($value) && count($value) === 2) {
                        $query->whereBetween($column, $value);
                    }
                    break;
                case 'in':
                    if (is_array($value)) {
                        $query->whereIn($column, $value);
                    }
                    break;
            }
        }

        return $query;
    }

    /**
     * Group data by field
     */
    private function groupData($data, string $groupBy)
    {
        return $data->groupBy(function ($item) use ($groupBy) {
            return data_get($item, $groupBy) ?? 'N/A';
        });
    }

    /**
     * Calculate aggregations for grouped data
     */
    private function calculateAggregations($groupedData, array $aggregations): array
    {
        $result = [];

        foreach ($groupedData as $groupKey => $items) {
            $result[$groupKey] = [];
            foreach ($aggregations as $agg) {
                if (isset($agg['column']) && isset($agg['function'])) {
                    $key = $agg['column'] . '_' . $agg['function'];
                    $result[$groupKey][$key] = $this->calculateSingleAggregation($items, $agg['column'], $agg['function']);
                }
            }
        }

        return $result;
    }

    /**
     * Calculate a single aggregation
     */
    private function calculateSingleAggregation($data, string $column, string $function)
    {
        $values = $data->pluck($column)->filter(function ($val) {
            return is_numeric($val);
        });

        switch ($function) {
            case 'sum':
                return $values->sum();
            case 'count':
                return $data->count();
            case 'avg':
                return $values->avg();
            case 'min':
                return $values->min();
            case 'max':
                return $values->max();
            default:
                return 0;
        }
    }
}
