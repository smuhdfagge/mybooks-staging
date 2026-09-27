<?php

namespace Tests\Unit\Models;

use App\Models\Bank;
use Tests\TestCase;

class BankTest extends TestCase
{
    public function test_masked_account_number_hides_digits(): void
    {
        $bank = Bank::factory()->make(['account_number' => '1234567890']);
        $this->assertEquals('••••••7890', $bank->masked_account_number);
    }

    public function test_masked_account_number_short_number(): void
    {
        $bank = Bank::factory()->make(['account_number' => '1234']);
        $this->assertEquals('1234', $bank->masked_account_number);
    }

    public function test_masked_account_number_empty(): void
    {
        $bank = Bank::factory()->make(['account_number' => '']);
        $this->assertEquals('', $bank->masked_account_number);
    }

    public function test_formatted_balance(): void
    {
        $bank = Bank::factory()->make(['current_balance' => 12345.67]);
        $this->assertEquals('12,345.67', $bank->formatted_balance);
    }

    public function test_get_account_types(): void
    {
        $types = Bank::getAccountTypes();
        $this->assertArrayHasKey(Bank::TYPE_CHECKING, $types);
        $this->assertArrayHasKey(Bank::TYPE_SAVINGS, $types);
        $this->assertArrayHasKey(Bank::TYPE_CREDIT_CARD, $types);
        $this->assertArrayHasKey(Bank::TYPE_CASH, $types);
        $this->assertArrayHasKey(Bank::TYPE_OTHER, $types);
    }

    public function test_scope_active(): void
    {
        $this->createAuthenticatedUser();

        Bank::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        Bank::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);

        $activeBanks = Bank::active()->get();
        $this->assertTrue($activeBanks->every(fn ($b) => $b->is_active));
    }

    public function test_scope_primary(): void
    {
        $this->createAuthenticatedUser();

        Bank::factory()->primary()->create(['tenant_id' => $this->tenant->id]);
        Bank::factory()->create(['tenant_id' => $this->tenant->id, 'is_primary' => false]);

        $primaryBanks = Bank::primary()->get();
        $this->assertTrue($primaryBanks->every(fn ($b) => $b->is_primary));
    }

    public function test_soft_deletes(): void
    {
        $this->createAuthenticatedUser();
        $bank = Bank::factory()->create(['tenant_id' => $this->tenant->id]);
        $bankId = $bank->id;
        $bank->delete();
        $this->assertSoftDeleted('banks', ['id' => $bankId]);
    }

    public function test_boolean_casts(): void
    {
        $bank = Bank::factory()->make();
        $this->assertIsBool($bank->is_primary);
        $this->assertIsBool($bank->is_active);
    }
}
