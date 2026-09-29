<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
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
     * Profit and loss from the ledger, without year-end closing journals
     * (A8). See App\Services\Accounting\FinancialStatements.
     */
    protected function calculateProfitLossFromJournals(int $tenantId, string $startDate, string $endDate): array
    {
        return app(\App\Services\Accounting\FinancialStatements::class)->profitAndLoss($tenantId, $startDate, $endDate);
    }

    /**
     * This financial year's profit up to $asOf, as shown on the balance
     * sheet (A2): from the business's own financial-year start.
     */
    protected function calculateNetIncomeForBalanceSheet(int $tenantId, string $asOf): float
    {
        return app(\App\Services\Accounting\FinancialStatements::class)->balanceSheet($tenantId, $asOf)['netIncome'];
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
