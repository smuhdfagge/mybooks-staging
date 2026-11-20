<?php

namespace Tests\Feature\Api;

use App\Actions\Auth\DeleteUserAccount;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserDevice;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * DELETE /api/v1/auth/account: the mobile app's "Delete my account", which
 * the App Store and Google Play require.
 */
class DeleteAccountApiTest extends TestCase
{
    private function signedInWithToken(): array
    {
        $user = $this->createAuthenticatedUser();
        auth()->forgetGuards();

        return [$user, $user->createToken('MyBooks iOS test')->plainTextToken];
    }

    public function test_the_account_its_tokens_and_phones_go_and_the_books_stay(): void
    {
        [$user, $token] = $this->signedInWithToken();
        $user->createToken('Another phone');
        UserDevice::create([
            'user_id' => $user->id,
            'token' => 'fcm-token-1',
            'token_hash' => UserDevice::hashToken('fcm-token-1'),
            'platform' => 'ios',
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($token)->deleteJson('/api/v1/auth/account', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull(User::find($user->id));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, UserDevice::where('user_id', $user->id)->count());
        $this->assertNotNull(Customer::withoutGlobalScopes()->find($customer->id), 'the business keeps its books');
        $log = ActivityLog::withoutGlobalScopes()->where('action', ActivityLog::ACTION_DELETED)
            ->where('model_type', User::class)->where('model_id', $user->id)->first();
        $this->assertNotNull($log);
        $this->assertSame($user->name, $log->user_name);
    }

    public function test_the_token_stops_working(): void
    {
        [, $token] = $this->signedInWithToken();

        $this->withToken($token)->deleteJson('/api/v1/auth/account', ['password' => 'password'])->assertOk();
        auth()->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/user')->assertUnauthorized();
    }

    public function test_the_password_is_needed(): void
    {
        [$user, $token] = $this->signedInWithToken();

        $this->withToken($token)->deleteJson('/api/v1/auth/account', ['password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'The password is incorrect.');
        $this->withToken($token)->deleteJson('/api/v1/auth/account', [])
            ->assertUnprocessable();

        $this->assertNotNull(User::find($user->id));
    }

    public function test_the_last_admin_is_refused(): void
    {
        [$user, $token] = $this->signedInWithToken();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');

        $this->withToken($token)->deleteJson('/api/v1/auth/account', ['password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('message', DeleteUserAccount::LAST_ADMIN)
            ->assertJsonPath('errors.account.0', DeleteUserAccount::LAST_ADMIN);

        $this->assertNotNull(User::find($user->id));
    }

    public function test_an_admin_with_another_admin_can_go(): void
    {
        [$user, $token] = $this->signedInWithToken();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
        $this->createUserForTenant($this->tenant, [], ['is_active' => true])->assignRole('admin');

        $this->withToken($token)->deleteJson('/api/v1/auth/account', ['password' => 'password'])->assertOk();

        $this->assertNull(User::find($user->id));
    }

    public function test_it_works_without_a_subscription(): void
    {
        [$user, $token] = $this->signedInWithToken();
        $this->subscription->update(['status' => Subscription::STATUS_EXPIRED, 'ends_at' => now()->subDay()]);

        $this->withToken($token)->getJson('/api/v1/auth/user')->assertForbidden();
        $this->withToken($token)->deleteJson('/api/v1/auth/account', ['password' => 'password'])->assertOk();

        $this->assertNull(User::find($user->id));
    }

    public function test_deleting_on_the_web_also_ends_the_phones_sign_in(): void
    {
        $user = $this->createAuthenticatedUser();
        $user->createToken('MyBooks Android test');

        $this->delete('/profile', ['password' => 'password'])->assertRedirect('/');

        $this->assertNull($user->fresh());
        $this->assertSame(0, $user->tokens()->count());
    }
}
