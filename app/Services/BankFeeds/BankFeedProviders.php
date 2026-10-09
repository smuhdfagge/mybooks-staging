<?php

namespace App\Services\BankFeeds;

/**
 * Picks the bank feed provider from config (session 17). 'auto' means Mono
 * once its secret key is set, else 'log' (nothing is called).
 */
class BankFeedProviders
{
    public function driverName(): string
    {
        $name = (string) config('mybooks.bank_feeds.driver', 'auto');

        return match ($name) {
            'mono', 'auto' => app(MonoProvider::class)->isLive() ? 'mono' : 'log',
            default => 'log',
        };
    }

    public function isLive(): bool
    {
        return $this->driverName() !== 'log';
    }

    public function driver(): BankFeedProvider
    {
        return match ($this->driverName()) {
            'mono' => app(MonoProvider::class),
            default => app(LogProvider::class),
        };
    }

    /** The provider behind a stored connection (its name is saved on the row). */
    public function for(string $providerName): BankFeedProvider
    {
        return match ($providerName) {
            'mono' => app(MonoProvider::class),
            default => app(LogProvider::class),
        };
    }
}
