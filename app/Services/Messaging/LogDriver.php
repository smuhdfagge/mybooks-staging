<?php

namespace App\Services\Messaging;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes messages to the log instead of sending them: for local work and
 * tests, and what runs until the provider keys are set.
 */
class LogDriver implements SmsDriver, WhatsAppDriver
{
    public function name(): string
    {
        return 'log';
    }

    public function sendSms(string $to, string $text): SendResult
    {
        Log::info('SMS not sent (log driver)', ['to' => PhoneNumber::mask($to), 'text' => $text]);

        return SendResult::sent('log-'.Str::random(12));
    }

    public function sendTemplate(string $to, string $template, array $params, string $preview): SendResult
    {
        Log::info('WhatsApp not sent (log driver)', ['to' => PhoneNumber::mask($to), 'template' => $template, 'text' => $preview]);

        return SendResult::sent('log-'.Str::random(12));
    }
}
