<?php

namespace App\Services\BankFeeds;

/** The best MyBooks record for a bank line, with how sure we are. */
final class Suggestion
{
    public function __construct(
        public readonly Candidate $candidate,
        /** 0-100 */
        public readonly int $score,
    ) {}

    public function label(): string
    {
        $high = (int) config('mybooks.bank_feeds.high_confidence', 80);

        return match (true) {
            $this->score >= $high => 'High',
            $this->score >= 60 => 'Medium',
            default => 'Low',
        };
    }

    public function isHigh(): bool
    {
        return $this->score >= (int) config('mybooks.bank_feeds.high_confidence', 80);
    }
}
