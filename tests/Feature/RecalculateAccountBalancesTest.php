<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use Tests\TestCase;

class RecalculateAccountBalancesTest extends TestCase
{
    /**
     * Get or create an account by code, resetting its balance.
     */
    protected function getAccount(string $code, string $name, string $type, float $balance = 0): ChartOfAccount
    {
        $account = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('account_code', $code)
            ->first();

        if ($account) {
            $account->update(['current_balance' => $balance]);

            return $account->fresh();
        }

        return ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'account_code' => $code,
            'name' => $name,
            'type' => $type,
            'is_system' => true,
            'is_active' => true,
            'current_balance' => $balance,
        ]);
    }

    public function test_recalculate_command_fixes_incorrect_balances(): void
    {
        $this->createAuthenticatedUser();

        $cash = $this->getAccount('1000', 'Cash', 'asset', 999999);
        $revenue = $this->getAccount('4000', 'Revenue', 'income', 0);

        $journal = Journal::create([
            'tenant_id' => $this->tenant->id,
            'journal_number' => 'JE-000001',
            'journal_date' => now(),
            'description' => 'Test sale',
            'total_debit' => 500,
            'total_credit' => 500,
            'status' => 'posted',
            'is_posted' => true,
            'posted_at' => now(),
        ]);

        JournalEntry::create([
            'journal_id' => $journal->id,
            'account_id' => $cash->id,
            'description' => 'Cash in',
            'debit' => 500,
            'credit' => 0,
        ]);

        JournalEntry::create([
            'journal_id' => $journal->id,
            'account_id' => $revenue->id,
            'description' => 'Revenue',
            'debit' => 0,
            'credit' => 500,
        ]);

        $this->artisan('accounts:recalculate')
            ->assertExitCode(0);

        $this->assertEquals(500.00, (float) $cash->fresh()->current_balance);
        $this->assertEquals(500.00, (float) $revenue->fresh()->current_balance);
    }

    public function test_recalculate_dry_run_does_not_modify_balances(): void
    {
        $this->createAuthenticatedUser();

        $cash = $this->getAccount('1000', 'Cash', 'asset', 999999);

        $journal = Journal::create([
            'tenant_id' => $this->tenant->id,
            'journal_number' => 'JE-DRY-001',
            'journal_date' => now(),
            'description' => 'Test',
            'total_debit' => 100,
            'total_credit' => 100,
            'status' => 'posted',
            'is_posted' => true,
            'posted_at' => now(),
        ]);

        JournalEntry::create([
            'journal_id' => $journal->id,
            'account_id' => $cash->id,
            'description' => 'Debit',
            'debit' => 100,
            'credit' => 0,
        ]);

        $this->artisan('accounts:recalculate', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertEquals(999999.00, (float) $cash->fresh()->current_balance);
    }

    public function test_recalculate_with_tenant_filter(): void
    {
        $this->createAuthenticatedUser();

        $cash = $this->getAccount('1000', 'Cash', 'asset', 0);

        $journal = Journal::create([
            'tenant_id' => $this->tenant->id,
            'journal_number' => 'JE-TENANT-001',
            'journal_date' => now(),
            'description' => 'Test',
            'total_debit' => 250,
            'total_credit' => 250,
            'status' => 'posted',
            'is_posted' => true,
            'posted_at' => now(),
        ]);

        JournalEntry::create([
            'journal_id' => $journal->id,
            'account_id' => $cash->id,
            'description' => 'Debit',
            'debit' => 250,
            'credit' => 0,
        ]);

        $this->artisan('accounts:recalculate', ['--tenant' => $this->tenant->id])
            ->assertExitCode(0);

        $this->assertEquals(250.00, (float) $cash->fresh()->current_balance);
    }

    public function test_recalculate_ignores_soft_deleted_journals(): void
    {
        $this->createAuthenticatedUser();

        $cash = $this->getAccount('1000', 'Cash', 'asset', 0);

        $journal = Journal::create([
            'tenant_id' => $this->tenant->id,
            'journal_number' => 'JE-DEL-001',
            'journal_date' => now(),
            'description' => 'Deleted journal',
            'total_debit' => 1000,
            'total_credit' => 1000,
            'status' => 'posted',
            'is_posted' => true,
            'posted_at' => now(),
        ]);

        JournalEntry::create([
            'journal_id' => $journal->id,
            'account_id' => $cash->id,
            'description' => 'Cash',
            'debit' => 1000,
            'credit' => 0,
        ]);

        $journal->delete();

        $this->artisan('accounts:recalculate')
            ->assertExitCode(0);

        $this->assertEquals(0.00, (float) $cash->fresh()->current_balance);
    }
}
