<?php

namespace Tests\Support;

use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalEntry;

/**
 * Helpers for checking that the stored account balances agree with the
 * journal, and that journals balance.
 */
trait AssertsLedger
{
    /**
     * Balance of an account computed from its journal lines, the same way
     * accounts:recalculate does it.
     */
    protected function ledgerBalance(ChartOfAccount $account): float
    {
        $debit = (float) JournalEntry::where('account_id', $account->id)
            ->whereHas('journal', fn ($q) => $q->whereNull('deleted_at'))
            ->sum('debit');
        $credit = (float) JournalEntry::where('account_id', $account->id)
            ->whereHas('journal', fn ($q) => $q->whereNull('deleted_at'))
            ->sum('credit');

        $movement = $account->isDebitBalance() ? $debit - $credit : $credit - $debit;

        return round((float) $account->opening_balance + $movement, 2);
    }

    /**
     * Every account of the tenant: stored current_balance == journal balance.
     */
    protected function assertStoredBalancesMatchLedger(int $tenantId): void
    {
        $mismatches = [];

        foreach (ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->get() as $account) {
            $stored = round((float) $account->current_balance, 2);
            $ledger = $this->ledgerBalance($account);
            if (abs($stored - $ledger) > 0.001) {
                $mismatches[] = "{$account->account_code} {$account->name}: stored {$stored}, ledger {$ledger}";
            }
        }

        $this->assertSame([], $mismatches, 'Stored account balances disagree with the journal.');
    }

    protected function accountBalance(int $tenantId, string $code): float
    {
        return round((float) ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('account_code', $code)->value('current_balance'), 2);
    }

    /**
     * Every journal of the tenant has equal debits and credits.
     */
    protected function assertAllJournalsBalance(int $tenantId): void
    {
        $unbalanced = [];

        foreach (Journal::withoutGlobalScopes()->where('tenant_id', $tenantId)->get() as $journal) {
            $debit = round((float) $journal->entries()->sum('debit'), 2);
            $credit = round((float) $journal->entries()->sum('credit'), 2);
            if (abs($debit - $credit) > 0.001) {
                $unbalanced[] = "{$journal->journal_number}: Dr {$debit} / Cr {$credit}";
            }
        }

        $this->assertSame([], $unbalanced, 'Unbalanced journals found.');
    }
}
