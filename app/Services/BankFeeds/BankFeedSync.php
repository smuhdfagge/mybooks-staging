<?php

namespace App\Services\BankFeeds;

use App\Enums\BankFeedConnectionStatus;
use App\Enums\BankFeedLineStatus;
use App\Models\BankFeedConnection;
use App\Models\BankFeedLine;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pulls transactions into bank_feed_lines (session 17).
 *
 * A pull re-reads a few days before the last one and adds only lines the
 * bank id of which is new, so running it twice adds nothing. One failing
 * connection never stops the others. Nothing here posts to the books.
 */
class BankFeedSync
{
    public function __construct(private BankFeedProviders $providers) {}

    /**
     * Pull one connection. Never throws for a provider problem: the result
     * says what happened and the connection keeps the reason.
     *
     * @return array{new: int, error: ?string}
     */
    public function pull(BankFeedConnection $connection): array
    {
        if (! $connection->provider_account_id || ! in_array($connection->status, [...BankFeedConnectionStatus::syncable(), BankFeedConnectionStatus::NeedsReauthorisation->value], true)) {
            return ['new' => 0, 'error' => null];
        }

        // Two pulls of the same account at once (webhook + schedule) would
        // only repeat work, so the second waits its turn or skips.
        $lock = Cache::lock('bank-feed-pull-'.$connection->id, 120);
        if (! $lock->get()) {
            return ['new' => 0, 'error' => null];
        }

        try {
            return $this->pullLocked($connection);
        } finally {
            $lock->release();
        }
    }

    /**
     * Every connection that can be pulled; a failure on one is recorded on it
     * and the loop carries on.
     *
     * @return array{connections: int, new: int, failed: int}
     */
    public function syncAll(): array
    {
        $total = ['connections' => 0, 'new' => 0, 'failed' => 0];

        BankFeedConnection::withoutGlobalScopes()
            ->whereIn('status', BankFeedConnectionStatus::syncable())
            ->whereNotNull('provider_account_id')
            ->orderBy('id')
            ->each(function (BankFeedConnection $connection) use (&$total) {
                $total['connections']++;
                try {
                    $result = $this->pull($connection);
                } catch (\Throwable $e) {
                    // A bug on one connection must not stop the rest.
                    Log::error('Bank feed pull crashed', ['connection' => $connection->id, 'error' => get_class($e).': '.$e->getMessage()]);
                    $connection->forceFill(['last_error' => 'Something went wrong while reading this account. We will try again.'])->save();
                    $total['failed']++;

                    return;
                }
                $total['new'] += $result['new'];
                $total['failed'] += $result['error'] ? 1 : 0;
            });

        return $total;
    }

    /** @return array{new: int, error: ?string} */
    private function pullLocked(BankFeedConnection $connection): array
    {
        $provider = $this->providers->for($connection->provider);
        $since = $connection->last_synced_at
            ? CarbonImmutable::instance($connection->last_synced_at)->subDays((int) config('mybooks.bank_feeds.overlap_days', 5))->startOfDay()
            : CarbonImmutable::now()->subDays((int) config('mybooks.bank_feeds.first_pull_days', 90))->startOfDay();

        try {
            $transactions = $provider->fetchTransactions($connection->provider_account_id, $since);
            $info = $provider->accountDetails($connection->provider_account_id);
        } catch (ReauthorisationRequired $e) {
            $connection->forceFill(['status' => BankFeedConnectionStatus::NeedsReauthorisation->value, 'last_error' => $e->getMessage()])->save();

            return ['new' => 0, 'error' => $e->getMessage()];
        } catch (TemporaryFailure $e) {
            // The account itself is fine; the next scheduled pull tries again.
            $connection->forceFill(['last_error' => $e->getMessage()])->save();

            return ['new' => 0, 'error' => $e->getMessage()];
        } catch (BankFeedException $e) {
            $connection->forceFill(['status' => BankFeedConnectionStatus::Error->value, 'last_error' => $e->getMessage()])->save();
            Log::warning('Bank feed pull failed', ['connection' => $connection->id, 'error' => $e->getMessage()]);

            return ['new' => 0, 'error' => $e->getMessage()];
        }

        $new = 0;
        foreach ($transactions as $tx) {
            $new += $this->store($connection, $tx) ? 1 : 0;
        }

        $connection->forceFill([
            'status' => BankFeedConnectionStatus::Linked->value,
            'last_synced_at' => now(),
            'last_error' => null,
            'data_status' => $info->dataStatus,
            'provider_balance' => $info->balanceMinor ?? $connection->provider_balance,
            'balance_at' => $info->balanceMinor !== null ? now() : $connection->balance_at,
        ])->save();

        return ['new' => $new, 'error' => null];
    }

    /** Adds the line unless this bank id is already there. */
    private function store(BankFeedConnection $connection, FeedTransaction $tx): bool
    {
        $existing = BankFeedLine::withoutGlobalScopes()->where('connection_id', $connection->id)
            ->where('provider_transaction_id', $tx->id)->exists();
        if ($existing) {
            return false;
        }

        try {
            // The owner is the connection's business, whoever triggered the pull.
            $line = new BankFeedLine([
                'connection_id' => $connection->id,
                'provider_transaction_id' => $tx->id,
                'bank_id' => $connection->bank_id,
                'date' => $tx->date->toDateString(),
                'amount' => $tx->amount,
                'direction' => $tx->direction,
                'narration' => $tx->narration,
                'balance_after' => $tx->balanceAfter,
                'status' => BankFeedLineStatus::New->value,
            ]);
            $line->tenant_id = $connection->tenant_id;
            $line->skipTenantGuard = true;
            $line->save();
        } catch (UniqueConstraintViolationException) {
            return false; // another pull added it first
        }

        return true;
    }
}
