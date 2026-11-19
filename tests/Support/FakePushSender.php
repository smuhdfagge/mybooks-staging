<?php

namespace Tests\Support;

use App\Services\Push\PushMessage;
use App\Services\Push\PushSender;

/** Records pushes instead of sending them; tokens can be marked dead. */
class FakePushSender implements PushSender
{
    /** @var array<int, array{token: string, message: PushMessage}> */
    public array $sent = [];

    /** @var array<int, string> */
    public array $deadTokens = [];

    public function send(string $token, PushMessage $message): string
    {
        if (in_array($token, $this->deadTokens, true)) {
            return self::INVALID_TOKEN;
        }
        $this->sent[] = ['token' => $token, 'message' => $message];

        return self::SENT;
    }

    /** @return array<int, string> tokens that got a push of this type */
    public function tokensFor(string $type): array
    {
        return array_values(array_map(fn ($s) => $s['token'], array_filter($this->sent, fn ($s) => $s['message']->type === $type)));
    }
}
