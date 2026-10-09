<?php

namespace App\Services\Messaging;

use App\Support\SmsText;

/**
 * SMS through Termii. Sent on the 'dnd' route by default: reminders and
 * receipts are transactional (about the customer's own invoice), and the
 * 'generic' route drops numbers on the NCC Do-Not-Disturb list.
 */
class TermiiSmsDriver implements SmsDriver
{
    public function __construct(private TermiiClient $client) {}

    public function name(): string
    {
        return 'termii';
    }

    public function sendSms(string $to, string $text): SendResult
    {
        return $this->client->post('/api/sms/send', [
            'to' => ltrim($to, '+'),
            'from' => (string) config('services.termii.sender_id'),
            'sms' => $text,
            'type' => SmsText::isGsm($text) ? 'plain' : 'unicode',
            'channel' => (string) config('services.termii.sms_channel', 'dnd'),
        ]);
    }
}
