<?php

namespace App\Console\Commands;

use App\Models\BankFeedConnection;
use App\Services\BankFeeds\BankFeedSync;
use Illuminate\Console\Command;

/**
 * Pulls new transactions for every linked bank account (session 17). Runs
 * every few hours. One account failing does not stop the others. Nothing is
 * posted: new lines wait for a person to review them.
 */
class SyncBankFeeds extends Command
{
    protected $signature = 'bankfeeds:sync';

    protected $description = 'Pull new bank transactions for all linked bank accounts';

    public function handle(BankFeedSync $sync): int
    {
        if (! config('mybooks.features.bank_feeds')) {
            return self::SUCCESS;
        }

        // Links started and never finished.
        BankFeedConnection::withoutGlobalScopes()->where('status', 'pending')->where('created_at', '<', now()->subDay())->delete();

        $result = $sync->syncAll();
        $this->info("{$result['connections']} accounts read, {$result['new']} new lines, {$result['failed']} with a problem");

        return self::SUCCESS;
    }
}
