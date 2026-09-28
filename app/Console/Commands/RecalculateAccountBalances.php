<?php

namespace App\Console\Commands;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateAccountBalances extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'accounts:recalculate
                            {--dry-run : Show calculated balances without updating}
                            {--tenant= : Recalculate for a specific tenant only}';

    /**
     * The console command description.
     */
    protected $description = 'Recalculate all chart of account balances from journal entries';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tenantId = $this->option('tenant');

        if ($dryRun) {
            $this->info('Running in dry-run mode. No changes will be made.');
        }

        $this->info('Recalculating chart of account balances from journal entries...');

        $query = ChartOfAccount::withoutGlobalScopes();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
            $this->info("Filtering to tenant ID: {$tenantId}");
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->warn('No accounts found.');
            return 0;
        }

        $bar = $this->output->createProgressBar($accounts->count());
        $bar->start();

        $changes = collect();

        DB::transaction(function () use ($accounts, $bar, $dryRun, &$changes) {
            foreach ($accounts as $account) {
                $entries = JournalEntry::whereHas('journal', function ($query) {
                        $query->whereNull('deleted_at');
                    })
                    ->where('account_id', $account->id)
                    ->get();

                // Opening balances are stored on the account, not as journal
                // lines, so start from them or they would be wiped.
                $balance = (float) ($account->opening_balance ?? 0);

                foreach ($entries as $entry) {
                    if ($account->isDebitBalance()) {
                        // Assets, Expenses: Debits increase, Credits decrease
                        $balance += ($entry->debit - $entry->credit);
                    } else {
                        // Liabilities, Equity, Income: Credits increase, Debits decrease
                        $balance += ($entry->credit - $entry->debit);
                    }
                }

                $oldBalance = (float) $account->current_balance;

                if (abs($oldBalance - $balance) > 0.001) {
                    $changes->push([
                        'Account' => $account->account_code,
                        'Name' => \Str::limit($account->name, 30),
                        'Old Balance' => number_format($oldBalance, 2),
                        'New Balance' => number_format($balance, 2),
                        'Difference' => number_format($balance - $oldBalance, 2),
                    ]);
                }

                if (! $dryRun) {
                    $account->current_balance = $balance;
                    $account->save();
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        if ($changes->isEmpty()) {
            $this->info('All account balances are already correct. No changes needed.');
        } else {
            $this->info("Found {$changes->count()} account(s) with balance discrepancies:");
            $this->table(['Account', 'Name', 'Old Balance', 'New Balance', 'Difference'], $changes->toArray());

            if ($dryRun) {
                $this->warn('Dry-run mode: No changes were applied.');
            } else {
                $this->info("Updated {$changes->count()} account balance(s).");
            }
        }

        $this->info("Processed {$accounts->count()} account(s) total.");

        return 0;
    }
}
