<?php

namespace Tests\Unit\Models;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Vendor;
use Tests\TestCase;

class ScopeActiveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
    }

    // ── Customer ───────────────────────────────────────────────

    public function test_customer_scope_active_returns_only_active(): void
    {
        Customer::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        Customer::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);

        $this->assertCount(3, Customer::active()->get());
    }

    public function test_customer_scope_active_returns_empty_when_none_active(): void
    {
        Customer::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);

        $this->assertCount(0, Customer::active()->get());
    }

    // ── Vendor ─────────────────────────────────────────────────

    public function test_vendor_scope_active_returns_only_active(): void
    {
        Vendor::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        Vendor::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);

        $this->assertCount(2, Vendor::active()->get());
    }

    // ── ChartOfAccount ─────────────────────────────────────────

    public function test_chart_of_account_scope_active_returns_only_active(): void
    {
        // Tenant creation seeds default accounts (all active), so count them first
        $defaultActiveCount = ChartOfAccount::active()->count();

        ChartOfAccount::factory()->count(4)->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        ChartOfAccount::factory()->count(1)->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);

        $this->assertCount($defaultActiveCount + 4, ChartOfAccount::active()->get());
    }

    // ── Item ───────────────────────────────────────────────────

    public function test_item_scope_active_returns_only_active(): void
    {
        Item::factory()->count(5)->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        Item::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);

        $this->assertCount(5, Item::active()->get());
    }

    // ── Combined (chaining with other scopes) ──────────────────

    public function test_scope_active_is_chainable_with_where(): void
    {
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true, 'name' => 'Alice']);
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true, 'name' => 'Bob']);
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false, 'name' => 'Alice']);

        $result = Customer::active()->where('name', 'Alice')->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Alice', $result->first()->name);
    }
}
