<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\BankTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BankReconciliationService
{
    /**
     * Get all unreconciled transactions for a bank, optionally filtered by date.
     */
    public function getUnreconciledTransactions(Bank $bank, ?string $fromDate = null, ?string $toDate = null): Collection
    {
        return BankTransaction::where('bank_id', $bank->id)
            ->where('is_reconciled', false)
            ->when($fromDate, fn ($q) => $q->where('date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('date', '<=', $toDate))
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Get all reconciled transactions for a bank within a date range.
     */
    public function getReconciledTransactions(Bank $bank, ?string $fromDate = null, ?string $toDate = null): Collection
    {
        return BankTransaction::where('bank_id', $bank->id)
            ->where('is_reconciled', true)
            ->when($fromDate, fn ($q) => $q->where('date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('date', '<=', $toDate))
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Mark selected transactions as reconciled and validate against statement balance.
     *
     * @param  array  $transactionIds  IDs of transactions to reconcile
     * @param  float  $statementBalance  The closing balance from the bank statement
     * @param  string  $statementDate  The date of the bank statement
     * @return array{success: bool, message: string, difference: float}
     */
    public function reconcile(Bank $bank, array $transactionIds, float $statementBalance, string $statementDate): array
    {
        $transactions = BankTransaction::where('bank_id', $bank->id)
            ->whereIn('id', $transactionIds)
            ->where('is_reconciled', false)
            ->get();

        if ($transactions->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No valid transactions selected for reconciliation.',
                'difference' => 0,
            ];
        }

        // Calculate the reconciled balance
        // Start with opening balance + all previously reconciled transactions
        $reconciledBalance = $this->getReconciledBalance($bank);

        // Add the selected transactions
        foreach ($transactions as $txn) {
            if ($txn->isInflow()) {
                $reconciledBalance += $txn->amount;
            } else {
                $reconciledBalance -= $txn->amount;
            }
        }

        $difference = round($statementBalance - $reconciledBalance, 2);

        if (abs($difference) > 0.01) {
            return [
                'success' => false,
                'message' => "Reconciliation difference of {$difference}. The selected transactions do not match the statement balance.",
                'difference' => $difference,
            ];
        }

        // All good — mark transactions as reconciled
        DB::transaction(function () use ($transactions, $statementDate) {
            $userId = auth()->id();

            BankTransaction::whereIn('id', $transactions->pluck('id'))
                ->update([
                    'is_reconciled' => true,
                    'reconciled_date' => $statementDate,
                    'reconciled_by' => $userId,
                ]);
        });

        return [
            'success' => true,
            'message' => "Successfully reconciled {$transactions->count()} transactions.",
            'difference' => 0,
        ];
    }

    /**
     * Force-reconcile transactions even when there is a difference.
     * Records the adjustment in the response for the caller to handle.
     */
    public function forceReconcile(Bank $bank, array $transactionIds, string $statementDate): int
    {
        return DB::transaction(function () use ($bank, $transactionIds, $statementDate) {
            $userId = auth()->id();

            return BankTransaction::where('bank_id', $bank->id)
                ->whereIn('id', $transactionIds)
                ->where('is_reconciled', false)
                ->update([
                    'is_reconciled' => true,
                    'reconciled_date' => $statementDate,
                    'reconciled_by' => $userId,
                ]);
        });
    }

    /**
     * Undo reconciliation for the given transactions.
     */
    public function unreconcile(Bank $bank, array $transactionIds): int
    {
        return BankTransaction::where('bank_id', $bank->id)
            ->whereIn('id', $transactionIds)
            ->where('is_reconciled', true)
            ->update([
                'is_reconciled' => false,
                'reconciled_date' => null,
                'reconciled_by' => null,
            ]);
    }

    /**
     * Get the reconciled balance: opening balance + all reconciled transactions.
     */
    public function getReconciledBalance(Bank $bank): float
    {
        $reconciledDeposits = BankTransaction::where('bank_id', $bank->id)
            ->where('is_reconciled', true)
            ->whereIn('type', [
                BankTransaction::TYPE_DEPOSIT,
                BankTransaction::TYPE_TRANSFER_IN,
                BankTransaction::TYPE_INTEREST,
            ])
            ->sum('amount');

        $reconciledWithdrawals = BankTransaction::where('bank_id', $bank->id)
            ->where('is_reconciled', true)
            ->whereIn('type', [
                BankTransaction::TYPE_WITHDRAWAL,
                BankTransaction::TYPE_TRANSFER_OUT,
                BankTransaction::TYPE_FEE,
            ])
            ->sum('amount');

        return (float) $bank->opening_balance + (float) $reconciledDeposits - (float) $reconciledWithdrawals;
    }

    /**
     * Get a summary of reconciliation status for a bank.
     */
    public function getSummary(Bank $bank): array
    {
        $unreconciledCount = BankTransaction::where('bank_id', $bank->id)
            ->where('is_reconciled', false)
            ->count();

        $reconciledCount = BankTransaction::where('bank_id', $bank->id)
            ->where('is_reconciled', true)
            ->count();

        $lastReconciled = BankTransaction::where('bank_id', $bank->id)
            ->where('is_reconciled', true)
            ->latest('reconciled_date')
            ->first();

        return [
            'reconciled_balance' => $this->getReconciledBalance($bank),
            'book_balance' => (float) $bank->current_balance,
            'unreconciled_count' => $unreconciledCount,
            'reconciled_count' => $reconciledCount,
            'last_reconciled_date' => $lastReconciled?->reconciled_date,
            'difference' => round((float) $bank->current_balance - $this->getReconciledBalance($bank), 2),
        ];
    }
}
