<?php

namespace Tests\Unit\Models;

use App\Models\ChartOfAccount;
use Tests\TestCase;

class ChartOfAccountTest extends TestCase
{
    public function test_is_debit_balance_for_assets(): void
    {
        $account = ChartOfAccount::factory()->asset()->make();
        $this->assertTrue($account->isDebitBalance());
        $this->assertFalse($account->isCreditBalance());
    }

    public function test_is_debit_balance_for_expenses(): void
    {
        $account = ChartOfAccount::factory()->expense()->make();
        $this->assertTrue($account->isDebitBalance());
        $this->assertFalse($account->isCreditBalance());
    }

    public function test_is_credit_balance_for_liabilities(): void
    {
        $account = ChartOfAccount::factory()->liability()->make();
        $this->assertTrue($account->isCreditBalance());
        $this->assertFalse($account->isDebitBalance());
    }

    public function test_is_credit_balance_for_equity(): void
    {
        $account = ChartOfAccount::factory()->equity()->make();
        $this->assertTrue($account->isCreditBalance());
        $this->assertFalse($account->isDebitBalance());
    }

    public function test_is_credit_balance_for_income(): void
    {
        $account = ChartOfAccount::factory()->income()->make();
        $this->assertTrue($account->isCreditBalance());
        $this->assertFalse($account->isDebitBalance());
    }

    public function test_get_types_returns_all_types(): void
    {
        $types = ChartOfAccount::getTypes();
        $this->assertArrayHasKey('asset', $types);
        $this->assertArrayHasKey('liability', $types);
        $this->assertArrayHasKey('equity', $types);
        $this->assertArrayHasKey('income', $types);
        $this->assertArrayHasKey('expense', $types);
        $this->assertCount(5, $types);
    }

    public function test_parent_child_relationship(): void
    {
        $this->createAuthenticatedUser();

        $parent = ChartOfAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'account_code' => '18001',
            'name' => 'Parent Account',
            'type' => ChartOfAccount::TYPE_ASSET,
        ]);

        $child = ChartOfAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'parent_id' => $parent->id,
            'account_code' => '18002',
            'name' => 'Child Account',
            'type' => ChartOfAccount::TYPE_ASSET,
        ]);

        $this->assertEquals($parent->id, $child->parent->id);
        $this->assertTrue($parent->children->contains($child));
    }

    public function test_soft_deletes(): void
    {
        $this->createAuthenticatedUser();
        $account = ChartOfAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'account_code' => '99999',
        ]);
        $accountId = $account->id;
        $account->delete();
        $this->assertSoftDeleted('chart_of_accounts', ['id' => $accountId]);
    }

    public function test_is_system_cast(): void
    {
        $account = ChartOfAccount::factory()->system()->make();
        $this->assertIsBool($account->is_system);
        $this->assertTrue($account->is_system);
    }
}
