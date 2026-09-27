<?php

namespace Tests\Feature\Regression;

use App\Livewire\Subscriptions\SubscriptionManager;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Regression tests for the Phase 2 (permissions) fixes.
 */
class Phase2RegressionTest extends TestCase
{
    // ── C1 (part): only admins manage the subscription ──────────

    public function test_c1_user_without_permission_cannot_change_cancel_or_reactivate_the_plan(): void
    {
        $this->createAuthenticatedUser();
        $other = Plan::factory()->create(['slug' => 'enterprise-test']);
        $originalPlan = $this->subscription->plan_id;

        Livewire::test(SubscriptionManager::class)
            ->set('selectedPlanId', $other->id)
            ->set('selectedBillingCycle', 'monthly')
            ->call('changePlan')
            ->assertForbidden();

        Livewire::test(SubscriptionManager::class)->call('cancelSubscription')->assertForbidden();
        Livewire::test(SubscriptionManager::class)->call('reactivateSubscription')->assertForbidden();

        $this->assertSame($originalPlan, $this->tenant->fresh()->activeSubscription->plan_id);
        $this->assertNull($this->subscription->fresh()->cancelled_at);
    }

    public function test_c1_user_with_permission_can_change_the_plan(): void
    {
        $this->createAuthenticatedUser(['manage subscription']);
        $other = Plan::factory()->create(['slug' => 'enterprise-test']);

        Livewire::test(SubscriptionManager::class)
            ->set('selectedPlanId', $other->id)
            ->set('selectedBillingCycle', 'monthly')
            ->call('changePlan')
            ->assertStatus(200);

        $this->assertSame($other->id, $this->tenant->fresh()->activeSubscription->plan_id);
    }

    public function test_c1_page_hides_plan_buttons_from_non_admins(): void
    {
        $this->createAuthenticatedUser();

        Livewire::test(SubscriptionManager::class)
            ->assertDontSeeHtml('wire:click="openUpgradeModal')
            ->assertDontSeeHtml('wire:click="openCancelModal"')
            ->assertSee('Only an administrator can change or cancel the subscription.');

        $this->user->givePermissionTo(Permission::findOrCreate('manage subscription', 'web'));

        Livewire::test(SubscriptionManager::class)
            ->assertSeeHtml('wire:click="openUpgradeModal')
            ->assertSeeHtml('wire:click="openCancelModal"');
    }

    public function test_c1_manage_subscription_permission_is_seeded_for_admins_only(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('manage subscription'));
        $this->assertTrue(Role::findByName('super-admin', 'web')->hasPermissionTo('manage subscription'));
        foreach (['accountant', 'sales', 'hr-manager', 'viewer'] as $role) {
            $this->assertFalse(Role::findByName($role, 'web')->hasPermissionTo('manage subscription'), $role);
        }
    }

    public function test_c1_migration_grants_manage_subscription_to_existing_admin_roles(): void
    {
        Permission::where('name', 'manage subscription')->delete();
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        (require database_path('migrations/2026_09_28_000003_add_manage_subscription_permission.php'))->up();

        $this->assertTrue($admin->fresh()->hasPermissionTo('manage subscription'));
        $this->assertFalse($viewer->fresh()->hasPermissionTo('manage subscription'));
    }
}
