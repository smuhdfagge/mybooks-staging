<?php

namespace App\Services;

use App\Models\Bank;
use Illuminate\Support\Facades\Log;

/**
 * Centralized service for bank balance operations.
 *
 * Every adjustment to Bank::current_balance should flow through this
 * service so that balance changes are logged consistently and the
 * increment/decrement semantics are easy to audit.
 */
class BankService
{
    /**
     * Credit (increase) a bank's current balance.
     *
     * Use when: receiving a payment, reversing an outgoing payment, etc.
     */
    public function credit(?int $bankId, float $amount, string $reason = ''): void
    {
        if (!$bankId || $amount <= 0) {
            return;
        }

        $bank = Bank::find($bankId);

        if (!$bank) {
            Log::warning("BankService::credit — Bank #{$bankId} not found. Reason: {$reason}");
            return;
        }

        $bank->increment('current_balance', $amount);

        Log::info("BankService::credit", [
            'bank_id' => $bankId,
            'amount' => $amount,
            'new_balance' => $bank->fresh()->current_balance,
            'reason' => $reason,
        ]);
    }

    /**
     * Debit (decrease) a bank's current balance.
     *
     * Use when: making a payment, recording an expense, etc.
     */
    public function debit(?int $bankId, float $amount, string $reason = ''): void
    {
        if (!$bankId || $amount <= 0) {
            return;
        }

        $bank = Bank::find($bankId);

        if (!$bank) {
            Log::warning("BankService::debit — Bank #{$bankId} not found. Reason: {$reason}");
            return;
        }

        $bank->decrement('current_balance', $amount);

        Log::info("BankService::debit", [
            'bank_id' => $bankId,
            'amount' => $amount,
            'new_balance' => $bank->fresh()->current_balance,
            'reason' => $reason,
        ]);
    }

    /**
     * Transfer balance adjustment when bank or amount changes on update.
     *
     * Reverses the old bank's balance and applies the new bank's balance.
     * No-ops when nothing actually changed.
     */
    public function adjustOnUpdate(
        ?int $oldBankId,
        float $oldAmount,
        ?int $newBankId,
        float $newAmount,
        string $direction = 'outgoing',
        string $reason = '',
    ): void {
        if ($oldBankId == $newBankId && $oldAmount == $newAmount) {
            return;
        }

        // Reverse old bank — outgoing payments were debits, so reverse = credit
        if ($direction === 'outgoing') {
            $this->credit($oldBankId, $oldAmount, "Reverse (update): {$reason}");
            $this->debit($newBankId, $newAmount, "Apply (update): {$reason}");
        } else {
            // Incoming payments were credits, so reverse = debit
            $this->debit($oldBankId, $oldAmount, "Reverse (update): {$reason}");
            $this->credit($newBankId, $newAmount, "Apply (update): {$reason}");
        }
    }
}
