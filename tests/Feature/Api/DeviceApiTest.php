<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\UserDevice;
use Tests\TestCase;

/**
 * Mobile plan A2: phones registering for push notifications.
 */
class DeviceApiTest extends TestCase
{
    private function token(User $user): string
    {
        return $user->createToken('Test phone')->plainTextToken;
    }

    public function test_a_phone_registers_once_and_can_register_again(): void
    {
        $user = $this->createAuthenticatedUser();
        auth()->forgetGuards();
        $api = $this->token($user);

        $this->withToken($api)->postJson('/api/v1/devices', ['token' => 'fcm-token-1', 'platform' => 'android', 'app_version' => '1.0.0'])
            ->assertCreated()->assertJsonPath('data.platform', 'android');
        $this->withToken($api)->postJson('/api/v1/devices', ['token' => 'fcm-token-1', 'platform' => 'android', 'app_version' => '1.1.0'])
            ->assertOk()->assertJsonPath('data.app_version', '1.1.0');

        $this->assertSame(1, UserDevice::count());
        $device = UserDevice::first();
        $this->assertSame($user->id, $device->user_id);
        $this->assertNotNull($device->personal_access_token_id);
        $this->assertArrayNotHasKey('token', $device->toArray());
    }

    public function test_the_token_moves_to_whoever_signs_in_on_the_phone_last(): void
    {
        $first = $this->createAuthenticatedUser();
        $second = User::factory()->create(['tenant_id' => $this->tenant->id]);
        auth()->forgetGuards();

        $this->withToken($this->token($first))->postJson('/api/v1/devices', ['token' => 'shared-phone', 'platform' => 'ios'])->assertCreated();
        auth()->forgetGuards();
        $this->withToken($this->token($second))->postJson('/api/v1/devices', ['token' => 'shared-phone', 'platform' => 'ios'])->assertOk();

        $this->assertSame(1, UserDevice::count());
        $this->assertSame($second->id, UserDevice::first()->user_id);
    }

    public function test_bad_input_is_refused_and_signing_in_is_needed(): void
    {
        $this->postJson('/api/v1/devices', ['token' => 'x', 'platform' => 'ios'])->assertUnauthorized();

        $user = $this->createAuthenticatedUser();
        auth()->forgetGuards();
        $api = $this->token($user);
        $this->withToken($api)->postJson('/api/v1/devices', ['platform' => 'ios'])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->withToken($api)->postJson('/api/v1/devices', ['token' => 'x', 'platform' => 'windows'])->assertUnprocessable()->assertJsonValidationErrors('platform');
        $this->assertSame(0, UserDevice::count());
    }

    public function test_a_user_removes_only_their_own_device(): void
    {
        $user = $this->createAuthenticatedUser();
        $other = User::factory()->create(['tenant_id' => $this->tenant->id]);
        UserDevice::create(['user_id' => $other->id, 'token_hash' => UserDevice::hashToken('theirs:abc/1'), 'token' => 'theirs:abc/1', 'platform' => 'ios']);
        auth()->forgetGuards();
        $api = $this->token($user);
        $this->withToken($api)->postJson('/api/v1/devices', ['token' => 'mine:abc/1', 'platform' => 'android'])->assertCreated();

        $this->withToken($api)->deleteJson('/api/v1/devices/theirs:abc/1')->assertOk();
        $this->assertSame(2, UserDevice::count());

        $this->withToken($api)->deleteJson('/api/v1/devices/mine:abc/1')->assertOk();
        $this->assertSame(['theirs:abc/1'], UserDevice::pluck('token')->all());
    }

    public function test_signing_out_forgets_the_phone(): void
    {
        $user = $this->createAuthenticatedUser();
        auth()->forgetGuards();
        $api = $this->token($user);
        $this->withToken($api)->postJson('/api/v1/devices', ['token' => 'phone-a', 'platform' => 'android'])->assertCreated();

        $this->withToken($api)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertSame(0, UserDevice::count());
    }
}
