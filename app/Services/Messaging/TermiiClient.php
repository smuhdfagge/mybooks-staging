<?php

namespace App\Services\Messaging;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Termii's REST API (checked 2026-10-06):
 *  - SMS: POST /api/sms/send {api_key, to, from, sms, type, channel}
 *    https://developers.termii.com/messaging-api
 *  - WhatsApp template: POST /api/send/template {api_key, phone_number,
 *    device_id, template_id, data} https://developers.termii.com/templates
 *  - Balance: GET /api/get-balance?api_key= https://developers.termii.com/balance
 *
 * The API key goes in the body (Termii's design), so request bodies are
 * never logged; only Termii's answer is kept.
 */
class TermiiClient
{
    public function configured(): bool
    {
        return filled(config('services.termii.api_key'));
    }

    /** @param array<string, mixed> $body */
    public function post(string $path, array $body): SendResult
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('services.termii.base_url'), '/'))
                ->acceptJson()->asJson()->connectTimeout(5)->timeout(20)
                ->post($path, ['api_key' => config('services.termii.api_key')] + $body);
        } catch (ConnectionException $e) {
            throw new TemporaryFailure('Could not reach Termii: '.$e->getMessage());
        }

        return $this->result($response);
    }

    /** Account balance, for the platform admin page. */
    public function balance(): ?array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('services.termii.base_url'), '/'))
                ->acceptJson()->connectTimeout(3)->timeout(5)
                ->get('/api/get-balance', ['api_key' => config('services.termii.api_key')]);
        } catch (\Throwable) {
            return null; // only for display; never break the admin page
        }

        return $response->successful() ? ['balance' => (float) $response->json('balance'), 'currency' => (string) $response->json('currency')] : null;
    }

    private function result(Response $response): SendResult
    {
        if ($response->serverError() || $response->status() === 429) {
            throw new TemporaryFailure('Termii is busy (HTTP '.$response->status().')');
        }

        $id = $response->json('message_id_str') ?? $response->json('message_id');
        if ($response->successful() && $id) {
            return SendResult::sent((string) $id);
        }

        return SendResult::failed('Termii: '.($response->json('message') ?? 'HTTP '.$response->status()));
    }
}
