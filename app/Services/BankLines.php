<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\BankFeedConnection;
use App\Models\BankTransaction;
use App\Models\Journal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The lines to tick on the reconcile screen for a bank without a live feed
 * (audit MB-08). Lines used to come only from bank feed matching, so a
 * business without a feed had nothing to tick and could never reconcile.
 *
 * Every posted entry on the bank's ledger account becomes one line, once
 * (unique on journal_entry_id): receipts, payments, payroll, transfers and
 * journals alike. Opening balance plus the ticked lines is the reconciled
 * balance; ticking them all reaches the book balance.
 *
 * Kept in step with the ledger each time the screen opens:
 * - a new entry adds a line;
 * - an unticked line whose entry is gone (journal deleted, unposted or
 *   re-posted) is removed; a ticked one moves to the re-posted entry of the
 *   same journal and amount, or stays (it was on a statement);
 * - an unticked line follows its entry's date and amount.
 */
class BankLines
{
    public const CATEGORY = 'Books';

    /** Lines come from the ledger: the bank has a ledger account and no live feed. */
    public function fromLedger(Bank $bank): bool
    {
        return (bool) $bank->chart_of_account_id
            && ! BankFeedConnection::withoutGlobalScopes()->counted()->where('bank_id', $bank->id)->exists();
    }

    /** Brings the bank's lines up to date with its ledger account. Returns the lines added. */
    public function sync(Bank $bank): int
    {
        if (! $this->fromLedger($bank)) {
            return 0;
        }

        return DB::transaction(function () use ($bank) {
            $entries = $this->entries($bank)->keyBy('id');
            $lines = BankTransaction::withoutGlobalScopes()
                ->where('bank_id', $bank->id)->where('category', self::CATEGORY)->whereNotNull('journal_entry_id')
                ->get();
            $new = $entries->except($lines->pluck('journal_entry_id')->all());

            foreach ($lines as $line) {
                $entry = $entries->get($line->journal_entry_id);
                if ($entry) {
                    if (! $line->is_reconciled) {
                        $this->fill($line, $entry);
                        if ($line->isDirty()) {
                            $line->withoutPeriodValidation()->save();
                        }
                    }

                    continue;
                }
                if (! $line->is_reconciled) {
                    $line->withoutPeriodValidation()->forceDelete();

                    continue;
                }
                // Ticked, and its journal was re-posted: follow it.
                $same = $new->first(fn ($e) => (int) $e->journal_id === (int) $line->journal_id && $this->signed($e) === $this->signedLine($line));
                if ($same) {
                    $line->withoutPeriodValidation()->forceFill(['journal_entry_id' => $same->id])->save();
                    $new->forget($same->id);
                }
            }

            // A bank that had a feed before keeps the lines the feed made.
            $fromFeed = BankTransaction::withoutGlobalScopes()
                ->where('bank_id', $bank->id)->whereNull('journal_entry_id')
                ->get(['transactionable_type', 'transactionable_id'])
                ->map(fn ($t) => $t->transactionable_type.'#'.$t->transactionable_id)->flip();

            $added = 0;
            foreach ($new as $entry) {
                if (isset($fromFeed[Journal::class.'#'.$entry->journal_id]) || ($entry->reference_type && isset($fromFeed[$entry->reference_type.'#'.$entry->reference_id]))) {
                    continue;
                }
                $line = new BankTransaction([
                    'tenant_id' => $bank->tenant_id,
                    'bank_id' => $bank->id,
                    'category' => self::CATEGORY,
                    'balance_after' => 0,
                    'is_reconciled' => false,
                    'journal_entry_id' => $entry->id,
                    'journal_id' => $entry->journal_id,
                    'transactionable_type' => Journal::class,
                    'transactionable_id' => $entry->journal_id,
                ]);
                $this->fill($line, $entry);
                try {
                    // Listing what is in the books is not posting: a closed period must not block it.
                    DB::transaction(fn () => $line->withoutPeriodValidation()->save());
                    $added++;
                } catch (UniqueConstraintViolationException) {
                    // Another request added it first.
                }
            }

            return $added;
        });
    }

    /** @return Collection<int, \stdClass> posted entries on the bank's ledger account */
    protected function entries(Bank $bank): Collection
    {
        return DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->where('j.tenant_id', $bank->tenant_id)
            ->where('je.account_id', $bank->chart_of_account_id)
            ->where('j.is_posted', true)
            ->whereNull('j.deleted_at')
            ->select('je.id', 'je.journal_id', 'je.debit', 'je.credit', 'je.description', 'j.journal_date', 'j.journal_number', 'j.reference',
                'j.description as journal_description', 'j.journal_type', 'j.reference_type', 'j.reference_id')
            ->get()
            ->filter(fn ($e) => abs($this->signed($e)) >= 0.005);
    }

    protected function fill(BankTransaction $line, object $entry): void
    {
        $signed = $this->signed($entry);
        $transfer = $entry->journal_type === 'bank_transfer';
        $line->fill([
            'type' => $signed > 0
                ? ($transfer ? BankTransaction::TYPE_TRANSFER_IN : BankTransaction::TYPE_DEPOSIT)
                : ($transfer ? BankTransaction::TYPE_TRANSFER_OUT : BankTransaction::TYPE_WITHDRAWAL),
            'date' => substr((string) $entry->journal_date, 0, 10),
            'amount' => abs($signed),
            'reference' => $entry->reference ?: $entry->journal_number,
            'description' => $entry->description ?: $entry->journal_description,
        ]);
    }

    /** Money in positive, money out negative. */
    protected function signed(object $entry): float
    {
        return round((float) $entry->debit - (float) $entry->credit, 2);
    }

    protected function signedLine(BankTransaction $line): float
    {
        return round($line->isInflow() ? (float) $line->amount : -(float) $line->amount, 2);
    }
}
