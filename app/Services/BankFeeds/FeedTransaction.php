<?php

namespace App\Services\BankFeeds;

use Carbon\CarbonImmutable;

/** One bank transaction from the provider. The amount is positive naira; see $direction. */
final class FeedTransaction
{
    public function __construct(
        public readonly string $id,
        public readonly CarbonImmutable $date,
        public readonly float $amount,
        /** debit (money out) or credit (money in) */
        public readonly string $direction,
        public readonly ?string $narration,
        public readonly ?float $balanceAfter,
    ) {}
}
