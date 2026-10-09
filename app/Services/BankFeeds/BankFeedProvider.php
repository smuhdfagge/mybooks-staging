<?php

namespace App\Services\BankFeeds;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/**
 * A bank data provider (session 17). Mono is the only one today (Okra shut
 * down in May 2025); another can be added by implementing this and naming
 * it in BankFeedProviders.
 *
 * Amounts leave this layer in naira (the provider's kobo converted with
 * App\Support\Money). Nothing here ever logs a key or a full account number.
 */
interface BankFeedProvider
{
    /** Short name stored on the connection, e.g. "mono". */
    public function name(): string;

    /** Real calls are possible (keys are set). */
    public function isLive(): bool;

    /**
     * Start linking: returns the address where the customer picks their bank
     * and logs in. They come back to $redirectUrl.
     *
     * @throws BankFeedException
     */
    public function startLinking(string $reference, string $customerName, string $customerEmail, string $redirectUrl): string;

    /**
     * Finish linking with the one-time code the customer comes back with;
     * returns the lasting account id.
     *
     * @throws BankFeedException
     */
    public function finishLinking(string $code): string;

    /** @throws BankFeedException */
    public function accountDetails(string $accountId): AccountInfo;

    /**
     * Transactions dated from $since to $until (today when null), oldest
     * first, all pages.
     *
     * @return list<FeedTransaction>
     *
     * @throws BankFeedException
     */
    public function fetchTransactions(string $accountId, CarbonInterface $since, ?CarbonInterface $until = null): array;

    /**
     * Ask the bank for fresh data now. The provider answers later by webhook.
     *
     * @throws BankFeedException
     */
    public function requestSync(string $accountId): void;

    /**
     * Start logging in again for an account that needs it; returns the address.
     *
     * @throws BankFeedException
     */
    public function startReauthorisation(string $accountId, string $reference, string $redirectUrl): string;

    /** @throws BankFeedException */
    public function unlink(string $accountId): void;

    /** The webhook really came from the provider. */
    public function verifyWebhook(Request $request): bool;

    /** @param  array<string, mixed>  $payload */
    public function parseWebhook(array $payload): WebhookEvent;
}
