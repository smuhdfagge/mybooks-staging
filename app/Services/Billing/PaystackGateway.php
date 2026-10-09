<?php

namespace App\Services\Billing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The few Paystack API calls MyBooks needs for subscriptions (finding C1).
 *
 * https://paystack.com/docs/api/transaction/
 */
class PaystackGateway
{
    public function isConfigured(): bool
    {
        return filled(config('services.paystack.secret_key'));
    }

    /**
     * Start a payment and return the Paystack page to send the customer to.
     */
    public function initialize(string $email, int $amountInKobo, string $currency, string $reference, string $callbackUrl, array $metadata = []): string
    {
        $response = $this->client()->post('/transaction/initialize', [
            'email' => $email,
            'amount' => $amountInKobo,
            'currency' => $currency,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ]);

        $url = $response->json('data.authorization_url');

        if (! $response->successful() || ! $response->json('status') || ! $url) {
            throw new RuntimeException('Paystack could not start the payment: '.($response->json('message') ?? $response->status()));
        }

        return $url;
    }

    /**
     * Ask Paystack for the true state of a payment. Returns the transaction
     * data (status, amount, currency, reference, ...).
     */
    public function verify(string $reference): array
    {
        $response = $this->client()->get('/transaction/verify/'.rawurlencode($reference));

        if (! $response->successful() || ! $response->json('status')) {
            throw new RuntimeException('Paystack could not verify the payment: '.($response->json('message') ?? $response->status()));
        }

        return (array) $response->json('data');
    }

    /**
     * Charge a saved card again (session 15). Returns Paystack's transaction
     * data; its "status" is "success" only when the money was taken. A
     * refused request comes back as status "failed" with Paystack's message.
     * A lost connection throws: the charge may or may not have happened, so
     * the caller leaves it pending and checks it later with verify().
     *
     * Checked 2026-10-06 against https://paystack.com/docs/api/transaction/#charge-authorization
     * and https://paystack.com/docs/payments/recurring-charges/: POST
     * /transaction/charge_authorization with authorization_code, the email the
     * card was saved with, amount in kobo, our own reference. Only an
     * authorization with "reusable": true can be charged. data.status may also
     * be "send_otp"/"pending" or the reply "paused" (the bank wants the
     * customer to confirm): we treat those as not paid.
     */
    public function chargeAuthorization(string $authorizationCode, string $email, int $amountInKobo, string $currency, string $reference, array $metadata = []): array
    {
        $response = $this->client(retry: false)->post('/transaction/charge_authorization', [
            'authorization_code' => $authorizationCode,
            'email' => $email,
            'amount' => $amountInKobo,
            'currency' => $currency,
            'reference' => $reference,
            'metadata' => $metadata,
        ]);

        $data = (array) $response->json('data');

        if (! $response->successful() || ! $response->json('status') || $data === []) {
            return [
                'status' => 'failed',
                'reference' => $reference,
                'gateway_response' => (string) ($response->json('message') ?: 'Paystack refused the charge (HTTP '.$response->status().').'),
            ];
        }

        if ($response->json('data.paused')) {
            $data['status'] = 'paused';
        }

        return $data;
    }

    /**
     * Webhooks are signed with HMAC-SHA512 of the raw body using the secret
     * key, sent in the x-paystack-signature header (checked 2026-10-06,
     * https://paystack.com/docs/payments/webhooks/).
     */
    public function hasValidSignature(string $payload, ?string $signature): bool
    {
        $secret = (string) config('services.paystack.secret_key');

        if ($secret === '' || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }

    private function client(bool $retry = true)
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Paystack is not set up (PAYSTACK_SECRET_KEY is empty).');
        }

        $client = Http::baseUrl(rtrim((string) config('services.paystack.base_url'), '/'))
            ->withToken((string) config('services.paystack.secret_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(20);

        // A charge is never resent blindly: it might have gone through.
        return $retry
            ? $client->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false)
            : $client;
    }
}
