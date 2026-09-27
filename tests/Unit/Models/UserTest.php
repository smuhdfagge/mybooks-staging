<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\Tenant;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_user_belongs_to_tenant(): void
    {
        $this->createAuthenticatedUser();
        $this->assertInstanceOf(Tenant::class, $this->user->tenant);
        $this->assertEquals($this->tenant->id, $this->user->tenant->id);
    }

    public function test_is_super_admin_returns_true_for_super_admin(): void
    {
        $this->createSuperAdmin();
        $this->assertTrue($this->user->isSuperAdmin());
    }

    public function test_is_super_admin_returns_false_for_regular_user(): void
    {
        $this->createAuthenticatedUser();
        $this->assertFalse($this->user->isSuperAdmin());
    }

    public function test_belongs_to_tenant_method(): void
    {
        $this->createAuthenticatedUser();
        $this->assertTrue($this->user->belongsToTenant($this->tenant->id));
        $this->assertFalse($this->user->belongsToTenant(999));
    }

    public function test_scope_active(): void
    {
        $this->createAuthenticatedUser();
        $inactive = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $activeUsers = User::active()->where('tenant_id', $this->tenant->id)->get();
        $this->assertTrue($activeUsers->contains($this->user));
        $this->assertFalse($activeUsers->contains($inactive));
    }

    public function test_scope_for_tenant(): void
    {
        $this->createAuthenticatedUser();
        $otherTenant = Tenant::withoutEvents(fn () => Tenant::factory()->create());
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $tenantUsers = User::forTenant($this->tenant->id)->get();
        $this->assertTrue($tenantUsers->contains($this->user));
        $this->assertFalse($tenantUsers->contains($otherUser));
    }

    public function test_password_is_hashed(): void
    {
        $user = User::factory()->create(['tenant_id' => Tenant::factory()]);
        $this->assertNotEquals('password', $user->password);
        $this->assertTrue(\Hash::check('password', $user->password));
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $this->createAuthenticatedUser();
        $this->assertIsBool($this->user->is_active);
        $this->assertIsBool($this->user->is_super_admin);
    }
}
