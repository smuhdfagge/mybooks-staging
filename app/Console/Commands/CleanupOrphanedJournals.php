<?php

namespace App\Console\Commands;

use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOrphanedJournals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'journals:cleanup 
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--reset-all : Delete ALL journals and reset ALL chart of account balances to zero}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned journal entries where the source transaction has been deleted and recalculate chart of account balances';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $resetAll = $this->option('reset-all');

        if ($dryRun) {
            $this->info('Running in dry-run mode. No changes will be made.');
        }

        // If reset-all flag is set, delete everything
        if ($resetAll) {
            return $this->resetAllJournalsAndBalances($dryRun);
        }

        $this->info('Starting cleanup of orphaned journals...');

        // Find all journals with references to transactions
        $orphanedJournals = $this->findOrphanedJournals();

        $this->info("Found {$orphanedJournals->count()} orphaned journal(s).");

        if ($orphanedJournals->isEmpty()) {
            $this->info('No orphaned journals found. Chart of accounts will be recalculated.');
        } else {
            $this->table(
                ['ID', 'Journal Number', 'Reference Type', 'Reference ID', 'Total Debit', 'Description'],
                $orphanedJournals->map(fn ($j) => [
                    $j->id,
                    $j->journal_number,
                    class_basename($j->reference_type),
                    $j->reference_id,
                    number_format($j->total_debit, 2),
                    \Str::limit($j->description, 40),
                ])
            );

            if (! $dryRun) {
                if ($this->confirm('Do you want to delete these orphaned journals?', true)) {
                    $this->deleteOrphanedJournals($orphanedJournals);
                }
            }
        }

        // Recalculate all chart of account balances
        if (! $dryRun) {
            if ($this->confirm('Do you want to recalculate all chart of account balances from journal entries?', true)) {
                $this->recalculateAccountBalances();
            }
        } else {
            $this->info('Would recalculate all chart of account balances.');
        }

        $this->info('Cleanup complete!');
    }

    /**
     * Find journals where the referenced transaction no longer exists
     */
    protected function findOrphanedJournals()
    {
        // Get all journals with references (including soft-deleted ones)
        $journals = Journal::withoutGlobalScopes()
            ->withTrashed()
            ->whereNotNull('reference_type')
            ->whereNotNull('reference_id')
            ->get();

        $orphaned = collect();

        foreach ($journals as $journal) {
            $exists = $this->transactionExists($journal->reference_type, $journal->reference_id);

            if (! $exists) {
                $orphaned->push($journal);
            }
        }

        return $orphaned;
    }

    /**
     * Check if a transaction exists
     */
    protected function transactionExists(string $referenceType, int $referenceId): bool
    {
        try {
            // Use withoutGlobalScopes and withTrashed to check for soft-deleted records too
            $model = new $referenceType;
            $query = $referenceType::withoutGlobalScopes()->where('id', $referenceId);

            // Check if model uses soft deletes
            if (method_exists($model, 'withTrashed')) {
                $query->withTrashed();
            }

            return $query->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete orphaned journals and their entries
     */
    protected function deleteOrphanedJournals($orphanedJournals)
    {
        $this->info('Deleting orphaned journals...');

        DB::transaction(function () use ($orphanedJournals) {
            foreach ($orphanedJournals as $journal) {
                // Force delete entries
                JournalEntry::where('journal_id', $journal->id)->forceDelete();

                // Force delete journal
                $journal->forceDelete();

                $this->line("Deleted journal #{$journal->journal_number}");
            }
        });

        $this->info("Deleted {$orphanedJournals->count()} orphaned journal(s).");
    }

    /**
     * Recalculate all chart of account balances from journal entries
     */
    protected function recalculateAccountBalances()
    {
        $this->info('Recalculating chart of account balances...');

        // Get all accounts
        $accounts = ChartOfAccount::withoutGlobalScopes()->get();

        $bar = $this->output->createProgressBar($accounts->count());
        $bar->start();

        DB::transaction(function () use ($accounts, $bar) {
            foreach ($accounts as $account) {
                // Calculate balance from all journal entries for this account
                // Only consider journals that are not soft-deleted
                $entries = JournalEntry::whereHas('journal', function ($query) {
                    $query->whereNull('deleted_at');
                })
                    ->where('account_id', $account->id)
                    ->get();

                $balance = 0;

                foreach ($entries as $entry) {
                    if ($account->isDebitBalance()) {
                        // Assets, Expenses: Debits increase, Credits decrease
                        $balance += ($entry->debit - $entry->credit);
                    } else {
                        // Liabilities, Equity, Income: Credits increase, Debits decrease
                        $balance += ($entry->credit - $entry->debit);
                    }
                }

                // Update account balance
                $account->current_balance = $balance;
                $account->save();

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Recalculated balances for {$accounts->count()} account(s).");
    }

    /**
     * Reset ALL journals and chart of account balances to zero
     */
    protected function resetAllJournalsAndBalances(bool $dryRun)
    {
        $journalCount = Journal::withoutGlobalScopes()->withTrashed()->count();
        $entryCount = JournalEntry::count();
        $accountCount = ChartOfAccount::withoutGlobalScopes()->where('current_balance', '!=', 0)->count();

        $this->warn('⚠️  RESET ALL MODE');
        $this->info('This will permanently delete:');
        $this->line("  - {$journalCount} journal(s) (including soft-deleted)");
        $this->line("  - {$entryCount} journal entries");
        $this->line("  - Reset {$accountCount} chart of account balances to zero");

        if ($dryRun) {
            $this->info('Dry-run mode: No changes made.');

            return 0;
        }

        if (! $this->confirm('⚠️  Are you SURE you want to delete ALL journals and reset ALL balances? This cannot be undone!', false)) {
            $this->info('Operation cancelled.');

            return 0;
        }

        if (! $this->confirm('⚠️  FINAL WARNING: Type YES to confirm complete reset', false)) {
            $this->info('Operation cancelled.');

            return 0;
        }

        $this->info('Resetting all journals and balances...');

        DB::transaction(function () {
            // Delete all journal entries first
            $deletedEntries = JournalEntry::query()->delete();
            $this->line("Deleted {$deletedEntries} journal entries.");

            // Force delete all journals (including soft-deleted)
            $deletedJournals = Journal::withoutGlobalScopes()->withTrashed()->forceDelete();
            $this->line('Deleted all journals.');

            // Reset all chart of account balances to zero
            $updatedAccounts = ChartOfAccount::withoutGlobalScopes()->update(['current_balance' => 0]);
            $this->line("Reset {$updatedAccounts} account balances to zero.");
        });

        $this->info('✅ Complete reset finished!');
        $this->warn('Note: You may need to recreate journal entries for existing transactions.');

        return 0;
    }
}
