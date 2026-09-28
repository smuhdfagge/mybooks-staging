<?php

namespace Tests\Unit\Models;

use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use Tests\TestCase;

class JournalTest extends TestCase
{
    /**
     * A journal with real lines. Balance is judged from the lines, not from
     * the total_debit/total_credit columns, which can be stale (finding M2).
     */
    private function journalWithLines(float $debit, float $credit, array $attributes = []): Journal
    {
        $this->createAuthenticatedUser();
        $asset = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('type', 'asset')->first();
        $income = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('type', 'income')->first();

        $journal = Journal::factory()->create(array_merge(['tenant_id' => $this->tenant->id], $attributes));
        JournalEntry::factory()->debit($debit)->create(['journal_id' => $journal->id, 'account_id' => $asset->id]);
        JournalEntry::factory()->credit($credit)->create(['journal_id' => $journal->id, 'account_id' => $income->id]);

        return $journal;
    }

    public function test_is_balanced_returns_true_when_equal(): void
    {
        $this->assertTrue($this->journalWithLines(1000.00, 1000.00)->isBalanced());
    }

    public function test_is_balanced_returns_false_when_unequal(): void
    {
        $this->assertFalse($this->journalWithLines(1000.00, 500.00)->isBalanced());
    }

    public function test_is_balanced_handles_floating_point_precision(): void
    {
        // Lines are stored to 2 decimals, so these both become 1000.00
        $this->assertTrue($this->journalWithLines(1000.001, 1000.004)->isBalanced());
    }

    public function test_is_balanced_fails_at_threshold(): void
    {
        // One kobo out is unbalanced
        $this->assertFalse($this->journalWithLines(1000.00, 1000.01)->isBalanced());
    }

    public function test_is_balanced_ignores_stale_total_columns(): void
    {
        // The stored totals say balanced, the lines do not
        $journal = $this->journalWithLines(250.00, 200.00, ['total_debit' => 0, 'total_credit' => 0]);
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
        $journal = $this->journalWithLines(1000, 500);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Journal entries must be balanced before posting.');
        $journal->post();
    }

    public function test_post_fails_when_journal_has_no_lines(): void
    {
        $this->createAuthenticatedUser();
        $journal = Journal::factory()->create(['tenant_id' => $this->tenant->id, 'total_debit' => 500, 'total_credit' => 500]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Journal has no lines to post.');
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
