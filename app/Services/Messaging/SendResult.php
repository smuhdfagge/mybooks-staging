<?php

namespace App\Services\Messaging;

/**
 * What the provider said about one message (session 16).
 */
final class SendResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $providerId = null,
        public readonly ?float $cost = null,
        public readonly ?string $error = null,
    ) {}

    public static function sent(?string $providerId, ?float $cost = null): self
    {
        return new self(true, $providerId, $cost);
    }

    /** Refused for good (bad number, template not approved...): not retried. */
    public static function failed(string $error): self
    {
        return new self(false, null, null, mb_substr($error, 0, 255));
    }
}
