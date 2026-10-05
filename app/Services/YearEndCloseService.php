<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Services\Accounting\FinancialStatements;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Year-end close (finding A1).
 *
 * 1. Every income and expense account is brought to zero by its signed
 *    balance for the period, against Income Summary.
 * 2. Income Summary is closed to Retained Earnings.
 * 3. Every income and expense account is checked to be at zero for the
 *    period; if not, nothing is saved and the period stays open.
 * 4. The period is locked.
 *
 * Balances come from posted journals (a cancelled document's journal and
 * its reversal are both posted and cancel out). Account codes come from
 * AccountCodeService, so a business with its own chart is closed to its
 * own retained earnings account.
 */
class YearEndCloseService
{
    public function __construct(
        protected JournalService $journalService,
        protected FinancialStatements $statements,
    ) {}

    /**
     * @return array{total_income: float, total_expenses: float, net_income: float, journals_created: int, period_locked: bool}
     */
    public function performYearEndClose(AccountingPeriod $period, ?string $notes = null): array
    {
        if ($period->isLocked()) {
            throw new \InvalidArgumentException('This period is already locked.');
        }

        $tenantId = $period->tenant_id;
        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();

        return DB::transaction(function () use ($period, $tenantId, $start, $end, $notes) {
            // The screen offers the year-end close on a closed period, whose
            // closing journals were then refused as "in a closed period". It
            // is opened inside this transaction and locked at the end
            // (session 11); if anything fails it stays closed.
            if ($period->isClosed()) {
                $period->forceFill(['status' => AccountingPeriod::STATUS_OPEN])->save();
            }

            $retainedCode = AccountCodeService::resolve($tenantId, 'retained_earnings');
            $summaryCode = AccountCodeService::resolve($tenantId, 'income_summary');
            $this->ensureAccount($tenantId, $retainedCode, 'Retained Earnings', 'retained_earnings');
            $this->ensureAccount($tenantId, $summaryCode, 'Income Summary', 'equity');

            $profitAndLoss = $this->statements->accountBalances($tenantId, $start, $end)
                ->whereIn('type', [ChartOfAccount::TYPE_INCOME, ChartOfAccount::TYPE_EXPENSE])
                ->filter(fn ($a) => abs($a->balance) >= 0.005);

            $totalIncome = round($profitAndLoss->where('type', ChartOfAccount::TYPE_INCOME)->sum('balance'), 2);
            $totalExpenses = round($profitAndLoss->where('type', ChartOfAccount::TYPE_EXPENSE)->sum('balance'), 2);
            $netIncome = round($totalIncome - $totalExpenses, 2);
            $journals = 0;

            // Step 1: every income and expense account to Income Summary.
            if ($profitAndLoss->isNotEmpty()) {
                $journal = $this->closingJournal($tenantId, $end, 'Close income and expense accounts to Income Summary', 'year-end-close-accounts');
                $summaryDebit = 0.0;
                $summaryCredit = 0.0;

                foreach ($profitAndLoss as $account) {
                    // Take the balance off in the opposite direction to how it sits.
                    // Income normally has a credit balance, so it is debited; an
                    // expense with a credit balance (a rebate) is debited too.
                    $sitsAsCredit = $account->isDebitBalance() ? $account->balance < 0 : $account->balance > 0;
                    $amount = abs($account->balance);

                    if ($sitsAsCredit) {
                        $this->journalService->createEntry($journal, $account->account_code, $amount, 0, "Close {$account->name}");
                        $summaryCredit += $amount;
                    } else {
                        $this->journalService->createEntry($journal, $account->account_code, 0, $amount, "Close {$account->name}");
                        $summaryDebit += $amount;
                    }
                }

                if ($summaryCredit > 0) {
                    $this->journalService->createEntry($journal, $summaryCode, 0, round($summaryCredit, 2), 'Income and credits closed');
                }
                if ($summaryDebit > 0) {
                    $this->journalService->createEntry($journal, $summaryCode, round($summaryDebit, 2), 0, 'Expenses and debits closed');
                }

                $this->finish($journal);
                $journals++;
            }

            // Step 2: Income Summary to Retained Earnings.
            if (abs($netIncome) >= 0.005) {
                $journal = $this->closingJournal($tenantId, $end, 'Close Income Summary to Retained Earnings', 'year-end-close-retained');

                if ($netIncome > 0) {
                    $this->journalService->createEntry($journal, $summaryCode, $netIncome, 0, 'Net profit to Retained Earnings');
                    $this->journalService->createEntry($journal, $retainedCode, 0, $netIncome, 'Net profit for the year');
                } else {
                    $this->journalService->createEntry($journal, $retainedCode, -$netIncome, 0, 'Net loss for the year');
                    $this->journalService->createEntry($journal, $summaryCode, 0, -$netIncome, 'Net loss to Retained Earnings');
                }

                $this->finish($journal);
                $journals++;
            }

            // Step 3: refuse to lock unless the year really is closed.
            $leftOver = $this->statements->accountBalances($tenantId, $start, $end)
                ->whereIn('type', [ChartOfAccount::TYPE_INCOME, ChartOfAccount::TYPE_EXPENSE])
                ->filter(fn ($a) => abs($a->balance) >= 0.005);
            if ($leftOver->isNotEmpty()) {
                throw new RuntimeException('Year-end close did not bring these accounts to zero: '
                    .$leftOver->map(fn ($a) => "{$a->account_code} ({$a->balance})")->implode(', '));
            }

            // Step 4: lock the period.
            $period->lock($notes ?? 'Year-end close completed');

            return [
                'total_income' => $totalIncome,
                'total_expenses' => $totalExpenses,
                'net_income' => $netIncome,
                'journals_created' => $journals,
                'period_locked' => true,
            ];
        });
    }

    protected function ensureAccount(int $tenantId, string $code, string $name, string $subType): void
    {
        ChartOfAccount::firstOrCreate(
            ['tenant_id' => $tenantId, 'account_code' => $code],
            ['name' => $name, 'type' => ChartOfAccount::TYPE_EQUITY, 'sub_type' => $subType, 'is_system' => true, 'is_active' => true, 'current_balance' => 0]
        );
    }

    protected function closingJournal(int $tenantId, string $date, string $description, string $reference): Journal
    {
        return Journal::create([
            'tenant_id' => $tenantId,
            'journal_number' => Journal::generateNumber($tenantId),
            'journal_date' => $date,
            'reference' => $reference,
            'description' => $description,
            'journal_type' => Journal::TYPE_CLOSING,
            'status' => 'posted',
            'is_posted' => true,
            'posted_at' => now(),
            'created_by' => auth()->id(),
        ]);
    }

    protected function finish(Journal $journal): void
    {
        $journal->updateTotals();
        $journal->save();
        $this->journalService->updateAccountBalances($journal); // checks it balances (M2)
    }
}
