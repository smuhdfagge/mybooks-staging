<?php

namespace App\Services\Messaging;

/**
 * WhatsApp template messages through Termii: the template (approved by
 * Meta through Termii) is named by its Termii template ID, and its values
 * go by name in "data".
 */
class TermiiWhatsAppDriver implements WhatsAppDriver
{
    public function __construct(private TermiiClient $client) {}

    public function name(): string
    {
        return 'termii';
    }

    public function sendTemplate(string $to, string $template, array $params, string $preview): SendResult
    {
        return $this->client->post('/api/send/template', [
            'phone_number' => ltrim($to, '+'),
            'device_id' => (string) config('services.termii.whatsapp_device_id'),
            'template_id' => $template,
            'data' => (object) $params,
        ]);
    }
}
