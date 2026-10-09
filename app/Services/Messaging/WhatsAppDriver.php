<?php

namespace App\Services\Messaging;

interface WhatsAppDriver
{
    public function name(): string;

    /**
     * Send an approved template. $params are the template's values, in the
     * template's order, keyed by name (customer, business, ...).
     *
     * @param  array<string, string>  $params
     *
     * @throws TemporaryFailure when it should be tried again
     */
    public function sendTemplate(string $to, string $template, array $params, string $preview): SendResult;
}
