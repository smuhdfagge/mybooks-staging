<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Services\JournalService;
use App\Services\YearEndCloseService;
use Tests\TestCase;

class YearEndCloseTest extends TestCase
{
    /**
     * Seed chart of accounts needed for year-end close.
     */
    protected function seedAccountsForYearEnd(int $tenantId): void
    {
        $accounts = [
            ['account_code' => '1000', 'name' => 'Cash', 'type' => 'asset'],
            ['account_code' => '1100', 'name' => 'Checking', 'type' => 'asset'],
            ['account_code' => '3200', 'name' => 'Retained Earnings', 'type' => 'equity'],
            ['account_code' => '4000', 'name' => 'Sales Revenue', 'type' => 'income'],
            ['account_code' => '4100', 'name' => 'Service Revenue', 'type' => 'income'],
            ['account_code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => 'expense'],
            ['account_code' => '6000', 'name' => 'Salaries', 'type' => 'expense'],
            ['account_code' => '6990', 'name' => 'General Expense', 'type' => 'expense'],
        ];

        foreach ($accounts as $acct) {
            $account = ChartOfAccount::firstOrCreate(
                ['tenant_id' => $tenantId, 'account_code' => $acct['account_code']],
                array_merge($acct, [
                    'tenant_id' => $tenantId,
                    'is_system' => true,
                    'is_active' => true,
                    'current_balance' => 0,
                ])
            );
            // Reset balance for clean test state
            $account->update(['current_balance' => 0]);
        }
    }

    /**
     * Create a posted journal entry for a given account within a period.
     */
    protected function createPostedJournal(int $tenantId, string $date, string $desc, array $entries): Journal
    {
        $journal = Journal::create([
            'tenant_id' => $tenantId,
            'journal_number' => Journal::generateNumber($tenantId),
            'journal_date' => $date,
            'description' => $desc,
            'status' => 'posted',
            'is_posted' => true,
            'posted_at' => now(),
        ]);

        $journalService = app(JournalService::class);

        foreach ($entries as [$accountCode, $debit, $credit, $entryDesc]) {
            $journalService->createEntry($journal, $accountCode, $debit, $credit, $entryDesc);
        }

        $journal->updateTotals();
        $journal->save();

        $journalService->updateAccountBalances($journal);

        return $journal;
    }

    public function test_year_end_close_creates_closing_journals(): void
    {
        $this->createAuthenticatedUser();
        $this->seedAccountsForYearEnd($this->tenant->id);

        $period = AccountingPeriod::withoutEvents(function () {
            return AccountingPeriod::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'FY 2025',
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
                'status' => AccountingPeriod::STATUS_OPEN,
                'fiscal_year' => 2025,
            ]);
        });

        // Create revenue journal: CR Sales Revenue 10000
        $this->createPostedJournal($this->tenant->id, '2025-06-15', 'Sale', [
            ['1000', 10000, 0, 'Cash received'],
            ['4000', 0, 10000, 'Sales revenue'],
        ]);

        // Create expense journal: DR General Expense 3000
        $this->createPostedJournal($this->tenant->id, '2025-07-01', 'Expense', [
            ['6990', 3000, 0, 'General expense'],
            ['1000', 0, 3000, 'Cash paid'],
        ]);

        $service = app(YearEndCloseService::class);
        $result = $service->performYearEndClose($period);

        $this->assertEquals(10000, $result['total_income']);
        $this->assertEquals(3000, $result['total_expenses']);
        $this->assertEquals(7000, $result['net_income']);
        $this->assertTrue($result['period_locked']);
        $this->assertGreaterThan(0, $result['journals_created']);

        // Period should be locked
        $this->assertTrue($period->fresh()->isLocked());

        // Income Summary account should exist
        $incomeSummary = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('account_code', '3300')
            ->first();
        $this->assertNotNull($incomeSummary);

        // Retained Earnings should have increased by net income (7000)
        $retainedEarnings = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('account_code', '3200')
            ->first();
        $this->assertEquals(7000, (float) $retainedEarnings->current_balance);
    }

    public function test_year_end_close_handles_net_loss(): void
    {
        $this->createAuthenticatedUser();
        $this->seedAccountsForYearEnd($this->tenant->id);

        $period = AccountingPeriod::withoutEvents(function () {
            return AccountingPeriod::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'FY 2024',
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
                'status' => AccountingPeriod::STATUS_OPEN,
                'fiscal_year' => 2024,
            ]);
        });

        // Revenue 2000
        $this->createPostedJournal($this->tenant->id, '2024-06-15', 'Small Sale', [
            ['1000', 2000, 0, 'Cash'],
            ['4000', 0, 2000, 'Revenue'],
        ]);

        // Expenses 5000
        $this->createPostedJournal($this->tenant->id, '2024-08-01', 'Big Expense', [
            ['6990', 5000, 0, 'Expense'],
            ['1000', 0, 5000, 'Cash'],
        ]);

        $service = app(YearEndCloseService::class);
        $result = $service->performYearEndClose($period);

        $this->assertEquals(2000, $result['total_income']);
        $this->assertEquals(5000, $result['total_expenses']);
        $this->assertEquals(-3000, $result['net_income']);

        // Retained Earnings should be debited (reduced) by 3000
        $retainedEarnings = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('account_code', '3200')
            ->first();
        $this->assertEquals(-3000, (float) $retainedEarnings->current_balance);
    }

    public function test_year_end_close_prevents_double_lock(): void
    {
        $this->createAuthenticatedUser();
        $this->seedAccountsForYearEnd($this->tenant->id);

        $period = AccountingPeriod::withoutEvents(function () {
            return AccountingPeriod::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'FY 2023',
                'start_date' => '2023-01-01',
                'end_date' => '2023-12-31',
                'status' => AccountingPeriod::STATUS_LOCKED,
                'is_year_end' => true,
                'fiscal_year' => 2023,
            ]);
        });

        $service = app(YearEndCloseService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already locked');

        $service->performYearEndClose($period);
    }

    public function test_year_end_close_with_no_transactions(): void
    {
        $this->createAuthenticatedUser();
        $this->seedAccountsForYearEnd($this->tenant->id);

        $period = AccountingPeriod::withoutEvents(function () {
            return AccountingPeriod::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'FY Empty',
                'start_date' => '2022-01-01',
                'end_date' => '2022-12-31',
                'status' => AccountingPeriod::STATUS_OPEN,
                'fiscal_year' => 2022,
            ]);
        });

        $service = app(YearEndCloseService::class);
        $result = $service->performYearEndClose($period);

        $this->assertEquals(0, $result['total_income']);
        $this->assertEquals(0, $result['total_expenses']);
        $this->assertEquals(0, $result['net_income']);
        $this->assertTrue($result['period_locked']);
        $this->assertTrue($period->fresh()->isLocked());
    }
}
