<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

/**
 * Mobile plan A1: what the app reads at start, the modules that are on and
 * the app versions.
 */
class MobileAppApiTest extends TestCase
{
    public function test_the_signed_in_user_says_which_modules_are_on(): void
    {
        $this->createAuthenticatedUser(['view dashboard']);
        config(['mybooks.features.assembly' => false, 'mybooks.features.quotations' => true]);

        $this->getJson('/api/v1/auth/user')->assertOk()
            ->assertJsonPath('data.modules.assembly', false)
            ->assertJsonPath('data.modules.quotations', true)
            ->assertJsonPath('data.modules.warehouses', true)
            ->assertJsonPath('data.modules.stock_transfers', true);

        // Stock transfers need warehouses too, as the routes check.
        config(['mybooks.features.warehouses' => false]);
        $this->getJson('/api/v1/auth/user')->assertOk()
            ->assertJsonPath('data.modules.warehouses', false)
            ->assertJsonPath('data.modules.stock_transfers', false);
    }

    public function test_signing_in_returns_the_modules_too(): void
    {
        $user = $this->createAuthenticatedUser();
        auth()->forgetGuards();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Test phone'])
            ->assertOk()->assertJsonStructure(['data' => ['user' => ['modules' => ['assembly', 'stock_transfers', 'warehouses']], 'token']]);
    }

    public function test_the_app_version_is_public_and_comes_from_config(): void
    {
        config([
            'mybooks.mobile_app.min_version' => '1.2.0',
            'mybooks.mobile_app.latest_version' => '1.4.1',
            'mybooks.mobile_app.ios_store_url' => 'https://apps.apple.com/app/id000',
            'mybooks.mobile_app.android_store_url' => '',
        ]);

        $this->getJson('/api/v1/app/version')->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.min_version', '1.2.0')
            ->assertJsonPath('data.latest_version', '1.4.1')
            ->assertJsonPath('data.ios_store_url', 'https://apps.apple.com/app/id000')
            ->assertJsonPath('data.android_store_url', null);
    }
}
