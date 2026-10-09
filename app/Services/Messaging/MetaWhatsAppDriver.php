<?php

namespace App\Services\Messaging;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Cloud API (Meta), checked 2026-10-06:
 * POST https://graph.facebook.com/{version}/{phone-number-id}/messages with
 * a template message; the values fill {{1}}, {{2}}... in order.
 * https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-message-templates
 *
 * Messages a business starts must use an approved template. Meta charges
 * per template message (utility rate) since 1 July 2025.
 */
class MetaWhatsAppDriver implements WhatsAppDriver
{
    public function name(): string
    {
        return 'meta';
    }

    public function sendTemplate(string $to, string $template, array $params, string $preview): SendResult
    {
        $url = rtrim((string) config('services.whatsapp_meta.base_url'), '/').'/'.config('services.whatsapp_meta.api_version')
            .'/'.config('services.whatsapp_meta.phone_number_id').'/messages';

        try {
            $response = Http::withToken((string) config('services.whatsapp_meta.token'))
                ->acceptJson()->asJson()->connectTimeout(5)->timeout(20)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => ltrim($to, '+'),
                    'type' => 'template',
                    'template' => [
                        'name' => $template,
                        'language' => ['code' => (string) config('services.whatsapp_meta.language', 'en')],
                        'components' => [[
                            'type' => 'body',
                            'parameters' => array_map(fn ($value) => ['type' => 'text', 'text' => (string) $value], array_values($params)),
                        ]],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new TemporaryFailure('Could not reach WhatsApp: '.$e->getMessage());
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new TemporaryFailure('WhatsApp is busy (HTTP '.$response->status().')');
        }

        $id = $response->json('messages.0.id');
        if ($response->successful() && $id) {
            return SendResult::sent((string) $id);
        }

        return SendResult::failed('WhatsApp: '.($response->json('error.message') ?? 'HTTP '.$response->status()));
    }
}
