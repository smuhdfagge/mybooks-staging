<?php

namespace App\Services\BankFeeds;

/** A linked account as the provider describes it. The balance is in kobo. */
final class AccountInfo
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name,
        public readonly ?string $institution,
        /** Last 4 digits only; the provider's full number is never kept. */
        public readonly ?string $mask,
        public readonly string $currency,
        public readonly ?int $balanceMinor,
        public readonly ?string $dataStatus,
    ) {}
}
