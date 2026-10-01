<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\Tenant;
use App\Services\AccountCodeService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Profit and loss and balance sheet, both built from the ledger
 * (findings A2 and A8).
 *
 * Every figure is a sum of posted journal lines in the date range, plus
 * the account's opening balance when the range starts at the beginning.
 * Reversed journals stay posted, and their reversal cancels them, so both
 * are counted. Deleted and draft journals are not.
 *
 * Year-end closing journals move the year's profit into retained earnings.
 * The profit and loss leaves them out (so a closed year still shows its
 * profit); the balance sheet keeps them (so profit isn't counted twice).
 */
class FinancialStatements
{
    /** Balance-sheet groups: key => [account type, sub-types]. Anything else falls into the type's "other" group. */
    public const ASSET_GROUPS = [
        'cash' => ['cash', 'bank'],
        'accounts_receivable' => ['accounts_receivable'],
        'inventory' => ['inventory'],
        'fixed' => ['fixed_asset', 'accumulated_depreciation'],
        // other_current: everything else
    ];

    public const LIABILITY_GROUPS = [
        'accounts_payable' => ['accounts_payable'],
        'credit_card' => ['credit_card'],
        'long_term' => ['long_term_liability'],
        // other_current: everything else
    ];

    public const EQUITY_GROUPS = [
        'retained_earnings' => ['retained_earnings'],
        // capital: everything else (owner's capital, drawings, income summary)
    ];

    /**
     * Balance of every account from the ledger, in its natural direction
     * (debit-balance accounts: debits minus credits; the rest: credits
     * minus debits).
     *
     * @param  string|null  $from  first date, or null for "since the beginning" (adds opening balances)
     * @return Collection<int, ChartOfAccount> accounts with ->ledger_debit, ->ledger_credit and ->balance set
     */
    public function accountBalances(int $tenantId, ?string $from, string $to, bool $includeClosing = true): Collection
    {
        $totals = DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->where('j.tenant_id', $tenantId)
            ->where('j.is_posted', true)
            ->whereNull('j.deleted_at')
            // "Before the next day", not "<= $to": SQLite stores dates with a
            // time part, so "2025-12-31 00:00:00" <= "2025-12-31" is false there.
            ->where('j.journal_date', '<', Carbon::parse($to)->addDay()->toDateString())
            ->when($from, fn ($q) => $q->where('j.journal_date', '>=', $from))
            ->when(! $includeClosing, fn ($q) => $q->where(fn ($w) => $w->whereNull('j.journal_type')->orWhere('j.journal_type', '!=', Journal::TYPE_CLOSING)))
            ->groupBy('je.account_id')
            ->selectRaw('je.account_id, SUM(je.debit) as debit, SUM(je.credit) as credit')
            ->get()
            ->keyBy('account_id');

        return ChartOfAccount::withTrashed()
            ->where('tenant_id', $tenantId)
            ->orderBy('account_code')
            ->get()
            ->map(function (ChartOfAccount $account) use ($totals, $from) {
                $row = $totals->get($account->id);
                $debit = round((float) ($row->debit ?? 0), 2);
                $credit = round((float) ($row->credit ?? 0), 2);
                $net = $account->isDebitBalance() ? $debit - $credit : $credit - $debit;
                if ($from === null) {
                    $net += (float) ($account->opening_balance ?? 0);
                }

                $account->setAttribute('ledger_debit', $debit);
                $account->setAttribute('ledger_credit', $credit);
                $account->setAttribute('balance', round($net, 2));

                return $account;
            })
            ->filter(fn ($a) => ! $a->trashed() || abs($a->balance) >= 0.005)
            ->values();
    }

    /**
     * Trial balance at $asOf: total debits and credits per account from one
     * grouped query (finding P1; it used to load every journal line of
     * every account into memory).
     *
     * @return Collection<int, ChartOfAccount> accounts with ->total_debit and ->total_credit
     */
    public function trialBalance(int $tenantId, string $asOf): Collection
    {
        return $this->accountBalances($tenantId, '1900-01-01', $asOf)
            ->filter(fn ($a) => $a->ledger_debit > 0 || $a->ledger_credit > 0)
            ->each(function ($a) {
                $a->setAttribute('total_debit', $a->ledger_debit);
                $a->setAttribute('total_credit', $a->ledger_credit);
            })
            ->values();
    }

    /**
     * Profit and loss for a date range, without closing journals (A8).
     *
     * @return array{revenue: float, costOfGoodsSold: float, operatingExpenses: float, payrollExpenses: float, totalExpenses: float, netProfit: float}
     */
    public function profitAndLoss(int $tenantId, string $from, string $to): array
    {
        $accounts = $this->accountBalances($tenantId, $from, $to, includeClosing: false);

        $income = $accounts->where('type', ChartOfAccount::TYPE_INCOME);
        $expenses = $accounts->where('type', ChartOfAccount::TYPE_EXPENSE);

        $revenue = round($income->sum('balance'), 2);
        $totalExpenses = round($expenses->sum('balance'), 2);
        $cogs = round($expenses->where('sub_type', 'cost_of_goods_sold')->sum('balance'), 2);
        $payroll = round($expenses
            ->where('sub_type', '!=', 'cost_of_goods_sold')
            ->filter(fn ($a) => preg_match('/salar|wage|payroll/i', (string) $a->name))
            ->sum('balance'), 2);

        return [
            'revenue' => $revenue,
            'costOfGoodsSold' => $cogs,
            'operatingExpenses' => round($totalExpenses - $cogs - $payroll, 2),
            'payrollExpenses' => $payroll,
            'totalExpenses' => $totalExpenses,
            'netProfit' => round($revenue - $totalExpenses, 2),
        ];
    }

    /**
     * Start of the business's financial year that contains $date. Uses the
     * month and day of the tenant's fiscal_year_start (default 1 January).
     */
    public function financialYearStart(int $tenantId, string $date): Carbon
    {
        $day = Carbon::parse($date)->startOfDay();
        $setting = Tenant::find($tenantId)?->fiscal_year_start;
        $month = $setting ? (int) $setting->format('n') : 1;
        $dayOfMonth = $setting ? (int) $setting->format('j') : 1;

        $start = Carbon::create($day->year, $month, min($dayOfMonth, 28))->startOfDay();
        if ($dayOfMonth > 28) {
            $start->day(min($dayOfMonth, $start->daysInMonth));
        }

        return $start->greaterThan($day) ? $start->subYear() : $start;
    }

    /**
     * Balance sheet at the end of $asOf (A2). Returns the totals the views
     * and exports already use, plus:
     *  - priorYearsProfit: profit of earlier years not yet closed into
     *    retained earnings (so the sheet balances without a close)
     *  - difference: assets minus liabilities and equity; must be zero
     *  - details: accounts per group, with current_balance set to the
     *    balance at $asOf (for the expandable rows)
     *
     * @return array<string, mixed>
     */
    public function balanceSheet(int $tenantId, string $asOf): array
    {
        $accounts = $this->accountBalances($tenantId, null, $asOf);
        foreach ($accounts as $account) {
            // The views show current_balance; make it the balance at $asOf.
            $account->setAttribute('current_balance', $account->balance);
        }

        $fyStart = $this->financialYearStart($tenantId, $asOf);
        $dayBefore = $fyStart->copy()->subDay()->toDateString();

        // Income and expense accounts hold profit not yet closed. Split it
        // into this financial year and earlier years.
        $unclosedTotal = $this->profitFrom($accounts);
        $priorYears = $this->profitFrom($this->accountBalances($tenantId, null, $dayBefore));
        $currentYear = round($unclosedTotal - $priorYears, 2);

        $group = function (string $type, array $groups, string $fallback) use ($accounts): array {
            $ofType = $accounts->where('type', $type)->filter(fn ($a) => abs($a->balance) >= 0.005 || $a->is_active);
            $out = [];
            $known = [];
            foreach ($groups as $key => $subTypes) {
                $out[$key] = $ofType->filter(fn ($a) => in_array($a->sub_type, $subTypes, true))->values();
                $known = array_merge($known, $subTypes);
            }
            $out[$fallback] = $ofType->filter(fn ($a) => ! in_array($a->sub_type, $known, true))->values();

            return $out;
        };

        $assets = $group(ChartOfAccount::TYPE_ASSET, self::ASSET_GROUPS, 'other_current');
        $liabilities = $group(ChartOfAccount::TYPE_LIABILITY, self::LIABILITY_GROUPS, 'other_current');
        $equity = $group(ChartOfAccount::TYPE_EQUITY, self::EQUITY_GROUPS, 'capital');

        $sum = fn (Collection $c) => round($c->sum('balance'), 2);

        $cashAndBank = $sum($assets['cash']);
        $accountsReceivable = $sum($assets['accounts_receivable']);
        $inventory = $sum($assets['inventory']);
        $otherCurrentAssets = $sum($assets['other_current']);
        $fixedAssets = $sum($assets['fixed']);
        $totalCurrentAssets = round($cashAndBank + $accountsReceivable + $inventory + $otherCurrentAssets, 2);
        $totalAssets = round($totalCurrentAssets + $fixedAssets, 2);

        $accountsPayable = $sum($liabilities['accounts_payable']);
        $creditCardPayable = $sum($liabilities['credit_card']);
        $otherCurrentLiabilities = $sum($liabilities['other_current']);
        $longTermLiabilities = $sum($liabilities['long_term']);
        $totalCurrentLiabilities = round($accountsPayable + $creditCardPayable + $otherCurrentLiabilities, 2);
        $totalLiabilities = round($totalCurrentLiabilities + $longTermLiabilities, 2);

        $ownersEquity = $sum($equity['capital']);
        $retainedEarnings = $sum($equity['retained_earnings']);
        $totalEquity = round($ownersEquity + $retainedEarnings + $priorYears + $currentYear, 2);
        $totalLiabilitiesAndEquity = round($totalLiabilities + $totalEquity, 2);

        return [
            'asOf' => $asOf,
            'fiscalYearStart' => $fyStart->toDateString(),
            'cashAndBank' => $cashAndBank,
            'accountsReceivable' => $accountsReceivable,
            'inventory' => $inventory,
            'otherCurrentAssets' => $otherCurrentAssets,
            'fixedAssets' => $fixedAssets,
            'totalCurrentAssets' => $totalCurrentAssets,
            'totalAssets' => $totalAssets,
            'accountsPayable' => $accountsPayable,
            'creditCardPayable' => $creditCardPayable,
            'otherCurrentLiabilities' => $otherCurrentLiabilities,
            'longTermLiabilities' => $longTermLiabilities,
            'totalCurrentLiabilities' => $totalCurrentLiabilities,
            'totalLiabilities' => $totalLiabilities,
            'ownersEquity' => $ownersEquity,
            'retainedEarnings' => $retainedEarnings,
            'priorYearsProfit' => $priorYears,
            'netIncome' => $currentYear,
            'totalEquity' => $totalEquity,
            'totalLiabilitiesAndEquity' => $totalLiabilitiesAndEquity,
            'difference' => round($totalAssets - $totalLiabilitiesAndEquity, 2),
            'assetDetails' => $assets,
            'liabilityDetails' => $liabilities,
            'equityDetails' => $equity,
        ];
    }

    /**
     * Cash flow statement from the ledger (finding A7).
     *
     * Only journals that touch a cash or bank account count. In such a
     * journal, every other line's (credit - debit) is the cash it brought in
     * (positive) or paid out (negative), so the lines add up exactly to the
     * change in cash and closing cash always equals the cash accounts. Each
     * line is classified by its account:
     *  - investing: fixed assets and accumulated depreciation
     *  - financing: long-term liabilities and equity
     *  - operating: everything else, split into customers, suppliers,
     *    payroll and other expenses
     *
     * Journals without a cash line (invoices, depreciation, applying a
     * customer deposit, the year-end close) move no cash and are left out.
     *
     * @return array<string, float|string>
     */
    public function cashFlow(int $tenantId, string $from, string $to): array
    {
        $isCash = fn ($a) => $a->type === ChartOfAccount::TYPE_ASSET && in_array($a->sub_type, ['cash', 'bank'], true);
        $accounts = ChartOfAccount::withTrashed()->where('tenant_id', $tenantId)->get()->keyBy('id');
        $cashIds = $accounts->filter($isCash)->keys()->all();

        $payrollCodes = collect(['accrued_salaries', 'payroll_liabilities', 'tax_payable', 'pension_payable', 'insurance_payable', 'union_dues_payable', 'garnishments_payable', 'net_pay_payable'])
            ->map(function ($key) use ($tenantId) {
                try {
                    return AccountCodeService::resolve($tenantId, $key);
                } catch (\InvalidArgumentException) {
                    return null;
                }
            })->filter()->all();

        $depositCode = AccountCodeService::resolve($tenantId, 'customer_deposits');

        $totals = [
            'customers' => 0.0, 'suppliers' => 0.0, 'payroll' => 0.0, 'other' => 0.0,
            'investIn' => 0.0, 'investOut' => 0.0,
            'borrowIn' => 0.0, 'borrowOut' => 0.0, 'capitalIn' => 0.0, 'capitalOut' => 0.0,
        ];

        $end = Carbon::parse($to)->addDay()->toDateString();
        DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->where('j.tenant_id', $tenantId)
            ->where('j.is_posted', true)
            ->whereNull('j.deleted_at')
            ->where('j.journal_date', '>=', $from)
            ->where('j.journal_date', '<', $end)
            ->whereNotIn('je.account_id', $cashIds ?: [0])
            // Journals that touch a cash account, as a join rather than a
            // correlated EXISTS run for every line (P8).
            ->joinSub(
                DB::table('journal_entries')->select('journal_id')->whereIn('account_id', $cashIds ?: [0])->distinct(),
                'cash',
                'cash.journal_id',
                '=',
                'je.journal_id'
            )
            ->select('je.account_id', 'je.debit', 'je.credit')
            ->orderBy('je.id')
            ->lazy(1000)
            ->each(function ($line) use ($accounts, $payrollCodes, $depositCode, &$totals) {
                $account = $accounts->get($line->account_id);
                if (! $account) {
                    return;
                }
                $cash = round((float) $line->credit - (float) $line->debit, 2);
                if ($cash == 0.0) {
                    return;
                }

                $sub = (string) $account->sub_type;
                if ($account->type === ChartOfAccount::TYPE_ASSET && in_array($sub, ['fixed_asset', 'accumulated_depreciation'], true)) {
                    $cash > 0 ? $totals['investIn'] += $cash : $totals['investOut'] -= $cash;
                } elseif ($account->type === ChartOfAccount::TYPE_LIABILITY && $sub === 'long_term_liability') {
                    $cash > 0 ? $totals['borrowIn'] += $cash : $totals['borrowOut'] -= $cash;
                } elseif ($account->type === ChartOfAccount::TYPE_EQUITY) {
                    $cash > 0 ? $totals['capitalIn'] += $cash : $totals['capitalOut'] -= $cash;
                } elseif ($account->type === ChartOfAccount::TYPE_INCOME
                    || in_array($sub, ['accounts_receivable'], true)
                    || $account->account_code === $depositCode) {
                    $totals['customers'] += $cash;
                } elseif (in_array($sub, ['accounts_payable', 'inventory', 'cost_of_goods_sold'], true)) {
                    $totals['suppliers'] += $cash;
                } elseif (in_array($account->account_code, $payrollCodes, true)
                    || ($account->type === ChartOfAccount::TYPE_EXPENSE && preg_match('/salar|wage|payroll/i', (string) $account->name))) {
                    $totals['payroll'] += $cash;
                } else {
                    $totals['other'] += $cash;
                }
            });

        $totals = array_map(fn ($v) => round($v, 2), $totals);
        $beginningCash = round($this->accountBalances($tenantId, null, Carbon::parse($from)->subDay()->toDateString())->filter($isCash)->sum('balance'), 2);

        $paymentsReceived = $totals['customers'];
        $paymentsMade = -$totals['suppliers'];
        $payrollPaid = -$totals['payroll'];
        $expensesPaid = -$totals['other'];
        $operatingInflows = $paymentsReceived;
        $operatingOutflows = round($paymentsMade + $expensesPaid + $payrollPaid, 2);
        $netOperatingCashFlow = round($operatingInflows - $operatingOutflows, 2);

        $fixedAssetPurchases = $totals['investOut'];
        $fixedAssetSales = $totals['investIn'];
        $netInvestingCashFlow = round($fixedAssetSales - $fixedAssetPurchases, 2);

        $borrowingsReceived = $totals['borrowIn'];
        $loanRepayments = $totals['borrowOut'];
        $capitalContributions = $totals['capitalIn'];
        $drawings = $totals['capitalOut'];
        $netFinancingCashFlow = round($borrowingsReceived - $loanRepayments + $capitalContributions - $drawings, 2);

        $netCashFlow = round($netOperatingCashFlow + $netInvestingCashFlow + $netFinancingCashFlow, 2);

        return [
            'startDate' => $from,
            'endDate' => $to,
            'paymentsReceived' => $paymentsReceived,
            'paymentsMade' => $paymentsMade,
            'expensesPaid' => $expensesPaid,
            'payrollPaid' => $payrollPaid,
            'operatingInflows' => $operatingInflows,
            'operatingOutflows' => $operatingOutflows,
            'netOperatingCashFlow' => $netOperatingCashFlow,
            'fixedAssetPurchases' => $fixedAssetPurchases,
            'fixedAssetSales' => $fixedAssetSales,
            'netInvestingCashFlow' => $netInvestingCashFlow,
            'borrowingsReceived' => $borrowingsReceived,
            'loanRepayments' => $loanRepayments,
            'capitalContributions' => $capitalContributions,
            'drawings' => $drawings,
            'netFinancingCashFlow' => $netFinancingCashFlow,
            'totalInflows' => round($paymentsReceived + $fixedAssetSales + $borrowingsReceived + $capitalContributions, 2),
            'totalOutflows' => round($operatingOutflows + $fixedAssetPurchases + $loanRepayments + $drawings, 2),
            'netCashFlow' => $netCashFlow,
            'beginningCash' => $beginningCash,
            'endingCash' => round($beginningCash + $netCashFlow, 2),
        ];
    }

    /** @param Collection<int, ChartOfAccount> $accounts */
    private function profitFrom(Collection $accounts): float
    {
        return round(
            $accounts->where('type', ChartOfAccount::TYPE_INCOME)->sum('balance')
            - $accounts->where('type', ChartOfAccount::TYPE_EXPENSE)->sum('balance'),
            2
        );
    }
}
