<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    /**
     * Get budget vs actual comparison for a specific budget
     */
    public function getBudgetVsActual(Budget $budget, ?string $throughMonth = null): Collection
    {
        // The budget's months in the business's financial year (audit MB-54).
        $months = $this->monthStarts($budget->tenant_id, (int) $budget->fiscal_year);

        // Determine which months to include
        if ($throughMonth) {
            $monthIndex = array_search($throughMonth, array_keys($months));
            $months = array_slice($months, 0, $monthIndex + 1, true);
        }

        return $budget->lines()->with('account')->get()->map(function ($line) use ($months) {
            $isIncome = $line->account->type === 'income';
            $budgeted = 0;
            $actual = 0;
            $monthlyData = [];

            foreach ($months as $monthKey => $monthStart) {
                $startDate = $monthStart->copy();
                $endDate = $startDate->copy()->endOfMonth();

                $monthBudget = $line->{$monthKey};
                $monthActual = $this->getAccountActual($line->account_id, $startDate, $endDate, $line->account->type);

                $budgeted += $monthBudget;
                $actual += $monthActual;

                $monthlyData[$monthKey] = [
                    'budget' => $monthBudget,
                    'actual' => $monthActual,
                    'variance' => $isIncome ? $monthActual - $monthBudget : $monthBudget - $monthActual,
                ];
            }

            // Variance is how much better (+) or worse (-) than the budget:
            // income above its budget is better, spending above it is worse.
            // (Tables plan T7: income used to count as "over budget" when it beat the target.)
            $variance = $isIncome ? $actual - $budgeted : $budgeted - $actual;
            $variancePercent = $budgeted != 0 ? ($variance / abs($budgeted)) * 100 : 0;

            return [
                'account' => $line->account,
                'account_code' => $line->account->account_code,
                'account_name' => $line->account->name,
                'account_type' => $line->account->type,
                'budgeted' => $budgeted,
                'actual' => $actual,
                'variance' => $variance,
                'variance_percent' => round($variancePercent, 2),
                'is_over_budget' => $variance < 0, // worse than budget: spent more, or earned less
                'monthly_data' => $monthlyData,
            ];
        });
    }

    /**
     * Get actual amount for an account in a date range
     */
    protected function getAccountActual(int $accountId, Carbon $startDate, Carbon $endDate, string $accountType): float
    {
        // A join, not a correlated EXISTS per line (P8). Posted journals
        // only, and not the year-end close, which empties income and expense
        // accounts: the same figures as the profit and loss (audit MB-54).
        $data = JournalEntry::query()
            ->join('journals', 'journals.id', '=', 'journal_entries.journal_id')
            ->whereNull('journals.deleted_at')
            ->where('journals.is_posted', true)
            ->where(fn ($q) => $q->whereNull('journals.journal_type')->orWhere('journals.journal_type', '!=', Journal::TYPE_CLOSING))
            ->whereBetween('journals.journal_date', [$startDate, $endDate])
            ->where('journal_entries.account_id', $accountId)
            ->selectRaw('SUM(journal_entries.debit) as total_debit, SUM(journal_entries.credit) as total_credit')
            ->first();

        // For expense accounts: actual = debits - credits
        // For income accounts: actual = credits - debits
        if (in_array($accountType, ['asset', 'expense'])) {
            return ($data->total_debit ?? 0) - ($data->total_credit ?? 0);
        }

        return ($data->total_credit ?? 0) - ($data->total_debit ?? 0);
    }

    /**
     * The twelve months of financial year $year, in order, from the month of
     * the tenant's fiscal_year_start (January when not set). A year that
     * starts in April runs April $year to March $year+1, so the budget's
     * "jan" column is January of the next calendar year (audit MB-54).
     *
     * @return array<string, Carbon> month column => first day of that month
     */
    public function monthStarts(int $tenantId, int $year): array
    {
        $first = Budget::firstMonth($tenantId);
        $starts = [];
        foreach (array_keys(Budget::getMonthColumns($first)) as $monthKey) {
            $num = $this->getMonthNumber($monthKey);
            $starts[$monthKey] = Carbon::create($num >= $first ? $year : $year + 1, $num, 1)->startOfMonth();
        }

        return $starts;
    }

    /**
     * Get month number from short name
     */
    protected function getMonthNumber(string $monthKey): int
    {
        $months = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
            'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
            'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];

        return $months[$monthKey] ?? 1;
    }

    /**
     * Copy budget from a previous year as a template
     */
    public function copyFromPreviousYear(Budget $sourceBudget, string $newFiscalYear, string $newName): Budget
    {
        return DB::transaction(function () use ($sourceBudget, $newFiscalYear, $newName) {
            $newBudget = Budget::create([
                'tenant_id' => $sourceBudget->tenant_id,
                'name' => $newName,
                'fiscal_year' => $newFiscalYear,
                'status' => Budget::STATUS_DRAFT,
                'description' => "Copied from {$sourceBudget->name} ({$sourceBudget->fiscal_year})",
                'created_by' => auth()->id(),
            ]);

            foreach ($sourceBudget->lines as $line) {
                BudgetLine::create([
                    'budget_id' => $newBudget->id,
                    'account_id' => $line->account_id,
                    'jan' => $line->jan,
                    'feb' => $line->feb,
                    'mar' => $line->mar,
                    'apr' => $line->apr,
                    'may' => $line->may,
                    'jun' => $line->jun,
                    'jul' => $line->jul,
                    'aug' => $line->aug,
                    'sep' => $line->sep,
                    'oct' => $line->oct,
                    'nov' => $line->nov,
                    'dec' => $line->dec,
                    'annual_total' => $line->annual_total,
                    'notes' => $line->notes,
                ]);
            }

            return $newBudget;
        });
    }

    /**
     * Apply a percentage increase to all budget lines
     */
    public function applyPercentageIncrease(Budget $budget, float $percentage): void
    {
        $multiplier = 1 + ($percentage / 100);

        foreach ($budget->lines as $line) {
            foreach (Budget::getMonthColumns() as $monthKey => $monthName) {
                $line->{$monthKey} = round($line->{$monthKey} * $multiplier, 2);
            }
            $line->calculateAnnualTotal();
            $line->save();
        }
    }

    /**
     * Get summary statistics for a budget
     */
    public function getBudgetSummary(Budget $budget): array
    {
        $lines = $budget->lines()->with('account')->get();

        $summary = [
            'total_budget' => 0,
            'income_budget' => 0,
            'expense_budget' => 0,
            'net_budget' => 0,
            'accounts_count' => $lines->count(),
            'by_type' => [],
        ];

        foreach ($lines as $line) {
            $type = $line->account->type;
            $amount = $line->annual_total;

            $summary['total_budget'] += abs($amount);

            if (! isset($summary['by_type'][$type])) {
                $summary['by_type'][$type] = 0;
            }
            $summary['by_type'][$type] += $amount;

            if ($type === 'income') {
                $summary['income_budget'] += $amount;
            } elseif ($type === 'expense') {
                $summary['expense_budget'] += $amount;
            }
        }

        $summary['net_budget'] = $summary['income_budget'] - $summary['expense_budget'];

        return $summary;
    }

    /**
     * The last month "to date" covers: this month for this year's budget,
     * the year's last month for a past year, and null for a future year
     * (nothing yet). Years follow the financial year (audit MB-54).
     */
    public function throughMonth(Budget $budget): ?string
    {
        $months = $this->monthStarts($budget->tenant_id, (int) $budget->fiscal_year);
        $keys = array_keys($months);
        $now = now()->startOfMonth();

        return match (true) {
            $now->lt($months[$keys[0]]) => null,
            $now->gt($months[$keys[11]]) => $keys[11],
            default => strtolower($now->format('M')),
        };
    }

    /**
     * Budget so far against actual so far (tables plan T7). "Used" counts
     * spending only (expense accounts); adding income and spending together
     * gave a meaningless figure. Accounts worse than budget are listed.
     */
    public function getYTDUtilization(Budget $budget): array
    {
        $through = $this->throughMonth($budget);
        $comparison = $through ? $this->getBudgetVsActual($budget, $through) : collect();
        $spending = $comparison->where('account_type', 'expense');

        $totalBudget = $spending->sum('budgeted');
        $totalActual = $spending->sum('actual');
        $utilizationPercent = $totalBudget != 0 ? ($totalActual / $totalBudget) * 100 : 0;

        $overBudgetAccounts = $comparison->filter(fn ($item) => $item['is_over_budget']);

        return [
            'through' => $through,
            'total_budget_ytd' => $totalBudget,
            'total_actual_ytd' => $totalActual,
            'utilization_percent' => round($utilizationPercent, 2),
            'remaining' => $totalBudget - $totalActual,
            'over_budget_count' => $overBudgetAccounts->count(),
            'over_budget_accounts' => $overBudgetAccounts->take(5)->values(),
        ];
    }

    /**
     * Create budget from historical actuals
     */
    public function createFromHistoricalActuals(int $tenantId, string $sourceYear, string $targetYear, string $name, array $accountTypes = ['income', 'expense']): Budget
    {
        return DB::transaction(function () use ($tenantId, $sourceYear, $targetYear, $name, $accountTypes) {
            $budget = Budget::create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'fiscal_year' => $targetYear,
                'status' => Budget::STATUS_DRAFT,
                'description' => "Created from {$sourceYear} actuals",
                'created_by' => auth()->id(),
            ]);

            $accounts = ChartOfAccount::where('tenant_id', $tenantId)
                ->whereIn('type', $accountTypes)
                ->where('is_active', true)
                ->get();

            foreach ($accounts as $account) {
                $monthlyActuals = [];

                foreach ($this->monthStarts($tenantId, (int) $sourceYear) as $monthKey => $startDate) {
                    $endDate = $startDate->copy()->endOfMonth();

                    $monthlyActuals[$monthKey] = abs($this->getAccountActual($account->id, $startDate, $endDate, $account->type));
                }

                $annualTotal = array_sum($monthlyActuals);

                // Only create line if there's any activity
                if ($annualTotal > 0) {
                    BudgetLine::create(array_merge([
                        'budget_id' => $budget->id,
                        'account_id' => $account->id,
                        'annual_total' => $annualTotal,
                    ], $monthlyActuals));
                }
            }

            return $budget;
        });
    }
}
