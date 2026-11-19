<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Firebase Cloud Messaging, HTTP v1 API (mobile plan A2), for Android and
 * iOS alike. Signs in with the Firebase project's service account: the JSON
 * key file named by FCM_CREDENTIALS, kept outside the repository. Without
 * it, nothing is sent (SKIPPED), so the rest of the app works unchanged.
 */
class FcmSender implements PushSender
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** @var array<string, mixed>|null */
    private ?array $account = null;

    public function send(string $token, PushMessage $message): string
    {
        $account = $this->account();
        if ($account === null) {
            return self::SKIPPED;
        }

        $projectId = (string) (config('services.fcm.project_id') ?: $account['project_id']);
        $response = Http::withToken($this->accessToken($account))
            ->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $message->title, 'body' => $message->body],
                    'data' => $message->data(),
                    'android' => ['priority' => 'high', 'notification' => ['sound' => 'default']],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                ],
            ]);

        if ($response->successful()) {
            return self::SENT;
        }
        if (self::isInvalidToken($response->status(), (array) $response->json())) {
            return self::INVALID_TOKEN;
        }
        if ($response->status() === 401) {
            Cache::forget($this->cacheKey($account)); // a fresh access token next time
        }
        Log::warning('FCM send failed', ['status' => $response->status(), 'error' => $response->json('error.status')]);

        return self::FAILED;
    }

    /**
     * FCM says a token is dead with UNREGISTERED (the app was removed or the
     * token replaced), or 404, or INVALID_ARGUMENT about the token itself.
     *
     * @param  array<string, mixed>  $body
     */
    public static function isInvalidToken(int $status, array $body): bool
    {
        $codes = collect($body['error']['details'] ?? [])->pluck('errorCode')->filter()->all();
        if (in_array('UNREGISTERED', $codes, true) || $status === 404) {
            return true;
        }

        return $status === 400
            && ($body['error']['status'] ?? null) === 'INVALID_ARGUMENT'
            && str_contains(strtolower((string) ($body['error']['message'] ?? '')), 'registration token');
    }

    /** @return array<string, mixed>|null */
    private function account(): ?array
    {
        if ($this->account !== null) {
            return $this->account;
        }
        $path = (string) config('services.fcm.credentials');
        if ($path === '' || ! is_readable($path)) {
            return null;
        }
        $account = json_decode((string) file_get_contents($path), true);
        if (! is_array($account) || empty($account['client_email']) || empty($account['private_key']) || empty($account['project_id'])) {
            Log::error('FCM_CREDENTIALS is not a Firebase service account key file.');

            return null;
        }

        return $this->account = $account;
    }

    /** @param array<string, mixed> $account */
    private function accessToken(array $account): string
    {
        return Cache::remember($this->cacheKey($account), now()->addMinutes(50), function () use ($account) {
            $response = Http::asForm()->timeout(10)->post((string) ($account['token_uri'] ?? 'https://oauth2.googleapis.com/token'), [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->signedJwt($account),
            ]);
            $token = $response->json('access_token');
            if (! $response->successful() || ! is_string($token) || $token === '') {
                throw new RuntimeException('Could not get a Firebase access token (HTTP '.$response->status().').');
            }

            return $token;
        });
    }

    /** @param array<string, mixed> $account */
    private function signedJwt(array $account): string
    {
        $now = time();
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $unsigned = $b64((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            .'.'.$b64((string) json_encode([
                'iss' => $account['client_email'],
                'scope' => self::SCOPE,
                'aud' => $account['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
        if (! openssl_sign($unsigned, $signature, (string) $account['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign with the Firebase service account key.');
        }

        return $unsigned.'.'.$b64($signature);
    }

    /** @param array<string, mixed> $account */
    private function cacheKey(array $account): string
    {
        return 'fcm-access-token:'.sha1((string) $account['client_email']);
    }
}
