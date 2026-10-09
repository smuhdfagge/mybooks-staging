<?php

namespace App\Services\BankFeeds;

use App\Enums\BankFeedConnectionStatus;
use App\Models\Bank;
use App\Models\BankFeedConnection;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Linking, logging in again and disconnecting a bank account (session 17).
 *
 * MyBooks holds one Mono account for all businesses. A link is tied to the
 * business that started it by a random reference (kept on a "pending" row),
 * and to one MyBooks bank account. Only naira accounts are accepted.
 */
class BankFeedLinker
{
    public function __construct(
        private BankFeedProviders $providers,
        private BankFeedSync $sync,
        private BankFeedLimit $limit,
    ) {}

    /**
     * Where to send the user to pick their bank and log in. Links the feed
     * to $bank, or to a new MyBooks bank account called $newBankName.
     *
     * @throws BankFeedException
     */
    public function start(Tenant $tenant, User $user, ?Bank $bank, ?string $newBankName = null): string
    {
        $provider = $this->providers->driver();
        if (! $provider->isLive()) {
            throw new NotSetUp;
        }
        if ($this->limit->reached($tenant)) {
            throw new BankFeedException($this->limit->reachedMessage($tenant));
        }

        if ($bank) {
            if ((int) $bank->tenant_id !== (int) $tenant->id) {
                throw new BankFeedException('That bank account was not found.');
            }
            if ($bank->currency && strtoupper($bank->currency) !== 'NGN') {
                throw new BankFeedException('Bank feeds work for naira accounts only.');
            }
            $this->assertFree($bank);
            // An earlier, abandoned attempt for this account.
            BankFeedConnection::withoutGlobalScopes()->where('bank_id', $bank->id)->where('status', BankFeedConnectionStatus::Pending->value)->delete();
        } elseif (! filled($newBankName)) {
            throw new BankFeedException('Choose a bank account, or give the new one a name.');
        }

        $connection = BankFeedConnection::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'bank_id' => $bank?->id,
            'new_bank_name' => $bank ? null : Str::limit(trim((string) $newBankName), 100, ''),
            'provider' => $provider->name(),
            'link_ref' => Str::random(40),
            'status' => BankFeedConnectionStatus::Pending->value,
            'linked_by' => $user->id,
        ]);

        try {
            return $provider->startLinking($connection->link_ref, $tenant->name ?: 'MyBooks business', $user->email, route('bank-feeds.callback', ['ref' => $connection->link_ref]));
        } catch (\Throwable $e) {
            $connection->delete();
            throw $e;
        }
    }

    /**
     * Where to send the user to log in to the bank again.
     *
     * @throws BankFeedException
     */
    public function startReauthorisation(BankFeedConnection $connection, User $user): string
    {
        $provider = $this->providers->for($connection->provider);
        if (! $provider->isLive() || ! $connection->provider_account_id) {
            throw new NotSetUp;
        }

        $connection->forceFill(['link_ref' => Str::random(40)])->save();

        return $provider->startReauthorisation($connection->provider_account_id, $connection->link_ref, route('bank-feeds.callback', ['ref' => $connection->link_ref]));
    }

    /**
     * The customer is back (code from the redirect) or Mono says the link is
     * done (account id from the webhook). Safe to call twice.
     *
     * @throws BankFeedException
     */
    public function complete(string $ref, ?string $code = null, ?string $accountId = null): ?BankFeedConnection
    {
        // The customer's return and Mono's webhook arrive at about the same
        // time; one finishes the link, the other finds it done.
        return Cache::lock('bank-feed-link-'.$ref, 60)->block(15, fn () => $this->completeLocked($ref, $code, $accountId));
    }

    private function completeLocked(string $ref, ?string $code, ?string $accountId): ?BankFeedConnection
    {
        $connection = BankFeedConnection::withoutGlobalScopes()->where('link_ref', $ref)->first();
        if (! $connection) {
            return null;
        }
        $provider = $this->providers->for($connection->provider);

        // Logging in again: the account is the same, it just works again.
        if ($connection->provider_account_id) {
            return $this->reauthorised($connection);
        }

        if ($connection->status !== BankFeedConnectionStatus::Pending->value) {
            return $connection;
        }

        $tenant = Tenant::findOrFail($connection->tenant_id);
        if ($this->limit->reached($tenant, $connection->id)) {
            $connection->delete();
            throw new BankFeedException($this->limit->reachedMessage($tenant));
        }

        $accountId ??= $code ? $provider->finishLinking($code) : null;
        if (! $accountId) {
            throw new BankFeedException('The bank did not finish linking. Please try again.');
        }

        $info = $provider->accountDetails($accountId);
        if ($info->currency !== 'NGN') {
            $this->tryUnlink($provider, $accountId);
            $connection->delete();
            throw new BankFeedException('Bank feeds work for naira accounts only. That account is in '.$info->currency.'.');
        }

        // The same bank link can't feed two businesses (or two accounts).
        $taken = BankFeedConnection::withoutGlobalScopes()->where('provider_account_id', $accountId)->where('id', '!=', $connection->id)->first();
        if ($taken && $taken->status !== BankFeedConnectionStatus::Unlinked->value) {
            $connection->delete();
            throw new BankFeedException('That bank account is already linked.');
        }
        if ($taken) {
            $taken->forceFill(['provider_account_id' => null])->save(); // an old, disconnected row
        }

        $connection = DB::transaction(function () use ($connection, $tenant, $info) {
            $bank = $connection->bank_id ? Bank::withoutGlobalScopes()->where('tenant_id', $tenant->id)->find($connection->bank_id) : null;
            if (! $bank) {
                $bank = Bank::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'name' => $connection->new_bank_name ?: trim(($info->institution ?: 'Bank').' '.($info->mask ? '••••'.$info->mask : '')),
                    'bank_name' => $info->institution,
                    'account_type' => Bank::TYPE_CHECKING,
                    'currency' => 'NGN',
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'is_active' => true,
                ]);
            }
            $this->assertFree($bank, $connection->id);

            $connection->forceFill([
                'bank_id' => $bank->id,
                'new_bank_name' => null,
                'provider_account_id' => $info->id,
                'institution_name' => $info->institution,
                'account_name' => $info->name,
                'account_mask' => $info->mask,
                'currency' => 'NGN',
                'status' => BankFeedConnectionStatus::Linked->value,
                'data_status' => $info->dataStatus,
                'provider_balance' => $info->balanceMinor,
                'balance_at' => $info->balanceMinor !== null ? now() : null,
                'linked_at' => now(),
                'last_error' => null,
            ])->save();

            return $connection;
        });

        // First pull: the last 90 days. If the bank is slow the scheduled
        // sync picks it up; linking itself has worked.
        $this->sync->pull($connection);

        return $connection->refresh();
    }

    /** The user chose to disconnect: stop at the provider, keep the lines. */
    public function disconnect(BankFeedConnection $connection): void
    {
        $provider = $this->providers->for($connection->provider);
        $error = null;

        if ($connection->provider_account_id && $provider->isLive()) {
            try {
                $provider->unlink($connection->provider_account_id);
            } catch (BankFeedException $e) {
                // Disconnected here regardless; the link at Mono can be removed from the Mono side.
                $error = 'Disconnected here, but Mono could not be told ('.$e->getMessage().').';
                Log::warning('Bank feed unlink failed', ['connection' => $connection->id, 'error' => $e->getMessage()]);
            }
        }

        $connection->forceFill([
            'status' => BankFeedConnectionStatus::Unlinked->value,
            'unlinked_at' => now(),
            'last_error' => $error,
        ])->save();
    }

    /** Mono says the account asked for a new login (webhook). */
    public function markNeedsReauthorisation(BankFeedConnection $connection, string $why = 'The bank needs you to log in again.'): void
    {
        if (! $connection->isActive()) {
            return;
        }
        $connection->forceFill(['status' => BankFeedConnectionStatus::NeedsReauthorisation->value, 'last_error' => $why])->save();
    }

    public function reauthorised(BankFeedConnection $connection): BankFeedConnection
    {
        if ($connection->status === BankFeedConnectionStatus::NeedsReauthorisation->value) {
            $connection->forceFill(['status' => BankFeedConnectionStatus::Linked->value, 'last_error' => null])->save();
            $this->sync->pull($connection);
        }

        return $connection->refresh();
    }

    /** One feed per MyBooks bank account. */
    private function assertFree(Bank $bank, int $exceptConnectionId = 0): void
    {
        $exists = BankFeedConnection::withoutGlobalScopes()->where('bank_id', $bank->id)->where('id', '!=', $exceptConnectionId)
            ->whereIn('status', BankFeedConnectionStatus::counted())->exists();

        if ($exists) {
            throw new BankFeedException('This bank account already has a bank feed.');
        }
    }

    private function tryUnlink(BankFeedProvider $provider, string $accountId): void
    {
        try {
            $provider->unlink($accountId);
        } catch (BankFeedException) {
            // best effort: the account was refused anyway
        }
    }
}
