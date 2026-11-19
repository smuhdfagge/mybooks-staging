<?php

namespace Tests\Unit;

use App\Services\Push\FcmSender;
use App\Services\Push\PushMessage;
use App\Services\Push\PushSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Mobile plan A2: the Firebase HTTP v1 sender, with Google's servers faked.
 */
class FcmSenderTest extends TestCase
{
    private string $keyFile;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->keyFile = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($this->keyFile, json_encode([
            'type' => 'service_account', 'project_id' => 'mybooks-test', 'client_email' => 'push@mybooks-test.iam.gserviceaccount.com',
            'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        config(['services.fcm.credentials' => $this->keyFile, 'services.fcm.project_id' => null]);
    }

    protected function tearDown(): void
    {
        @unlink($this->keyFile);
        parent::tearDown();
    }

    private function message(): PushMessage
    {
        return new PushMessage(PushMessage::INVOICE_PAID, 42, 'Invoice paid', 'INV-0042 paid');
    }

    public function test_it_signs_in_with_the_service_account_and_sends_the_message(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/mybooks-test/messages/1']),
        ]);

        $sender = new FcmSender;
        $this->assertSame(PushSender::SENT, $sender->send('phone-token', $this->message()));
        $this->assertSame(PushSender::SENT, $sender->send('phone-token-2', $this->message()));

        Http::assertSentCount(3); // one sign-in, two sends: the access token is reused
        Http::assertSent(function (Request $r) {
            if (! str_contains($r->url(), 'oauth2')) {
                return false;
            }
            [$header, $claims] = array_map(fn ($p) => json_decode(base64_decode(strtr($p, '-_', '+/')), true), array_slice(explode('.', $r['assertion']), 0, 2));

            return $header['alg'] === 'RS256' && $claims['iss'] === 'push@mybooks-test.iam.gserviceaccount.com'
                && $claims['scope'] === 'https://www.googleapis.com/auth/firebase.messaging';
        });
        Http::assertSent(fn (Request $r) => $r->url() === 'https://fcm.googleapis.com/v1/projects/mybooks-test/messages:send'
            && $r->hasHeader('Authorization', 'Bearer ya29.test')
            && $r['message']['token'] === 'phone-token'
            && $r['message']['data'] === ['type' => 'invoice_paid', 'id' => '42']
            && $r['message']['notification']['title'] === 'Invoice paid');
    }

    public function test_a_dead_token_is_reported_and_other_errors_are_failures(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test']),
            'fcm.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)
                ->push(['error' => ['status' => 'INVALID_ARGUMENT', 'message' => 'The registration token is not a valid FCM registration token']], 400)
                ->push(['error' => ['status' => 'UNAVAILABLE']], 503),
        ]);

        $sender = new FcmSender;
        $this->assertSame(PushSender::INVALID_TOKEN, $sender->send('gone', $this->message()));
        $this->assertSame(PushSender::INVALID_TOKEN, $sender->send('garbage', $this->message()));
        $this->assertSame(PushSender::FAILED, $sender->send('ok-later', $this->message()));
    }

    public function test_without_credentials_nothing_is_sent(): void
    {
        Http::fake();
        config(['services.fcm.credentials' => null]);

        $this->assertSame(PushSender::SKIPPED, (new FcmSender)->send('phone-token', $this->message()));
        Http::assertNothingSent();
    }
}
