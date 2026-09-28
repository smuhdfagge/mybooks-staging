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
     * Webhooks are signed with HMAC-SHA512 of the raw body using the secret key.
     */
    public function hasValidSignature(string $payload, ?string $signature): bool
    {
        $secret = (string) config('services.paystack.secret_key');

        if ($secret === '' || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }

    private function client()
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Paystack is not set up (PAYSTACK_SECRET_KEY is empty).');
        }

        return Http::baseUrl(rtrim((string) config('services.paystack.base_url'), '/'))
            ->withToken((string) config('services.paystack.secret_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false);
    }
}
