<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class MobileAppVersionApiTest extends TestCase
{
    public function test_version_policy_is_public_and_uses_configured_values(): void
    {
        config([
            'mybooks.mobile_app' => [
                'min_version' => '0.1.0',
                'latest_version' => '1.0.0',
                'ios_store_url' => null,
                'android_store_url' => 'https://play.google.com/store/apps/details?id=cloud.mybooks.app',
            ],
        ]);

        $this->getJson('/api/v1/app/version')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.min_version', '0.1.0')
            ->assertJsonPath('data.latest_version', '1.0.0')
            ->assertJsonPath('data.ios_store_url', null)
            ->assertJsonPath(
                'data.android_store_url',
                'https://play.google.com/store/apps/details?id=cloud.mybooks.app',
            );
    }
}
