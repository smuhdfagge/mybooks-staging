<?php

namespace App\Http\Controllers\Api;

use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\Payroll;
use App\Models\Vendor;
use App\Services\Accounting\FinancialStatements;
use App\Services\Reports\PayrollReportService;
use App\Services\Statements\Statement;
use App\Services\Statements\StatementBuilder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends BaseApiController
{
    /**
     * Get Profit & Loss Report
     */
    public function profitLoss(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $plData = $this->calculateProfitLossFromJournals($tenantId, $startDate, $endDate);

        return $this->success([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'revenue' => (float) $plData['revenue'],
            'cost_of_goods_sold' => (float) $plData['costOfGoodsSold'],
            'gross_profit' => (float) ($plData['revenue'] - $plData['costOfGoodsSold']),
            'operating_expenses' => (float) $plData['operatingExpenses'],
            'payroll_expenses' => (float) $plData['payrollExpenses'],
            'total_expenses' => (float) $plData['totalExpenses'],
            'net_profit' => (float) $plData['netProfit'],
        ]);
    }

    /**
     * Get Balance Sheet Report
     */
    public function balanceSheet(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $asOf = $request->get('as_of', now()->format('Y-m-d'));

        // Built from the ledger at the chosen date (A2), same as the web report.
        $bs = app(FinancialStatements::class)->balanceSheet($tenantId, $asOf);

        return $this->success([
            'as_of' => $asOf,
            'assets' => [
                'current' => [
                    'cash_and_bank' => (float) $bs['cashAndBank'],
                    'accounts_receivable' => (float) $bs['accountsReceivable'],
                    'inventory' => (float) $bs['inventory'],
                    'other_current_assets' => (float) $bs['otherCurrentAssets'],
                    'total_current_assets' => (float) $bs['totalCurrentAssets'],
                ],
                'fixed_assets' => (float) $bs['fixedAssets'],
                'total_assets' => (float) $bs['totalAssets'],
            ],
            'liabilities' => [
                'current' => [
                    'accounts_payable' => (float) $bs['accountsPayable'],
                    'credit_card_payable' => (float) $bs['creditCardPayable'],
                    'other_current_liabilities' => (float) $bs['otherCurrentLiabilities'],
                    'total_current_liabilities' => (float) $bs['totalCurrentLiabilities'],
                ],
                'long_term_liabilities' => (float) $bs['longTermLiabilities'],
                'total_liabilities' => (float) $bs['totalLiabilities'],
            ],
            'equity' => [
                'owners_equity' => (float) $bs['ownersEquity'],
                'retained_earnings' => (float) $bs['retainedEarnings'],
                'prior_years_profit' => (float) $bs['priorYearsProfit'],
                'net_income' => (float) $bs['netIncome'],
                'total_equity' => (float) $bs['totalEquity'],
            ],
            'total_liabilities_and_equity' => (float) $bs['totalLiabilitiesAndEquity'],
            'difference' => (float) $bs['difference'],
        ]);
    }

    /**
     * Get Cash Flow Report
     */
    public function cashFlow(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        // Same ledger-based figures as the web report (A7).
        $cf = app(FinancialStatements::class)->cashFlow($tenantId, $startDate, $endDate);

        return $this->success([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'beginning_cash' => (float) $cf['beginningCash'],
            'operating_activities' => [
                'inflows' => [
                    'payments_received' => (float) $cf['paymentsReceived'],
                    'total' => (float) $cf['operatingInflows'],
                ],
                'outflows' => [
                    'payments_made' => (float) $cf['paymentsMade'],
                    'expenses_paid' => (float) $cf['expensesPaid'],
                    'payroll_paid' => (float) $cf['payrollPaid'],
                    'total' => (float) $cf['operatingOutflows'],
                ],
                'net' => (float) $cf['netOperatingCashFlow'],
            ],
            'investing_activities' => [
                'fixed_asset_purchases' => (float) $cf['fixedAssetPurchases'],
                'fixed_asset_sales' => (float) $cf['fixedAssetSales'],
                'net' => (float) $cf['netInvestingCashFlow'],
            ],
            'financing_activities' => [
                'borrowings_received' => (float) $cf['borrowingsReceived'],
                'loan_repayments' => (float) $cf['loanRepayments'],
                'capital_contributions' => (float) $cf['capitalContributions'],
                'drawings' => (float) $cf['drawings'],
                'net' => (float) $cf['netFinancingCashFlow'],
            ],
            'net_cash_flow' => (float) $cf['netCashFlow'],
            'ending_cash' => (float) $cf['endingCash'],
        ]);
    }

    /**
     * Get Accounts Receivable Aging Report
     */
    public function accountsReceivable(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $asOf = $request->get('as_of', now()->format('Y-m-d'));

        // "Sent" invoices are unpaid too; they were missing (session 10). "Before
        // the next day" because SQLite keeps a time part on dates.
        $invoices = Invoice::where('tenant_id', $tenantId)
            ->where('invoice_date', '<', Carbon::parse($asOf)->addDay()->toDateString())
            ->whereIn('status', ['sent', 'unpaid', 'partial', 'overdue'])
            ->where('balance_due', '>', 0)
            ->with('customer:id,name,email')
            ->get();

        $aging = [
            'current' => 0,
            'days_1_30' => 0,
            'days_31_60' => 0,
            'days_61_90' => 0,
            'days_91_120' => 0,
            'over_120' => 0,
        ];

        $customerAging = [];

        foreach ($invoices as $invoice) {
            $dueDate = Carbon::parse($invoice->due_date);
            $asOfDate = Carbon::parse($asOf);
            $daysOverdue = $dueDate->diffInDays($asOfDate, false);
            $balance = (float) $invoice->balance_due;

            $customerId = $invoice->customer_id;
            if (! isset($customerAging[$customerId])) {
                $customerAging[$customerId] = [
                    'customer' => [
                        'id' => $invoice->customer->id,
                        'name' => $invoice->customer->name,
                        'email' => $invoice->customer->email,
                    ],
                    'current' => 0,
                    'days_1_30' => 0,
                    'days_31_60' => 0,
                    'days_61_90' => 0,
                    'days_91_120' => 0,
                    'over_120' => 0,
                    'total' => 0,
                ];
            }

            if ($daysOverdue <= 0) {
                $aging['current'] += $balance;
                $customerAging[$customerId]['current'] += $balance;
            } elseif ($daysOverdue <= 30) {
                $aging['days_1_30'] += $balance;
                $customerAging[$customerId]['days_1_30'] += $balance;
            } elseif ($daysOverdue <= 60) {
                $aging['days_31_60'] += $balance;
                $customerAging[$customerId]['days_31_60'] += $balance;
            } elseif ($daysOverdue <= 90) {
                $aging['days_61_90'] += $balance;
                $customerAging[$customerId]['days_61_90'] += $balance;
            } elseif ($daysOverdue <= 120) {
                $aging['days_91_120'] += $balance;
                $customerAging[$customerId]['days_91_120'] += $balance;
            } else {
                $aging['over_120'] += $balance;
                $customerAging[$customerId]['over_120'] += $balance;
            }

            $customerAging[$customerId]['total'] += $balance;
        }

        $total = array_sum($aging);

        return $this->success([
            'as_of' => $asOf,
            'summary' => [
                'current' => (float) $aging['current'],
                'days_1_30' => (float) $aging['days_1_30'],
                'days_31_60' => (float) $aging['days_31_60'],
                'days_61_90' => (float) $aging['days_61_90'],
                'days_91_120' => (float) $aging['days_91_120'],
                'over_120' => (float) $aging['over_120'],
                'total' => (float) $total,
            ],
            'by_customer' => array_values($customerAging),
        ]);
    }

    /**
     * Get Accounts Payable Aging Report
     */
    public function accountsPayable(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $asOf = $request->get('as_of', now()->format('Y-m-d'));

        $bills = Bill::where('tenant_id', $tenantId)
            ->where('bill_date', '<=', $asOf)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->where('balance_due', '>', 0)
            ->with('vendor:id,name,email')
            ->get();

        $aging = [
            'current' => 0,
            'days_1_30' => 0,
            'days_31_60' => 0,
            'days_61_90' => 0,
            'days_91_120' => 0,
            'over_120' => 0,
        ];

        $vendorAging = [];

        foreach ($bills as $bill) {
            $dueDate = Carbon::parse($bill->due_date);
            $asOfDate = Carbon::parse($asOf);
            $daysOverdue = $dueDate->diffInDays($asOfDate, false);
            $balance = (float) $bill->balance_due;

            $vendorId = $bill->vendor_id;
            if (! isset($vendorAging[$vendorId])) {
                $vendorAging[$vendorId] = [
                    'vendor' => [
                        'id' => $bill->vendor->id,
                        'name' => $bill->vendor->name,
                        'email' => $bill->vendor->email,
                    ],
                    'current' => 0,
                    'days_1_30' => 0,
                    'days_31_60' => 0,
                    'days_61_90' => 0,
                    'days_91_120' => 0,
                    'over_120' => 0,
                    'total' => 0,
                ];
            }

            if ($daysOverdue <= 0) {
                $aging['current'] += $balance;
                $vendorAging[$vendorId]['current'] += $balance;
            } elseif ($daysOverdue <= 30) {
                $aging['days_1_30'] += $balance;
                $vendorAging[$vendorId]['days_1_30'] += $balance;
            } elseif ($daysOverdue <= 60) {
                $aging['days_31_60'] += $balance;
                $vendorAging[$vendorId]['days_31_60'] += $balance;
            } elseif ($daysOverdue <= 90) {
                $aging['days_61_90'] += $balance;
                $vendorAging[$vendorId]['days_61_90'] += $balance;
            } elseif ($daysOverdue <= 120) {
                $aging['days_91_120'] += $balance;
                $vendorAging[$vendorId]['days_91_120'] += $balance;
            } else {
                $aging['over_120'] += $balance;
                $vendorAging[$vendorId]['over_120'] += $balance;
            }

            $vendorAging[$vendorId]['total'] += $balance;
        }

        $total = array_sum($aging);

        return $this->success([
            'as_of' => $asOf,
            'summary' => [
                'current' => (float) $aging['current'],
                'days_1_30' => (float) $aging['days_1_30'],
                'days_31_60' => (float) $aging['days_31_60'],
                'days_61_90' => (float) $aging['days_61_90'],
                'days_91_120' => (float) $aging['days_91_120'],
                'over_120' => (float) $aging['over_120'],
                'total' => (float) $total,
            ],
            'by_vendor' => array_values($vendorAging),
        ]);
    }

    /**
     * Get Sales Report
     */
    public function sales(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $customerId = $request->get('customer_id');
        $groupBy = $request->get('group_by', 'day'); // day, week, month

        $query = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate]);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $invoices = $query->get();

        $totalSales = $invoices->sum('total');
        $totalPaid = $invoices->sum('amount_paid');
        $totalOutstanding = $invoices->sum('balance_due');

        // Group by period
        $salesByPeriod = [];
        foreach ($invoices as $invoice) {
            $date = Carbon::parse($invoice->invoice_date);

            $key = match ($groupBy) {
                'week' => $date->startOfWeek()->format('Y-m-d'),
                'month' => $date->format('Y-m'),
                default => $date->format('Y-m-d'),
            };

            if (! isset($salesByPeriod[$key])) {
                $salesByPeriod[$key] = [
                    'period' => $key,
                    'total' => 0,
                    'count' => 0,
                ];
            }

            $salesByPeriod[$key]['total'] += (float) $invoice->total;
            $salesByPeriod[$key]['count']++;
        }

        // Top customers
        $topCustomers = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->with('customer:id,name')
            ->selectRaw('customer_id, SUM(total) as total_sales, COUNT(*) as invoice_count')
            ->groupBy('customer_id')
            ->orderByDesc('total_sales')
            ->take(10)
            ->get()
            ->map(fn ($item) => [
                'customer' => $item->customer ? ['id' => $item->customer->id, 'name' => $item->customer->name] : null,
                'total_sales' => (float) $item->total_sales,
                'invoice_count' => $item->invoice_count,
            ]);

        return $this->success([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => [
                'total_sales' => (float) $totalSales,
                'total_paid' => (float) $totalPaid,
                'total_outstanding' => (float) $totalOutstanding,
                'invoice_count' => $invoices->count(),
            ],
            'by_period' => array_values($salesByPeriod),
            'top_customers' => $topCustomers,
        ]);
    }

    /**
     * Get Tax Summary Report
     */
    public function taxSummary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        // Sales tax collected (from invoices)
        $salesTaxCollected = Invoice::where('tenant_id', $tenantId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->whereIn('status', ['unpaid', 'partial', 'paid'])
            ->sum('tax_amount');

        // Purchase tax paid (from bills)
        $purchaseTaxPaid = Bill::where('tenant_id', $tenantId)
            ->whereBetween('bill_date', [$startDate, $endDate])
            ->sum('tax_amount');

        // Tax on expenses
        $expenseTaxPaid = Expense::where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('tax_amount');

        $totalTaxPaid = $purchaseTaxPaid + $expenseTaxPaid;
        $netTaxLiability = $salesTaxCollected - $totalTaxPaid;

        return $this->success([
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'tax_collected' => [
                'sales_tax' => (float) $salesTaxCollected,
                'total' => (float) $salesTaxCollected,
            ],
            'tax_paid' => [
                'purchase_tax' => (float) $purchaseTaxPaid,
                'expense_tax' => (float) $expenseTaxPaid,
                'total' => (float) $totalTaxPaid,
            ],
            'net_tax_liability' => (float) $netTaxLiability,
        ]);
    }

    /**
     * Get Trial Balance Report
     */
    public function trialBalance(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $asOf = $request->get('as_of', now()->format('Y-m-d'));

        // One grouped query (P1), same figures as the web report.
        $accounts = app(FinancialStatements::class)->trialBalance($tenantId, $asOf)
            ->map(fn ($account) => [
                'id' => $account->id,
                'account_code' => $account->account_code,
                'name' => $account->name,
                'type' => $account->type,
                'total_debit' => (float) $account->total_debit,
                'total_credit' => (float) $account->total_credit,
            ])
            ->values();

        $totalDebits = $accounts->sum('total_debit');
        $totalCredits = $accounts->sum('total_credit');

        return $this->success([
            'as_of' => $asOf,
            'accounts' => $accounts,
            'totals' => [
                'total_debits' => (float) $totalDebits,
                'total_credits' => (float) $totalCredits,
                'is_balanced' => abs($totalDebits - $totalCredits) < 0.01,
            ],
        ]);
    }

    /**
     * Get General Ledger Report
     */
    public function generalLedger(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $accountId = $request->get('account_id');

        $accounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'name', 'type', 'sub_type']);

        if (! $accountId) {
            return $this->success([
                'accounts' => $accounts,
                'message' => 'Select an account_id to view ledger entries.',
            ]);
        }

        $selectedAccount = ChartOfAccount::where('tenant_id', $tenantId)->find($accountId);

        if (! $selectedAccount) {
            return $this->error('Account not found.', 404);
        }

        // Opening balance (all entries before start date)
        // Joins, not a correlated EXISTS per line (P8).
        $lines = fn () => JournalEntry::query()
            ->join('journals', 'journals.id', '=', 'journal_entries.journal_id')
            ->where('journals.tenant_id', $tenantId)
            ->where('journals.is_posted', true)
            ->whereNull('journals.deleted_at')
            ->where('journal_entries.account_id', $accountId);

        $openingEntries = $lines()
            ->where('journals.journal_date', '<', $startDate)
            ->selectRaw('SUM(journal_entries.debit) as total_debit, SUM(journal_entries.credit) as total_credit')
            ->first();

        $totalDebit = $openingEntries->total_debit ?? 0;
        $totalCredit = $openingEntries->total_credit ?? 0;
        $openingBalance = $selectedAccount->isDebitBalance()
            ? $totalDebit - $totalCredit
            : $totalCredit - $totalDebit;

        // Entries within the selected period
        $entries = $lines()
            ->where('journals.journal_date', '>=', $startDate)
            ->where('journals.journal_date', '<', Carbon::parse($endDate)->addDay()->toDateString())
            ->select('journal_entries.*')
            ->with(['journal:id,journal_number,journal_date,description'])
            ->orderBy('journals.journal_date')
            ->orderBy('journals.id')
            ->orderBy('journal_entries.id')
            ->get()
            ->map(fn ($entry) => [
                'date' => $entry->journal->journal_date?->format('Y-m-d'),
                'journal_number' => $entry->journal->journal_number,
                'description' => $entry->description ?? $entry->journal->description,
                'debit' => (float) $entry->debit,
                'credit' => (float) $entry->credit,
            ]);

        $closingBalance = $selectedAccount->isDebitBalance()
            ? $openingBalance + $entries->sum('debit') - $entries->sum('credit')
            : $openingBalance + $entries->sum('credit') - $entries->sum('debit');

        return $this->success([
            'account' => [
                'id' => $selectedAccount->id,
                'account_code' => $selectedAccount->account_code,
                'name' => $selectedAccount->name,
                'type' => $selectedAccount->type,
            ],
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'opening_balance' => (float) $openingBalance,
            'entries' => $entries,
            'closing_balance' => (float) $closingBalance,
        ]);
    }

    /**
     * Get Sales by Customer Report
     */
    public function salesByCustomer(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $customers = Customer::where('tenant_id', $tenantId)
            ->whereHas('invoices', fn ($q) => $q->whereBetween('invoice_date', [$startDate, $endDate]))
            ->withCount(['invoices' => fn ($q) => $q->whereBetween('invoice_date', [$startDate, $endDate])])
            ->withSum(['invoices' => fn ($q) => $q->whereBetween('invoice_date', [$startDate, $endDate])], 'total')
            ->withSum(['invoices' => fn ($q) => $q->whereBetween('invoice_date', [$startDate, $endDate])], 'amount_paid')
            ->orderByDesc('invoices_sum_total')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'invoice_count' => $c->invoices_count,
                'total_sales' => (float) ($c->invoices_sum_total ?? 0),
                'total_paid' => (float) ($c->invoices_sum_amount_paid ?? 0),
                'outstanding' => (float) (($c->invoices_sum_total ?? 0) - ($c->invoices_sum_amount_paid ?? 0)),
            ]);

        return $this->success([
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'customers' => $customers,
            'totals' => [
                'total_sales' => (float) $customers->sum('total_sales'),
                'total_paid' => (float) $customers->sum('total_paid'),
                'total_outstanding' => (float) $customers->sum('outstanding'),
            ],
        ]);
    }

    /**
     * Get Sales by Item Report
     */
    public function salesByItem(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
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
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity_sold' => (int) $item->invoiceItems->sum('quantity'),
                'total_sales' => (float) $item->invoiceItems->sum('total'),
            ])
            ->filter(fn ($item) => $item['quantity_sold'] > 0)
            ->sortByDesc('total_sales')
            ->values();

        return $this->success([
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'items' => $items,
            'totals' => [
                'total_quantity' => (int) $items->sum('quantity_sold'),
                'total_sales' => (float) $items->sum('total_sales'),
            ],
        ]);
    }

    /**
     * Get Purchases by Vendor Report
     */
    public function purchaseByVendor(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $vendors = Vendor::where('tenant_id', $tenantId)
            ->whereHas('bills', fn ($q) => $q->whereBetween('bill_date', [$startDate, $endDate]))
            ->withCount(['bills' => fn ($q) => $q->whereBetween('bill_date', [$startDate, $endDate])])
            ->withSum(['bills' => fn ($q) => $q->whereBetween('bill_date', [$startDate, $endDate])], 'total')
            ->withSum(['bills' => fn ($q) => $q->whereBetween('bill_date', [$startDate, $endDate])], 'amount_paid')
            ->orderByDesc('bills_sum_total')
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'bill_count' => $v->bills_count,
                'total_purchases' => (float) ($v->bills_sum_total ?? 0),
                'total_paid' => (float) ($v->bills_sum_amount_paid ?? 0),
                'outstanding' => (float) (($v->bills_sum_total ?? 0) - ($v->bills_sum_amount_paid ?? 0)),
            ]);

        return $this->success([
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'vendors' => $vendors,
            'totals' => [
                'total_purchases' => (float) $vendors->sum('total_purchases'),
                'total_paid' => (float) $vendors->sum('total_paid'),
                'total_outstanding' => (float) $vendors->sum('outstanding'),
            ],
        ]);
    }

    /**
     * Get Inventory Summary Report
     */
    public function inventorySummary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $items = Item::where('tenant_id', $tenantId)
            ->where('track_inventory', true)
            ->with('inventory')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'stock_quantity' => (int) ($item->inventory->quantity ?? 0),
                'cost_price' => (float) $item->cost_price,
                'stock_value' => (float) (($item->inventory->quantity ?? 0) * $item->cost_price),
                'reorder_level' => (int) $item->reorder_level,
                'is_low_stock' => ($item->inventory->quantity ?? 0) <= $item->reorder_level,
            ]);

        return $this->success([
            'items' => $items->values(),
            'summary' => [
                'total_items' => $items->count(),
                'total_value' => (float) $items->sum('stock_value'),
                'low_stock_count' => $items->where('is_low_stock', true)->count(),
            ],
        ]);
    }

    /**
     * Get Payroll Summary Report
     */
    public function payrollSummary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $payrolls = Payroll::where('tenant_id', $tenantId)
            ->whereBetween('pay_date', [$startDate, $endDate])
            ->with('employee:id,first_name,last_name')
            ->get();

        $byEmployee = $payrolls->groupBy('employee_id')->map(function ($records) {
            $employee = $records->first()->employee;

            return [
                'employee_id' => $records->first()->employee_id,
                'employee_name' => $employee ? ($employee->first_name.' '.$employee->last_name) : 'N/A',
                'gross_salary' => (float) $records->sum('gross_salary'),
                'total_deductions' => (float) $records->sum('total_deductions'),
                'net_salary' => (float) $records->sum('net_salary'),
                'pay_count' => $records->count(),
            ];
        })->values();

        return $this->success([
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'by_employee' => $byEmployee,
            'totals' => [
                'total_gross' => (float) $payrolls->sum('gross_salary'),
                'total_deductions' => (float) $payrolls->sum('total_deductions'),
                'total_net' => (float) $payrolls->sum('net_salary'),
                'payroll_count' => $payrolls->count(),
            ],
        ]);
    }

    /**
     * Payroll register for one month (?month=YYYY-MM&status=&department_id=).
     */
    public function payrollRegister(Request $request): JsonResponse
    {
        $request->validate(['month' => 'nullable|date_format:Y-m']);
        $month = $request->get('month', now()->format('Y-m'));
        $data = $this->payrollReports()->payrollRegister($this->getTenantId(), $month, $request->get('status'), $request->get('department_id'));

        return $this->success([
            'period' => ['month' => $month, 'start_date' => $data['startDate']->toDateString(), 'end_date' => $data['endDate']->toDateString()],
            'payrolls' => $data['payrolls']->map(fn ($p) => [
                'payroll_number' => $p->payroll_number,
                'employee' => $this->employeeSummary($p->employee),
                'status' => $p->status,
                'pay_period_start' => $p->pay_period_start?->toDateString(),
                'pay_date' => $p->pay_date?->toDateString(),
                'basic_salary' => (float) $p->basic_salary,
                'allowances' => (float) $p->allowances,
                'overtime_amount' => (float) $p->overtime_amount,
                'gross_salary' => (float) $p->gross_salary,
                'tax_deduction' => (float) $p->tax_deduction,
                'other_deductions' => (float) $p->other_deductions,
                'total_deductions' => (float) $p->total_deductions,
                'net_salary' => (float) $p->net_salary,
                'employer_contributions' => (float) $p->employer_contributions,
            ])->values(),
            'totals' => array_map('floatval', $data['totals']),
            'status_counts' => $data['statusCounts'],
        ]);
    }

    /**
     * Year-to-date earnings per employee (?year=&employee_id=).
     */
    public function ytdEarnings(Request $request): JsonResponse
    {
        $request->validate(['year' => 'nullable|integer|min:2000|max:2100']);
        $year = (int) $request->get('year', now()->year);
        $data = $this->payrollReports()->ytdEarnings($this->getTenantId(), $year, $request->get('employee_id'));

        return $this->success([
            'year' => $year,
            'by_employee' => $data['byEmployee']->map(fn ($row) => ['employee' => $this->employeeSummary($row['employee'])]
                + collect($row)->except('employee')->all())->values(),
            'totals' => $data['grandTotals'],
        ]);
    }

    /**
     * Payroll tax deducted, for filing (?start_date=&end_date=).
     */
    public function taxLiabilityPayroll(Request $request): JsonResponse
    {
        [$start, $end] = $this->payrollPeriod($request);
        $data = $this->payrollReports()->taxLiability($this->getTenantId(), $start, $end);

        return $this->success([
            'period' => ['start_date' => $start, 'end_date' => $end],
            'by_employee' => $this->withEmployeeSummary($data['byEmployee']),
            'by_month' => $data['monthlyBreakdown'],
            'by_department' => $data['byDepartment'],
            'totals' => $data['totals'],
        ]);
    }

    /**
     * Employer contributions (?start_date=&end_date=).
     */
    public function employerContributions(Request $request): JsonResponse
    {
        [$start, $end] = $this->payrollPeriod($request);
        $data = $this->payrollReports()->employerContributions($this->getTenantId(), $start, $end);

        return $this->success([
            'period' => ['start_date' => $start, 'end_date' => $end],
            'by_type' => $data['contributionTypes'],
            'by_employee' => $this->withEmployeeSummary($data['byEmployee']),
            'by_month' => $data['monthlyTrend'],
            'totals' => $data['totals'],
        ]);
    }

    /**
     * Net pay to disburse (?start_date=&end_date=&status=approved&payment_method=).
     * Includes the employee's bank details, so it needs the payroll permission too.
     */
    public function bankDisbursement(Request $request): JsonResponse
    {
        if (! $request->user()->can('view payroll')) {
            return $this->forbidden('You need the view payroll permission to see bank details.');
        }

        [$start, $end] = $this->payrollPeriod($request);
        $status = $request->get('status', Payroll::STATUS_APPROVED);
        $data = $this->payrollReports()->bankDisbursement($this->getTenantId(), $start, $end, $status, $request->get('payment_method'));

        return $this->success([
            'period' => ['start_date' => $start, 'end_date' => $end],
            'status' => $status,
            'payments' => $data['payrolls']->map(fn ($p) => [
                'payroll_number' => $p->payroll_number,
                'employee' => $this->employeeSummary($p->employee),
                'bank_name' => $p->employee?->bank_name,
                'bank_account_number' => $p->employee?->bank_account_number,
                'payment_method' => $p->payment_method,
                'pay_date' => $p->pay_date?->toDateString(),
                'net_salary' => (float) $p->net_salary,
            ])->values(),
            'by_payment_method' => $data['byPaymentMethod'],
            'totals' => $data['totals'],
        ]);
    }

    /**
     * Salary structure changes, plus one employee's pay by month (?employee_id=).
     */
    public function salaryRevisionHistory(Request $request): JsonResponse
    {
        $data = $this->payrollReports()->salaryRevisionHistory($this->getTenantId(), $request->get('employee_id'));

        return $this->success([
            'versions' => $data['versions']->map(fn ($v) => [
                'id' => $v->id,
                'salary_structure' => $v->salaryStructure?->name,
                'version' => $v->version,
                'basic_salary' => (float) $v->basic_salary,
                'effective_from' => $v->effective_from ? \Illuminate\Support\Carbon::parse($v->effective_from)->toDateString() : null,
                'changed_by' => $v->changedByUser?->name,
                'changed_at' => $v->created_at?->toIso8601String(),
                'reason' => $v->change_reason,
            ])->values(),
            'employee' => $this->employeeSummary($data['selectedEmployee']),
            'salary_progression' => $data['salaryProgression'],
        ]);
    }

    private function payrollReports(): PayrollReportService
    {
        return app(PayrollReportService::class);
    }

    /** @return array{0: string, 1: string} */
    private function payrollPeriod(Request $request): array
    {
        $request->validate(['start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date']);

        return [
            $request->get('start_date', now()->startOfMonth()->format('Y-m-d')),
            $request->get('end_date', now()->format('Y-m-d')),
        ];
    }

    /** Name and department only; never salary or ID details. */
    private function employeeSummary($employee): ?array
    {
        if (! $employee) {
            return null;
        }

        return [
            'id' => $employee->id,
            'employee_id' => $employee->employee_id,
            'name' => trim($employee->first_name.' '.$employee->last_name),
            'department' => $employee->department?->name,
        ];
    }

    private function withEmployeeSummary($rows)
    {
        return $rows->map(fn ($row) => ['employee' => $this->employeeSummary($row['employee'])]
            + collect($row)->except('employee')->all())->values();
    }

    /**
     * Get Customer Statement Report
     */
    public function customerStatement(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $customerId = $request->get('customer_id');
        $startDate = $request->get('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        if (! $customerId) {
            $customers = Customer::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'email']);

            return $this->success([
                'customers' => $customers,
                'message' => 'Provide customer_id to view statement.',
            ]);
        }

        $customer = Customer::where('tenant_id', $tenantId)->where('id', $customerId)->first();

        if (! $customer) {
            return $this->error('Customer not found.', 404);
        }

        // Same figures as the statement page (session 10): the old opening
        // balance used today's balances of older invoices, so a payment in
        // the period for an older invoice was counted twice, and drafts,
        // cancelled invoices, credit notes and WHT were handled wrongly.
        $statement = app(StatementBuilder::class)->build($customer, Statement::ACTIVITY, $startDate, $endDate);
        $transactions = collect($statement->rows)->map(fn ($r) => [
            'date' => $r['date'],
            'type' => $r['kind'],
            'reference' => $r['reference'],
            'description' => $r['label'],
            'debit' => $r['charge'],
            'credit' => $r['credit'],
            'balance' => $r['balance'],
        ]);
        $openingBalance = $statement->opening;
        $totalInvoices = $statement->totalCharges;
        $totalPayments = $statement->totalCredits;
        $closingBalance = $statement->closing;

        return $this->success([
            'customer' => ['id' => $customer->id, 'name' => $customer->name, 'email' => $customer->email],
            'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            'opening_balance' => (float) $openingBalance,
            'transactions' => $transactions,
            'totals' => [
                'total_invoices' => (float) $totalInvoices,
                'total_payments' => (float) $totalPayments,
                'closing_balance' => (float) $closingBalance,
            ],
        ]);
    }

    /**
     * Profit and loss from the ledger, without year-end closing journals
     * (A8). See App\Services\Accounting\FinancialStatements.
     */
    protected function calculateProfitLossFromJournals(int $tenantId, string $startDate, string $endDate): array
    {
        return app(FinancialStatements::class)->profitAndLoss($tenantId, $startDate, $endDate);
    }

    /**
     * This financial year's profit up to $asOf, as shown on the balance
     * sheet (A2): from the business's own financial-year start.
     */
    protected function calculateNetIncomeForBalanceSheet(int $tenantId, string $asOf): float
    {
        return app(FinancialStatements::class)->balanceSheet($tenantId, $asOf)['netIncome'];
    }

    /**
     * Calculate cash balance
     */
    protected function calculateCashBalance(int $tenantId, string $date, bool $beforeDate = false): float
    {
        $operator = $beforeDate ? '<' : '<=';

        $cashData = JournalEntry::whereHas('journal', function ($query) use ($tenantId, $date, $operator) {
            $query->where('tenant_id', $tenantId)
                ->where('journal_date', $operator, $date)
                ->where('is_posted', true);
        })
            ->whereHas('account', fn ($q) => $q->where('type', 'asset')->whereIn('sub_type', ['cash', 'bank']))
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        return ($cashData->total_debit ?? 0) - ($cashData->total_credit ?? 0);
    }

    /**
     * Get journal entry sum by account type
     */
    protected function getJournalSum(int $tenantId, string $startDate, string $endDate, string $type, string $subType, string $column): float
    {
        return JournalEntry::whereHas('journal', function ($query) use ($tenantId, $startDate, $endDate) {
            $query->where('tenant_id', $tenantId)
                ->whereBetween('journal_date', [$startDate, $endDate])
                ->where('is_posted', true);
        })
            ->whereHas('account', fn ($q) => $q->where('type', $type)->where('sub_type', $subType))
            ->sum($column);
    }
}
