<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use Illuminate\Support\Facades\DB;

class YearEndCloseService
{
    const ACCOUNT_RETAINED_EARNINGS = '3200';

    const ACCOUNT_INCOME_SUMMARY = '3300';

    protected JournalService $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
    }

    /**
     * Perform year-end close for a given accounting period.
     *
     * This process:
     * 1. Creates an Income Summary account if it doesn't exist
     * 2. Closes all revenue accounts to Income Summary (DR Revenue, CR Income Summary)
     * 3. Closes all expense accounts to Income Summary (DR Income Summary, CR Expense)
     * 4. Closes Income Summary to Retained Earnings
     * 5. Locks the accounting period
     *
     * @param  AccountingPeriod  $period  The year-end period to close
     * @param  string|null  $notes  Optional closing notes
     * @return array Summary of the closing process
     */
    public function performYearEndClose(AccountingPeriod $period, ?string $notes = null): array
    {
        if ($period->isLocked()) {
            throw new \InvalidArgumentException('This period is already locked.');
        }

        $tenantId = $period->tenant_id;

        return DB::transaction(function () use ($period, $tenantId, $notes) {
            $startDate = $period->start_date;
            $endDate = $period->end_date;

            // Ensure Income Summary account exists
            $this->ensureIncomeSummaryAccount($tenantId);

            // Step 1: Calculate totals for income and expense accounts from journal entries
            $incomeAccounts = $this->getAccountBalancesForPeriod($tenantId, 'income', $startDate, $endDate);
            $expenseAccounts = $this->getAccountBalancesForPeriod($tenantId, 'expense', $startDate, $endDate);

            $totalIncome = 0;
            $totalExpenses = 0;
            $closingEntries = [];

            // Step 2: Close revenue accounts → Income Summary
            if (! empty($incomeAccounts)) {
                $journal = $this->createClosingJournal(
                    $tenantId,
                    $endDate,
                    'Close Revenue Accounts to Income Summary',
                    'year-end-close-revenue'
                );

                foreach ($incomeAccounts as $account) {
                    if (abs($account->net_balance) < 0.01) {
                        continue;
                    }
                    // Debit Revenue accounts (to zero them out — they normally have credit balances)
                    $this->journalService->createEntry(
                        $journal, $account->account_code, $account->net_balance, 0,
                        "Close {$account->name} to Income Summary"
                    );
                    $totalIncome += $account->net_balance;
                }

                if ($totalIncome > 0) {
                    // Credit Income Summary
                    $this->journalService->createEntry(
                        $journal, self::ACCOUNT_INCOME_SUMMARY, 0, $totalIncome,
                        'Total Revenue closed to Income Summary'
                    );
                }

                $journal->updateTotals();
                $journal->save();
                $this->journalService->updateAccountBalances($journal);
                $closingEntries[] = $journal;
            }

            // Step 3: Close expense accounts → Income Summary
            if (! empty($expenseAccounts)) {
                $journal = $this->createClosingJournal(
                    $tenantId,
                    $endDate,
                    'Close Expense Accounts to Income Summary',
                    'year-end-close-expenses'
                );

                foreach ($expenseAccounts as $account) {
                    if (abs($account->net_balance) < 0.01) {
                        continue;
                    }
                    // Expense accounts have negative net_balance (debit balances → credit - debit < 0)
                    $expenseAmount = abs($account->net_balance);
                    // Credit Expense accounts (to zero them out — they normally have debit balances)
                    $this->journalService->createEntry(
                        $journal, $account->account_code, 0, $expenseAmount,
                        "Close {$account->name} to Income Summary"
                    );
                    $totalExpenses += $expenseAmount;
                }

                if ($totalExpenses > 0) {
                    // Debit Income Summary
                    $this->journalService->createEntry(
                        $journal, self::ACCOUNT_INCOME_SUMMARY, $totalExpenses, 0,
                        'Total Expenses closed to Income Summary'
                    );
                }

                $journal->updateTotals();
                $journal->save();
                $this->journalService->updateAccountBalances($journal);
                $closingEntries[] = $journal;
            }

            // Step 4: Close Income Summary → Retained Earnings
            $netIncome = $totalIncome - $totalExpenses;
            if (abs($netIncome) >= 0.01) {
                $journal = $this->createClosingJournal(
                    $tenantId,
                    $endDate,
                    'Close Income Summary to Retained Earnings',
                    'year-end-close-retained'
                );

                if ($netIncome > 0) {
                    // Net profit: DR Income Summary, CR Retained Earnings
                    $this->journalService->createEntry(
                        $journal, self::ACCOUNT_INCOME_SUMMARY, $netIncome, 0,
                        'Close net income to Retained Earnings'
                    );
                    $this->journalService->createEntry(
                        $journal, self::ACCOUNT_RETAINED_EARNINGS, 0, $netIncome,
                        'Net income transferred to Retained Earnings'
                    );
                } else {
                    // Net loss: DR Retained Earnings, CR Income Summary
                    $loss = abs($netIncome);
                    $this->journalService->createEntry(
                        $journal, self::ACCOUNT_RETAINED_EARNINGS, $loss, 0,
                        'Net loss transferred from Retained Earnings'
                    );
                    $this->journalService->createEntry(
                        $journal, self::ACCOUNT_INCOME_SUMMARY, 0, $loss,
                        'Close net loss to Retained Earnings'
                    );
                }

                $journal->updateTotals();
                $journal->save();
                $this->journalService->updateAccountBalances($journal);
                $closingEntries[] = $journal;
            }

            // Step 5: Lock the period
            $period->lock($notes ?? 'Year-end close completed');

            return [
                'total_income' => $totalIncome,
                'total_expenses' => $totalExpenses,
                'net_income' => $netIncome,
                'journals_created' => count($closingEntries),
                'period_locked' => true,
            ];
        });
    }

    /**
     * Get net balances for all accounts of a given type within a period
     */
    protected function getAccountBalancesForPeriod(int $tenantId, string $type, $startDate, $endDate): array
    {
        return DB::select("
            SELECT 
                coa.id,
                coa.account_code,
                coa.name,
                coa.type,
                COALESCE(SUM(je.credit) - SUM(je.debit), 0) as net_balance
            FROM chart_of_accounts coa
            INNER JOIN journal_entries je ON je.account_id = coa.id
            INNER JOIN journals j ON j.id = je.journal_id
            WHERE coa.tenant_id = ?
              AND coa.type = ?
              AND j.tenant_id = ?
              AND j.journal_date BETWEEN ? AND ?
              AND j.status = 'posted'
              AND j.deleted_at IS NULL
              AND coa.deleted_at IS NULL
            GROUP BY coa.id, coa.account_code, coa.name, coa.type
            HAVING ABS(COALESCE(SUM(je.credit) - SUM(je.debit), 0)) >= 0.01
        ", [$tenantId, $type, $tenantId, $startDate, $endDate]);
    }

    /**
     * Ensure the Income Summary account exists for the tenant
     */
    protected function ensureIncomeSummaryAccount(int $tenantId): void
    {
        ChartOfAccount::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'account_code' => self::ACCOUNT_INCOME_SUMMARY,
            ],
            [
                'name' => 'Income Summary',
                'type' => 'equity',
                'sub_type' => 'equity',
                'is_system' => true,
                'is_active' => true,
                'current_balance' => 0,
            ]
        );
    }

    /**
     * Create a closing journal entry
     */
    protected function createClosingJournal(int $tenantId, $date, string $description, string $reference): Journal
    {
        return Journal::create([
            'tenant_id' => $tenantId,
            'journal_number' => Journal::generateNumber($tenantId),
            'journal_date' => $date,
            'reference' => $reference,
            'description' => $description,
            'status' => 'posted',
            'is_posted' => true,
            'posted_at' => now(),
            'created_by' => auth()->id(),
        ]);
    }
}
