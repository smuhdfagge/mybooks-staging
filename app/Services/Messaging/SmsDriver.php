<?php

namespace App\Services\Messaging;

interface SmsDriver
{
    /** 'termii', 'log', ... (stored on each message). */
    public function name(): string;

    /**
     * @param  string  $to  +2348031234567
     *
     * @throws TemporaryFailure when it should be tried again
     */
    public function sendSms(string $to, string $text): SendResult;
}
