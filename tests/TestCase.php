<?php

namespace Tests;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Permission;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected Plan $plan;

    protected Subscription $subscription;

    /**
     * Create a full tenant context: tenant + plan + active subscription.
     * Returns [tenant, plan, subscription].
     */
    protected function createTenantWithSubscription(array $tenantAttrs = []): array
    {
        $tenant = Tenant::factory()->create($tenantAttrs);
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        return [$tenant, $plan, $subscription];
    }

    /**
     * Create an authenticated user with tenant + subscription context.
     * Optionally assign permissions.
     */
    protected function createAuthenticatedUser(array $permissions = [], array $userAttrs = []): User
    {
        [$tenant, $plan, $subscription] = $this->createTenantWithSubscription();

        $this->tenant = $tenant;
        $this->plan = $plan;
        $this->subscription = $subscription;

        $user = User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
        ], $userAttrs));

        if (! empty($permissions)) {
            foreach ($permissions as $perm) {
                Permission::findOrCreate($perm, 'web');
            }
            $user->givePermissionTo($permissions);
        }

        $this->user = $user;
        $this->actingAs($user);

        return $user;
    }

    /**
     * Create a user for a specific tenant (no actingAs).
     */
    protected function createUserForTenant(Tenant $tenant, array $permissions = [], array $userAttrs = []): User
    {
        $user = User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
        ], $userAttrs));

        if (! empty($permissions)) {
            foreach ($permissions as $perm) {
                Permission::findOrCreate($perm, 'web');
            }
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    /**
     * Create a super admin user and act as them.
     */
    protected function createSuperAdmin(): User
    {
        [$tenant, $plan, $subscription] = $this->createTenantWithSubscription();

        $this->tenant = $tenant;
        $this->plan = $plan;
        $this->subscription = $subscription;

        $user = User::factory()->superAdmin()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->user = $user;
        $this->actingAs($user);

        return $user;
    }

    /**
     * Sign in to the admin panel as an admin who has finished two-factor
     * sign-in (S2 requires 2FA for every platform admin).
     */
    protected function actingAsPlatformAdmin(\App\Models\AdminUser $admin): static
    {
        $admin->forceFill([
            'two_factor_secret' => \Illuminate\Support\Facades\Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $this->actingAs($admin, 'admin')->withSession([
            'admin_two_factor_verified' => true,
            'admin_password_hash' => \App\Http\Middleware\EnsureAdminTwoFactor::passwordFingerprint($admin),
        ]);
    }
}
