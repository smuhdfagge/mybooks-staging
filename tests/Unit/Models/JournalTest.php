<?php

namespace Tests\Unit\Models;

use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use Tests\TestCase;

class JournalTest extends TestCase
{
    public function test_is_balanced_returns_true_when_equal(): void
    {
        $journal = Journal::factory()->make([
            'total_debit' => 1000.00,
            'total_credit' => 1000.00,
        ]);
        $this->assertTrue($journal->isBalanced());
    }

    public function test_is_balanced_returns_false_when_unequal(): void
    {
        $journal = Journal::factory()->unbalanced()->make();
        $this->assertFalse($journal->isBalanced());
    }

    public function test_is_balanced_handles_floating_point_precision(): void
    {
        $journal = Journal::factory()->make([
            'total_debit' => 1000.001,
            'total_credit' => 1000.005,
        ]);
        // Difference is 0.004 which is < 0.01
        $this->assertTrue($journal->isBalanced());
    }

    public function test_is_balanced_fails_at_threshold(): void
    {
        $journal = Journal::factory()->make([
            'total_debit' => 1000.00,
            'total_credit' => 1000.02,
        ]);
        // Difference is 0.02 which is >= 0.01
        $this->assertFalse($journal->isBalanced());
    }

    public function test_generate_number_creates_sequential_numbers(): void
    {
        $this->createAuthenticatedUser();

        $number1 = Journal::generateNumber($this->tenant->id);
        $this->assertMatchesRegularExpression('/^JE-\d{6}$/', $number1);

        // Create a journal to advance the counter
        Journal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'journal_number' => $number1,
        ]);

        $number2 = Journal::generateNumber($this->tenant->id);
        $this->assertNotEquals($number1, $number2);
    }

    public function test_generate_number_starts_at_one(): void
    {
        $this->createAuthenticatedUser();
        $number = Journal::generateNumber($this->tenant->id);
        $this->assertEquals('JE-000001', $number);
    }

    public function test_entries_relationship(): void
    {
        $this->createAuthenticatedUser();

        $journal = Journal::factory()->create(['tenant_id' => $this->tenant->id]);

        // Use an existing default account created by the tenant event
        $account = ChartOfAccount::where('tenant_id', $this->tenant->id)->first();

        JournalEntry::factory()->debit(500)->create([
            'journal_id' => $journal->id,
            'account_id' => $account->id,
        ]);

        $this->assertCount(1, $journal->entries);
    }

    public function test_update_totals(): void
    {
        $this->createAuthenticatedUser();

        $journal = Journal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'total_debit' => 0,
            'total_credit' => 0,
        ]);

        // Use existing default accounts from tenant creation
        $assetAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('type', 'asset')->first();
        $incomeAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('type', 'income')->first();

        JournalEntry::factory()->debit(500)->create([
            'journal_id' => $journal->id,
            'account_id' => $assetAccount->id,
        ]);
        JournalEntry::factory()->credit(500)->create([
            'journal_id' => $journal->id,
            'account_id' => $incomeAccount->id,
        ]);

        $journal->updateTotals();
        $journal->refresh();

        $this->assertEquals('500.00', $journal->total_debit);
        $this->assertEquals('500.00', $journal->total_credit);
    }

    public function test_post_fails_when_unbalanced(): void
    {
        $this->createAuthenticatedUser();

        $journal = Journal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'total_debit' => 1000,
            'total_credit' => 500,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Journal entries must be balanced before posting.');
        $journal->post();
    }

    public function test_post_marks_journal_as_posted(): void
    {
        $this->createAuthenticatedUser();

        // Use existing default accounts and reset their balances
        $assetAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('type', 'asset')->first();
        $assetAccount->update(['current_balance' => 0]);
        $incomeAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('type', 'income')->first();
        $incomeAccount->update(['current_balance' => 0]);

        $journal = Journal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'total_debit' => 500,
            'total_credit' => 500,
        ]);

        JournalEntry::factory()->debit(500)->create([
            'journal_id' => $journal->id,
            'account_id' => $assetAccount->id,
        ]);
        JournalEntry::factory()->credit(500)->create([
            'journal_id' => $journal->id,
            'account_id' => $incomeAccount->id,
        ]);

        $journal->post();
        $journal->refresh();

        $this->assertTrue($journal->is_posted);
        $this->assertEquals('posted', $journal->status);
        $this->assertNotNull($journal->posted_at);
    }

    public function test_post_updates_account_balances(): void
    {
        $this->createAuthenticatedUser();

        // Use existing default accounts and reset their balances
        $assetAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('type', 'asset')->first();
        $assetAccount->update(['current_balance' => 0]);
        $incomeAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('type', 'income')->first();
        $incomeAccount->update(['current_balance' => 0]);

        $journal = Journal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'total_debit' => 500,
            'total_credit' => 500,
        ]);

        JournalEntry::factory()->debit(500)->create([
            'journal_id' => $journal->id,
            'account_id' => $assetAccount->id,
        ]);
        JournalEntry::factory()->credit(500)->create([
            'journal_id' => $journal->id,
            'account_id' => $incomeAccount->id,
        ]);

        $journal->post();

        $assetAccount->refresh();
        $incomeAccount->refresh();

        // Asset is debit-balance: delta = debit - credit = 500 - 0 = 500
        $this->assertEquals('500.00', $assetAccount->current_balance);
        // Income is credit-balance: delta = credit - debit = 500 - 0 = 500
        $this->assertEquals('500.00', $incomeAccount->current_balance);
    }

    public function test_soft_deletes(): void
    {
        $this->createAuthenticatedUser();
        $journal = Journal::factory()->create(['tenant_id' => $this->tenant->id]);
        $journalId = $journal->id;
        $journal->delete();
        $this->assertSoftDeleted('journals', ['id' => $journalId]);
    }
}
