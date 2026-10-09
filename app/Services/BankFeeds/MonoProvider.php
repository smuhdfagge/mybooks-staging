<?php

namespace App\Services\BankFeeds;

use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Mono (https://mono.co), Nigeria's open banking provider. Docs checked
 * 2026-10-09:
 *  - hosted link: POST /v2/accounts/initiate {customer, meta.ref, scope:"auth",
 *    redirect_url} -> data.mono_url; the customer returns to redirect_url
 *    with the one-time code (valid 10 minutes)
 *    https://docs.mono.co/docs/financial-data/connect-link
 *  - exchange the code: POST /v2/accounts/auth {code} -> id (the lasting
 *    account id) https://docs.mono.co/docs/financial-data/integration-guide
 *  - account details: GET /v2/accounts/{id}
 *    https://docs.mono.co/api/bank-data/accounts/details
 *  - transactions: GET /v2/accounts/{id}/transactions?start=DD-MM-YYYY&end=
 *    &paginate=true&page=N, amounts in kobo https://docs.mono.co/api/bank-data/transactions
 *  - refresh now: the x-realtime header on balance / transactions; at most one
 *    session per account every 5 minutes, answered by the
 *    mono.events.account_updated webhook https://docs.mono.co/docs/financial-data/realtime-data
 *  - log in again: POST /v2/accounts/initiate {scope:"reauth", account}
 *    https://docs.mono.co/api/bank-data/authorisation/initiate-account-reauth
 *  - unlink: POST /v2/accounts/{id}/unlink
 *  - webhooks: header mono-webhook-secret equals the secret set on the
 *    dashboard; events mono.events.account_connected / account_updated /
 *    account_reauthorized https://docs.mono.co/docs/webhooks
 *
 * Not spelled out in the docs, so read leniently and to be confirmed in the
 * sandbox: the query name the code comes back in (we accept "code"), the
 * shape of the reauth answer (we read data.mono_url like initiate), the
 * transaction page fields (meta.next / meta.total) and the exact event name
 * that says "log in again" (any event name containing "reauth" other than
 * "reauthorized", or sync_status REAUTHORISATION_REQUIRED).
 *
 * The secret key is sent only in the mono-sec-key header and never logged.
 */
class MonoProvider implements BankFeedProvider
{
    public function name(): string
    {
        return 'mono';
    }

    public function isLive(): bool
    {
        return filled(config('services.mono.secret_key'));
    }

    public function startLinking(string $reference, string $customerName, string $customerEmail, string $redirectUrl): string
    {
        $response = $this->send('POST', '/v2/accounts/initiate', [
            'customer' => ['name' => $customerName, 'email' => $customerEmail],
            'meta' => ['ref' => $reference],
            'scope' => 'auth',
            'redirect_url' => $redirectUrl,
        ]);

        return $this->linkUrl($response);
    }

    public function finishLinking(string $code): string
    {
        $response = $this->send('POST', '/v2/accounts/auth', ['code' => $code]);
        $id = $response->json('id') ?? $response->json('data.id');
        if (! is_string($id) || $id === '') {
            throw new BankFeedException('The bank link could not be completed. Please try again.');
        }

        return $id;
    }

    public function accountDetails(string $accountId): AccountInfo
    {
        $json = $this->send('GET', "/v2/accounts/{$accountId}")->json() ?? [];
        $account = data_get($json, 'data.account') ?? data_get($json, 'account') ?? data_get($json, 'data') ?? $json;
        $meta = data_get($json, 'data.meta') ?? data_get($json, 'meta') ?? [];

        $number = (string) ($account['account_number'] ?? $account['accountNumber'] ?? '');
        $balance = $account['balance'] ?? null;

        return new AccountInfo(
            id: $accountId,
            name: isset($account['name']) ? (string) $account['name'] : null,
            institution: isset($account['institution']['name']) ? (string) $account['institution']['name'] : null,
            mask: $number !== '' ? substr(preg_replace('/\D/', '', $number) ?? '', -4) : null,
            currency: strtoupper((string) ($account['currency'] ?? 'NGN')),
            balanceMinor: is_numeric($balance) ? (int) round((float) $balance) : null,
            dataStatus: isset($meta['data_status']) ? strtolower((string) $meta['data_status']) : null,
        );
    }

    public function fetchTransactions(string $accountId, CarbonInterface $since, ?CarbonInterface $until = null): array
    {
        $until ??= CarbonImmutable::now();
        $found = [];
        $max = max(1, (int) config('mybooks.bank_feeds.max_pages', 100));

        for ($page = 1; $page <= $max; $page++) {
            $json = $this->send('GET', "/v2/accounts/{$accountId}/transactions", query: [
                'start' => $since->format('d-m-Y'),
                'end' => $until->format('d-m-Y'),
                'paginate' => 'true',
                'page' => $page,
            ])->json() ?? [];

            $rows = (array) ($json['data'] ?? []);
            foreach ($rows as $row) {
                if ($tx = $this->transaction((array) $row)) {
                    $found[$tx->id] = $tx;
                }
            }

            // Another page only when Mono says there is one.
            $meta = (array) ($json['meta'] ?? []);
            $more = $rows !== [] && (
                ! empty($meta['next'])
                || (isset($meta['total'], $meta['limit']) && $page * (int) $meta['limit'] < (int) $meta['total'])
            );
            if (! $more) {
                break;
            }
        }

        $list = array_values($found);
        usort($list, fn (FeedTransaction $a, FeedTransaction $b) => [$a->date, $a->id] <=> [$b->date, $b->id]);

        return $list;
    }

    public function requestSync(string $accountId): void
    {
        // The balance call with x-realtime starts a live refresh; Mono then
        // sends mono.events.account_updated when it is done.
        $this->send('GET', "/v2/accounts/{$accountId}/balance", headers: ['x-realtime' => 'true', 'x-real-time' => 'true']);
    }

    public function startReauthorisation(string $accountId, string $reference, string $redirectUrl): string
    {
        $response = $this->send('POST', '/v2/accounts/initiate', [
            'meta' => ['ref' => $reference],
            'scope' => 'reauth',
            'account' => $accountId,
            'redirect_url' => $redirectUrl,
        ]);

        return $this->linkUrl($response);
    }

    public function unlink(string $accountId): void
    {
        $this->send('POST', "/v2/accounts/{$accountId}/unlink");
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) config('services.mono.webhook_secret');

        return $this->isLive() && $secret !== '' && hash_equals($secret, (string) $request->header('mono-webhook-secret'));
    }

    public function parseWebhook(array $payload): WebhookEvent
    {
        $event = strtolower((string) ($payload['event'] ?? ''));
        $data = (array) ($payload['data'] ?? []);
        $meta = (array) ($data['meta'] ?? $payload['meta'] ?? []);
        $accountId = $data['account']['_id'] ?? $data['account']['id'] ?? $data['id'] ?? $data['_id'] ?? null;
        $accountId = is_scalar($accountId) ? (string) $accountId : null;

        if (str_ends_with($event, 'account_connected')) {
            return new WebhookEvent(WebhookEvent::CONNECTED, $accountId, isset($meta['ref']) ? (string) $meta['ref'] : null);
        }
        if (str_ends_with($event, 'account_reauthorized') || str_ends_with($event, 'account_reauthorised')) {
            return new WebhookEvent(WebhookEvent::REAUTHORISED, $accountId);
        }
        if (str_ends_with($event, 'account_updated')) {
            if (strtoupper((string) ($meta['sync_status'] ?? '')) === 'REAUTHORISATION_REQUIRED') {
                return new WebhookEvent(WebhookEvent::REAUTH_REQUIRED, $accountId);
            }

            return new WebhookEvent(WebhookEvent::UPDATED, $accountId, null, array_key_exists('has_new_data', $meta) ? (bool) $meta['has_new_data'] : null);
        }
        if (str_contains($event, 'reauth')) {
            return new WebhookEvent(WebhookEvent::REAUTH_REQUIRED, $accountId);
        }

        return new WebhookEvent(WebhookEvent::IGNORED, $accountId);
    }

    // ---- internals --------------------------------------------------------

    /** @param  array<string, mixed>  $row */
    private function transaction(array $row): ?FeedTransaction
    {
        $id = $row['id'] ?? $row['_id'] ?? null;
        $type = strtolower((string) ($row['type'] ?? ''));
        if (! is_scalar($id) || $id === '' || ! in_array($type, ['debit', 'credit'], true) || ! isset($row['amount']) || empty($row['date'])) {
            return null; // not a usable line; skipped rather than guessed
        }

        try {
            // Dates are kept as the bank's local (Lagos) calendar day.
            $date = CarbonImmutable::parse((string) $row['date'])->setTimezone((string) config('mybooks.messaging.timezone', 'Africa/Lagos'))->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return new FeedTransaction(
            id: (string) $id,
            date: $date,
            amount: Money::fromMinor(abs((int) round((float) $row['amount']))),
            direction: $type,
            narration: isset($row['narration']) ? Str::limit(trim((string) $row['narration']), 500, '') : null,
            balanceAfter: isset($row['balance']) && is_numeric($row['balance']) ? Money::fromMinor((int) round((float) $row['balance'])) : null,
        );
    }

    private function linkUrl(Response $response): string
    {
        $url = $response->json('data.mono_url') ?? $response->json('mono_url');
        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new BankFeedException('The bank link could not be started. Please try again.');
        }

        return $url;
    }

    /**
     * One call, with up to 3 tries on a busy or failing Mono. Returns a
     * successful response or throws a BankFeedException the user can read.
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    private function send(string $method, string $path, array $body = [], array $query = [], array $headers = []): Response
    {
        if (! $this->isLive()) {
            throw new NotSetUp;
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('services.mono.base_url'), '/'))
                ->withHeaders(['mono-sec-key' => (string) config('services.mono.secret_key')] + $headers)
                ->acceptJson()->connectTimeout(5)->timeout(30)
                ->retry(3, 500, fn ($e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError())), throw: false)
                ->send($method, $path, array_filter([
                    'query' => $query ?: null,
                    'json' => $body ?: null,
                ]));
        } catch (ConnectionException) {
            throw new TemporaryFailure('Could not reach Mono. We will try again shortly.');
        }

        if ($response->successful()) {
            return $response;
        }

        $status = $response->status();
        $message = trim((string) ($response->json('message') ?? ''));
        if ($status === 429 || $response->serverError()) {
            throw new TemporaryFailure('Mono is busy right now (HTTP '.$status.'). We will try again shortly.');
        }
        if (in_array($status, [400, 403, 428], true) && stripos($response->body(), 'reauth') !== false) {
            throw new ReauthorisationRequired('The bank needs you to log in again.');
        }
        if ($status === 401) {
            throw new BankFeedException('Mono did not accept the MyBooks keys. The MyBooks team needs to check them.');
        }

        throw new BankFeedException($message !== '' ? 'Mono says: '.Str::limit($message, 200) : 'Mono could not do that (HTTP '.$status.').');
    }
}
