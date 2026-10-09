<?php

namespace App\Jobs;

use App\Models\BankFeedConnection;
use App\Services\BankFeeds\BankFeedSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Pulls one linked bank account (session 17), started by the provider's
 * webhook so the webhook itself answers at once. A temporary failure is
 * kept on the connection and the scheduled sync tries again.
 */
class PullBankFeed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $connectionId) {}

    public function handle(BankFeedSync $sync): void
    {
        $connection = BankFeedConnection::withoutGlobalScopes()->find($this->connectionId);
        if ($connection) {
            $sync->pull($connection);
        }
    }
}
