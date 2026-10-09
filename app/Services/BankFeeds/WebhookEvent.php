<?php

namespace App\Services\BankFeeds;

/** What a provider webhook says, in the provider-neutral terms MyBooks uses. */
final class WebhookEvent
{
    public const CONNECTED = 'connected';          // the customer finished linking (accountId + ref)

    public const UPDATED = 'updated';              // new data may be available

    public const REAUTH_REQUIRED = 'reauth_required';

    public const REAUTHORISED = 'reauthorised';

    public const IGNORED = 'ignored';

    public function __construct(
        public readonly string $type,
        public readonly ?string $accountId = null,
        public readonly ?string $ref = null,
        /** null when the provider doesn't say */
        public readonly ?bool $hasNewData = null,
    ) {}
}
