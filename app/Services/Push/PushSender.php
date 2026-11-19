<?php

namespace App\Services\Push;

/** Sends one push notification to one phone (mobile plan A2). */
interface PushSender
{
    /** Delivered (or accepted for delivery). */
    public const SENT = 'sent';

    /** The token is no longer valid: forget the device. */
    public const INVALID_TOKEN = 'invalid_token';

    /** Anything else; try again next time. */
    public const FAILED = 'failed';

    /** Not configured: nothing was sent. */
    public const SKIPPED = 'skipped';

    /** @return self::SENT|self::INVALID_TOKEN|self::FAILED|self::SKIPPED */
    public function send(string $token, PushMessage $message): string;
}
