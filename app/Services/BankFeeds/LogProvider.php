<?php

namespace App\Services\BankFeeds;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Used when no provider keys are set (session 17): nothing real is called.
 * Linking says "not set up yet"; reading returns nothing. Every call is
 * written to the log (without any account details) so a developer can see
 * what would have happened.
 */
class LogProvider implements BankFeedProvider
{
    public function name(): string
    {
        return 'log';
    }

    public function isLive(): bool
    {
        return false;
    }

    public function startLinking(string $reference, string $customerName, string $customerEmail, string $redirectUrl): string
    {
        $this->note('startLinking');
        throw new NotSetUp;
    }

    public function finishLinking(string $code): string
    {
        $this->note('finishLinking');
        throw new NotSetUp;
    }

    public function accountDetails(string $accountId): AccountInfo
    {
        $this->note('accountDetails');
        throw new NotSetUp;
    }

    public function fetchTransactions(string $accountId, CarbonInterface $since, ?CarbonInterface $until = null): array
    {
        $this->note('fetchTransactions');

        return [];
    }

    public function requestSync(string $accountId): void
    {
        $this->note('requestSync');
    }

    public function startReauthorisation(string $accountId, string $reference, string $redirectUrl): string
    {
        $this->note('startReauthorisation');
        throw new NotSetUp;
    }

    public function unlink(string $accountId): void
    {
        $this->note('unlink');
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function parseWebhook(array $payload): WebhookEvent
    {
        return new WebhookEvent(WebhookEvent::IGNORED);
    }

    private function note(string $call): void
    {
        Log::info("Bank feeds (log driver, nothing sent): {$call}");
    }
}
