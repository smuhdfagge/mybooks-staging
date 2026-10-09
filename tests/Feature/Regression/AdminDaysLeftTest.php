<?php

namespace Tests\Feature\Regression;

use App\Models\AdminUser;
use Tests\TestCase;

/**
 * Found during rebrand R9: the admin tenant page showed "Days Left" as a
 * negative decimal ("-30.846034457095 days") because it counted from the end
 * date back to today. It now counts forward from today in whole days.
 */
class AdminDaysLeftTest extends TestCase
{
    public function test_tenant_page_shows_whole_days_left_counting_forward(): void
    {
        [$tenant, , $subscription] = $this->createTenantWithSubscription();
        $subscription->update(['ends_at' => now()->addDays(20)->addHours(5)]);
        $admin = AdminUser::create(['name' => 'Ops', 'email' => 'ops@example.com', 'password' => 'Secret-123!', 'is_active' => true, 'role' => 'admin']);

        $html = $this->actingAsPlatformAdmin($admin)->get(route('admin.tenants.show', $tenant))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/>\s*20 days\s*</', $html);
        $this->assertDoesNotMatchRegularExpression('/-\d+(\.\d+)? days/', $html);
    }

    public function test_dashboard_expiring_soon_shows_whole_days(): void
    {
        [, , $subscription] = $this->createTenantWithSubscription();
        $subscription->update(['ends_at' => now()->addDays(3)->addHours(2)]);
        $admin = AdminUser::create(['name' => 'Ops', 'email' => 'ops@example.com', 'password' => 'Secret-123!', 'is_active' => true, 'role' => 'admin']);

        $html = $this->actingAsPlatformAdmin($admin)->get(route('admin.tenants.index'))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/>\s*3 days\s*</', $html);
        $this->assertDoesNotMatchRegularExpression('/-\d+(\.\d+)? days/', $html);
    }
}
