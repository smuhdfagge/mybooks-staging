<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Tenant;
use App\Services\BankService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class BankServiceTest extends TestCase
{
    private BankService $bankService;
    private Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
        $this->bankService = app(BankService::class);
        $this->bank = Bank::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_balance' => 10000.00,
        ]);
    }

    // ── credit() ────────────────────────────────────────────────

    public function test_credit_increases_bank_balance(): void
    {
        $this->bankService->credit($this->bank->id, 500.00, 'Test credit');

        $this->assertEquals(10500.00, $this->bank->fresh()->current_balance);
    }

    public function test_credit_logs_the_operation(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($msg, $ctx) => $msg === 'BankService::credit'
                && $ctx['bank_id'] === $this->bank->id
                && $ctx['amount'] === 250.00);

        $this->bankService->credit($this->bank->id, 250.00, 'logging test');
    }

    public function test_credit_skips_when_bank_id_is_null(): void
    {
        // Should not throw or log warning
        $this->bankService->credit(null, 100.00, 'null bank');

        $this->assertEquals(10000.00, $this->bank->fresh()->current_balance);
    }

    public function test_credit_skips_when_amount_is_zero(): void
    {
        $this->bankService->credit($this->bank->id, 0, 'zero amount');

        $this->assertEquals(10000.00, $this->bank->fresh()->current_balance);
    }

    public function test_credit_logs_warning_for_missing_bank(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'BankService::credit'));

        $this->bankService->credit(99999, 100.00, 'nonexistent');
    }

    // ── debit() ─────────────────────────────────────────────────

    public function test_debit_decreases_bank_balance(): void
    {
        $this->bankService->debit($this->bank->id, 300.00, 'Test debit');

        $this->assertEquals(9700.00, $this->bank->fresh()->current_balance);
    }

    public function test_debit_skips_when_bank_id_is_null(): void
    {
        $this->bankService->debit(null, 100.00, 'null bank');

        $this->assertEquals(10000.00, $this->bank->fresh()->current_balance);
    }

    public function test_debit_logs_warning_for_missing_bank(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains($msg, 'BankService::debit'));

        $this->bankService->debit(99999, 100.00, 'nonexistent');
    }

    // ── adjustOnUpdate() ────────────────────────────────────────

    public function test_adjust_on_update_same_bank_incoming_amount_change(): void
    {
        // Incoming payment increased from 500 to 800
        // Old effect: +500 → reverse: -500; New effect: +800
        $this->bankService->adjustOnUpdate(
            $this->bank->id, 500.00,
            $this->bank->id, 800.00,
            'incoming', 'update test'
        );

        // 10000 - 500 + 800 = 10300
        $this->assertEquals(10300.00, $this->bank->fresh()->current_balance);
    }

    public function test_adjust_on_update_same_bank_outgoing_amount_change(): void
    {
        // Outgoing payment increased from 200 to 600
        // Old effect: -200 → reverse: +200; New effect: -600
        $this->bankService->adjustOnUpdate(
            $this->bank->id, 200.00,
            $this->bank->id, 600.00,
            'outgoing', 'update test'
        );

        // 10000 + 200 - 600 = 9600
        $this->assertEquals(9600.00, $this->bank->fresh()->current_balance);
    }

    public function test_adjust_on_update_bank_changed_incoming(): void
    {
        $newBank = Bank::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_balance' => 5000.00,
        ]);

        // Incoming payment of 300 moved from oldBank to newBank, amount unchanged
        $this->bankService->adjustOnUpdate(
            $this->bank->id, 300.00,
            $newBank->id, 300.00,
            'incoming', 'bank change'
        );

        // Old bank: 10000 - 300 = 9700
        $this->assertEquals(9700.00, $this->bank->fresh()->current_balance);
        // New bank: 5000 + 300 = 5300
        $this->assertEquals(5300.00, $newBank->fresh()->current_balance);
    }

    public function test_adjust_on_update_bank_changed_outgoing(): void
    {
        $newBank = Bank::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_balance' => 5000.00,
        ]);

        // Outgoing payment of 400 moved from oldBank to newBank
        $this->bankService->adjustOnUpdate(
            $this->bank->id, 400.00,
            $newBank->id, 400.00,
            'outgoing', 'bank change'
        );

        // Old bank: 10000 + 400 = 10400 (reverse debit)
        $this->assertEquals(10400.00, $this->bank->fresh()->current_balance);
        // New bank: 5000 - 400 = 4600 (apply debit)
        $this->assertEquals(4600.00, $newBank->fresh()->current_balance);
    }

    public function test_adjust_on_update_no_change_is_noop(): void
    {
        // Same bank, same amount → no adjustment
        $this->bankService->adjustOnUpdate(
            $this->bank->id, 500.00,
            $this->bank->id, 500.00,
            'incoming', 'no change'
        );

        $this->assertEquals(10000.00, $this->bank->fresh()->current_balance);
    }

    public function test_adjust_on_update_old_bank_null_new_bank_set(): void
    {
        // Previously no bank, now assigned
        $this->bankService->adjustOnUpdate(
            null, 0,
            $this->bank->id, 1000.00,
            'incoming', 'new bank assigned'
        );

        // 10000 + 1000 = 11000
        $this->assertEquals(11000.00, $this->bank->fresh()->current_balance);
    }

    public function test_adjust_on_update_old_bank_set_new_bank_null(): void
    {
        // Previously had bank, now removed (incoming)
        $this->bankService->adjustOnUpdate(
            $this->bank->id, 700.00,
            null, 0,
            'incoming', 'bank removed'
        );

        // 10000 - 700 = 9300 (reverse old credit)
        $this->assertEquals(9300.00, $this->bank->fresh()->current_balance);
    }
}
