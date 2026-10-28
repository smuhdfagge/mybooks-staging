<?php

namespace Tests\Feature\Billing;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/** billing:check-paystack: checks the set-up without ever printing a key. */
class CheckPaystackCommandTest extends TestCase
{
    private string $secret;

    private string $public;

    protected function setUp(): void
    {
        parent::setUp();
        // Made up per run, so no key-like string sits in the repository.
        $this->secret = 'sk_test_'.Str::random(40);
        $this->public = 'pk_test_'.Str::random(40);
        config([
            'services.paystack.secret_key' => $this->secret,
            'services.paystack.public_key' => $this->public,
            'services.paystack.base_url' => 'https://api.paystack.co',
            'services.paystack.currency' => 'NGN',
            'app.url' => 'https://books.example.com',
        ]);
    }

    public function test_a_working_key_passes_and_is_never_shown(): void
    {
        Http::fake(['api.paystack.co/transaction*' => Http::response(['status' => true, 'message' => 'Transactions retrieved', 'data' => []])]);

        $this->artisan('billing:check-paystack')
            ->expectsOutputToContain('set, test mode')
            ->expectsOutputToContain('Paystack accepted the secret key.')
            ->expectsOutputToContain('/billing/paystack/webhook')
            ->doesntExpectOutputToContain($this->secret)
            ->doesntExpectOutputToContain($this->public)
            ->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://api.paystack.co/transaction?')
            && $request['perPage'] === 1
            && $request->hasHeader('Authorization', 'Bearer '.$this->secret));
    }

    public function test_a_refused_key_fails_with_paystacks_reason(): void
    {
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401)]);

        $this->artisan('billing:check-paystack')
            ->expectsOutputToContain('Paystack refused the secret key: Invalid key')
            ->expectsOutputToContain('Paystack is not ready')
            ->doesntExpectOutputToContain($this->secret)
            ->assertFailed();
    }

    public function test_no_secret_key_fails_without_calling_paystack(): void
    {
        config(['services.paystack.secret_key' => '']);
        Http::fake();

        $this->artisan('billing:check-paystack')->expectsOutputToContain('not set')->assertFailed();
        Http::assertNothingSent();
    }

    public function test_offline_checks_settings_only_and_flags_a_live_key_outside_production(): void
    {
        config(['services.paystack.secret_key' => 'sk_live_'.Str::random(40)]);
        Http::fake();

        $this->artisan('billing:check-paystack', ['--offline' => true])
            ->expectsOutputToContain('live key outside production')
            ->assertSuccessful();
        Http::assertNothingSent();
    }
}
