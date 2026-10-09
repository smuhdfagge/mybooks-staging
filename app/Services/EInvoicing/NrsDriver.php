<?php

namespace App\Services\EInvoicing;

use App\Models\EInvoiceSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The real NRS Merchant Buyer Solution (MBS) API.
 *
 * UNVERIFIED. NRS's own documents could not be read when this was written.
 * Everything below comes from third-party descriptions: the address of NRS,
 * the header names (x-api-key, x-api-secret), the endpoint paths and the
 * fields read from NRS's answers. All of the first three are in
 * config('mybooks.einvoicing') so they can be corrected without code; the
 * answer fields are read leniently (several likely names). Check all of it
 * against the NRS Postman collection in the sandbox before going live.
 *
 * Secrets: the key and secret are sent only in the two headers. Requests
 * and headers are never logged; only a status code and NRS's own message.
 */
class NrsDriver implements EInvoiceDriver
{
    public function name(): string
    {
        return 'nrs';
    }

    public function submit(array $payload, EInvoiceSetting $settings): NrsResult
    {
        try {
            $response = $this->http($settings)->post($this->path('submit'), $payload);
        } catch (ConnectionException) {
            return NrsResult::failed('We could not reach NRS. This is usually temporary, so it will be tried again.');
        }

        return $this->result($response, (string) ($payload['irn'] ?? ''));
    }

    public function confirm(string $irn, EInvoiceSetting $settings): NrsResult
    {
        try {
            $response = $this->http($settings)->get($this->path('confirm', ['irn' => $irn]));
        } catch (ConnectionException) {
            return NrsResult::failed('We could not reach NRS. This is usually temporary, so it will be tried again.');
        }

        // NRS not knowing the number is not a refusal: the send may simply not have arrived.
        if ($response->status() === 404) {
            return NrsResult::failed('NRS has no record of this invoice yet. It will be sent again.');
        }

        return $this->result($response, $irn);
    }

    public function test(EInvoiceSetting $settings): array
    {
        try {
            $response = $this->http($settings)->get($this->path('test'));
        } catch (ConnectionException) {
            return ['ok' => false, 'message' => 'We could not reach NRS. Check the address is right and try again.'];
        } catch (NotSetUp $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'NRS answered and accepted the keys.'];
        }
        if (in_array($response->status(), [401, 403], true)) {
            return ['ok' => false, 'message' => 'NRS did not accept the keys (HTTP '.$response->status().'). Check the API key and secret.'];
        }

        return ['ok' => false, 'message' => 'NRS answered with a problem (HTTP '.$response->status().'). '.($this->message($response) ?? '')];
    }

    private function http(EInvoiceSetting $settings): PendingRequest
    {
        $base = config('mybooks.einvoicing.base_urls.'.($settings->isLiveEnvironment() ? 'live' : 'sandbox'));
        if (! filled($base)) {
            throw new NotSetUp('The NRS address for the '.($settings->isLiveEnvironment() ? 'live' : 'sandbox').' environment has not been set by the MyBooks team yet.');
        }

        return Http::baseUrl(rtrim((string) $base, '/'))
            ->withHeaders([
                (string) config('mybooks.einvoicing.headers.key', 'x-api-key') => (string) $settings->api_key,
                (string) config('mybooks.einvoicing.headers.secret', 'x-api-secret') => (string) $settings->api_secret,
            ])
            ->acceptJson()->asJson()->connectTimeout(5)->timeout(30);
    }

    /** @param array<string, string> $replace */
    private function path(string $name, array $replace = []): string
    {
        $path = (string) config("mybooks.einvoicing.paths.{$name}", '/');
        foreach ($replace as $key => $value) {
            $path = str_replace('{'.$key.'}', rawurlencode($value), $path);
        }

        return $path;
    }

    private function result(Response $response, string $sentIrn): NrsResult
    {
        $status = $response->status();

        if ($response->successful()) {
            $irn = $this->first($response, ['data.irn', 'data.IRN', 'irn', 'IRN']) ?? ($sentIrn !== '' ? $sentIrn : null);
            $csid = $this->first($response, ['data.csid', 'data.CSID', 'csid', 'CSID', 'data.stamp', 'data.cryptographic_stamp']);
            $qr = $this->first($response, ['data.qr_code', 'data.qr', 'data.QRCodeData', 'qr_code', 'qr', 'QRCodeData']);
            $state = strtolower((string) $this->first($response, ['data.status', 'status']));

            if ($status === 202 || in_array($state, ['pending', 'queued', 'processing'], true)) {
                return NrsResult::pending($irn, 'NRS has the invoice and has not finished with it yet.');
            }
            if (in_array($state, ['rejected', 'failed', 'invalid'], true)) {
                return NrsResult::rejected($this->rejection($response));
            }

            return NrsResult::accepted($irn, $csid, $qr);
        }

        if (in_array($status, [401, 403], true)) {
            Log::warning('NRS did not accept the e-invoicing keys', ['status' => $status]);

            return NrsResult::failed("NRS did not accept the keys (HTTP {$status}). Check them in Settings > E-invoicing.");
        }
        if ($status === 408 || $status === 425 || $status === 429 || $status >= 500) {
            return NrsResult::failed("NRS is busy or offline (HTTP {$status}). It will be tried again.");
        }

        return NrsResult::rejected($this->rejection($response));
    }

    private function rejection(Response $response): string
    {
        $message = $this->message($response);

        return $message
            ? 'NRS did not accept this invoice: '.$message
            : 'NRS did not accept this invoice (HTTP '.$response->status().'). No reason was given.';
    }

    /** NRS's own wording of the problem, tidied and cut short. */
    private function message(Response $response): ?string
    {
        $text = $this->first($response, [
            'error.public_message', 'error.details', 'errorMessage', 'details', 'message', 'error_description', 'error',
        ]);
        if ($text === null) {
            return null;
        }

        return Str::limit(trim(strip_tags($text)), 400, '...');
    }

    /** @param list<string> $keys */
    private function first(Response $response, array $keys): ?string
    {
        $json = $response->json();
        if (! is_array($json)) {
            return null;
        }
        foreach ($keys as $key) {
            $value = data_get($json, $key);
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
            if (is_array($value) && $value !== [] && array_is_list($value) && is_string($value[0] ?? null)) {
                return implode(' ', $value);
            }
        }

        return null;
    }
}
