<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Livewire\Auth\RegisterWizard;
use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\DataRequest;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 20 (the rest of security hardening):
 * - MB-27: API sign-in was limited per address only;
 * - MB-28: sign-up took any plan id, including retired plans;
 * - MB-47: tokens carried an ability list nothing checked;
 * - MB-59: admin data-request actions weren't in the admin audit log, and
 *   deactivating the last admin who can manage admins wasn't stopped.
 */
class Session20SecurityHardeningTest extends TestCase
{
    /** Made per run, not written here: a literal reads as a leaked secret to the scanners. */
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secret = Str::password(20);
    }

    /** A user whose password is $this->secret, signed out. */
    private function apiUser(): User
    {
        $user = $this->createAuthenticatedUser(['view customers']);
        $user->forceFill(['password' => Hash::make($this->secret)])->save();
        auth()->logout();

        return $user;
    }

    private function apiLogin(string $email, string $password, string $ip)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password, 'device_name' => 'phone']);
    }

    public function test_api_sign_in_locks_one_account_after_five_wrong_passwords_from_any_address(): void
    {
        $user = $this->apiUser();

        // Each from its own address, so the per-address limit never trips.
        foreach (range(1, 5) as $i) {
            $this->apiLogin(strtoupper($user->email), 'wrong', "10.9.0.{$i}")->assertUnprocessable();
        }

        $this->apiLogin($user->email, $this->secret, '10.9.1.1')
            ->assertStatus(429)->assertJsonFragment(['success' => false])->assertHeader('Retry-After');
        $this->assertTrue(ActivityLog::withoutGlobalScopes()->where('action', ActivityLog::ACTION_ACCOUNT_LOCKED)->where('user_name', strtoupper($user->email))->exists());

        // Another account from the same addresses is unaffected.
        $other = $this->createUserForTenant($this->tenant);
        $other->forceFill(['password' => Hash::make($this->secret)])->save();
        $this->apiLogin($other->email, $this->secret, '10.9.0.1')->assertOk();
    }

    public function test_a_correct_api_sign_in_clears_the_count(): void
    {
        $user = $this->apiUser();

        foreach (range(1, 4) as $i) {
            $this->apiLogin($user->email, 'wrong', "10.8.0.{$i}");
        }
        $this->apiLogin($user->email, $this->secret, '10.8.1.1')->assertOk();
        foreach (range(1, 4) as $i) {
            $this->apiLogin($user->email, 'wrong', "10.8.2.{$i}");
        }
        $this->apiLogin($user->email, $this->secret, '10.8.3.1')->assertOk();
    }

    public function test_api_tokens_carry_no_stale_ability_list(): void
    {
        $user = $this->apiUser();

        $this->apiLogin($user->email, $this->secret, '10.7.0.1')->assertOk();

        // What the token may do is the user's permissions, checked live.
        $this->assertSame(['*'], PersonalAccessToken::where('tokenable_id', $user->id)->sole()->abilities);
    }

    private function signUp(Plan $plan)
    {
        return Livewire::test(RegisterWizard::class)
            ->set('plan_id', $plan->id)
            ->set('company_name', 'Retired Plan Ltd')
            ->set('name', 'Owner')
            ->set('email', 'owner@retired.test')
            ->set('password', $this->secret)
            ->call('register');
    }

    public function test_sign_up_refuses_a_plan_that_is_not_on_sale(): void
    {
        Notification::fake();
        $retired = Plan::factory()->create(['is_active' => false]);

        $this->signUp($retired)->assertHasErrors(['plan_id' => 'exists']);
        $this->assertGuest();

        $this->post(route('register'), [
            'plan_id' => $retired->id, 'billing_cycle' => 'monthly',
            'company_name' => 'Retired Plan Ltd', 'company_email' => 'co@retired.test', 'currency' => 'NGN',
            'name' => 'Owner', 'email' => 'owner2@retired.test', 'password' => $this->secret, 'password_confirmation' => $this->secret,
        ])->assertSessionHasErrors(['plan_id' => 'That plan is no longer available. Choose another.']);
        $this->assertGuest();
    }

    public function test_sign_up_still_takes_a_plan_on_sale(): void
    {
        Notification::fake();

        $this->signUp(Plan::factory()->create(['is_active' => true]))->assertHasNoErrors();
        $this->assertAuthenticated();
    }

    private function actingAsAdmin(string $role = AdminUser::ROLE_SUPER_ADMIN, string $email = 'root@mybooks.test'): AdminUser
    {
        $admin = AdminUser::create(['name' => 'Platform Admin', 'email' => $email, 'password' => $this->secret, 'role' => $role, 'is_active' => true]);
        $admin->forceFill(['two_factor_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($admin, 'admin')->withSession([
            'admin_two_factor_verified' => true,
            'admin_password_hash' => EnsureAdminTwoFactor::passwordFingerprint($admin),
        ]);

        return $admin;
    }

    public function test_logging_and_closing_a_data_request_go_in_the_admin_audit_log(): void
    {
        [$tenant] = $this->createTenantWithSubscription();
        $admin = $this->actingAsAdmin();

        $this->post(route('admin.data-requests.store'), [
            'type' => 'export', 'tenant_id' => $tenant->id, 'requester' => 'owner@business.test', 'details' => 'Wants a copy',
        ])->assertRedirect();
        $request = DataRequest::sole();
        $this->patch(route('admin.data-requests.complete', $request))->assertRedirect();

        $logs = ActivityLog::withoutGlobalScopes()->where('admin_user_id', $admin->id)->where('model_type', DataRequest::class)->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(ActivityLog::ACTION_CREATED, $logs[0]->action);
        $this->assertStringContainsString("logged a data request (Data export) from 'owner@business.test' for business '{$tenant->name}'", $logs[0]->description);
        $this->assertSame(ActivityLog::ACTION_UPDATED, $logs[1]->action);
        $this->assertStringContainsString('as done', $logs[1]->description);
        $this->assertSame(DataRequest::STATUS_COMPLETED, $logs[1]->new_values['status']);
    }

    public function test_another_admin_can_still_be_deactivated_while_a_super_admin_remains(): void
    {
        $this->actingAsAdmin();
        $other = AdminUser::create(['name' => 'Second', 'email' => 'second@mybooks.test', 'password' => $this->secret, 'role' => AdminUser::ROLE_SUPER_ADMIN, 'is_active' => true]);

        $this->patch(route('admin.users.toggle-status', $other))->assertSessionHas('success');
        $this->assertFalse($other->fresh()->is_active);
    }

    /**
     * Only a super admin reaches these routes and they can't change
     * themselves, so through the screens one always remains; the rule is
     * held here directly, as delete's already was. (update() checks the
     * same isLastActiveSuperAdmin() before a role change.)
     */
    public function test_the_last_active_super_admin_cannot_be_deactivated_or_deleted(): void
    {
        $viewer = AdminUser::create(['name' => 'Viewer', 'email' => 'viewer@mybooks.test', 'password' => $this->secret, 'role' => AdminUser::ROLE_VIEWER, 'is_active' => true]);
        $last = AdminUser::create(['name' => 'Root', 'email' => 'root@mybooks.test', 'password' => $this->secret, 'role' => AdminUser::ROLE_SUPER_ADMIN, 'is_active' => true]);
        $this->actingAs($viewer, 'admin');
        $controller = app(AdminUserController::class);

        $this->assertStringContainsString('last active admin who can manage admin users', $controller->toggleStatus($last)->getSession()->get('error'));
        $this->assertStringContainsString('last active super admin', $controller->destroy($last)->getSession()->get('error'));
        $this->assertTrue($last->fresh()->is_active);
        $this->assertNotNull(AdminUser::find($last->id));
    }
}
