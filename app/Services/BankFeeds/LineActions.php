<?php

namespace App\Services\BankFeeds;

use App\Enums\BankFeedLineStatus;
use App\Models\Bank;
use App\Models\BankFeedLine;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What a person can do with a bank line (session 17): accept a suggested
 * match, say "not this one", ignore it, undo a match.
 *
 * Accepting posts nothing. It links the line to the record and marks the
 * record cleared the way bank reconciliation does: a bank_transactions row
 * with is_reconciled = true, dated as the bank line, which the
 * reconciliation screen counts. (Nothing else creates bank_transactions
 * rows, so on a feed account this is what fills that screen.)
 */
class LineActions
{
    public function __construct(private Matcher $matcher) {}

    /** @throws BankFeedException */
    public function accept(BankFeedLine $line, string $recordType, int $recordId, User $user): void
    {
        $this->assertOwn($line, $user);

        DB::transaction(function () use ($line, $recordType, $recordId, $user) {
            $fresh = BankFeedLine::withoutGlobalScopes()->lockForUpdate()->findOrFail($line->id);
            if (! $fresh->isNew()) {
                throw new BankFeedException('This bank line has already been dealt with.');
            }
            $candidate = $this->matcher->candidateFor($fresh, $recordType, $recordId)
                ?? throw new BankFeedException('That record no longer fits this bank line.');

            $this->link($fresh, $candidate->record, BankFeedLineStatus::Matched, $user);
        });
    }

    /**
     * Accepts every line whose best suggestion is High. Returns how many.
     *
     * @param  iterable<BankFeedLine>  $lines
     */
    public function acceptSuggested(iterable $lines, User $user): int
    {
        $lines = collect($lines)->filter(fn (BankFeedLine $l) => (int) $l->tenant_id === (int) $user->tenant_id);
        $count = 0;

        foreach ($this->matcher->suggest($lines) as $lineId => $suggestion) {
            if (! $suggestion->isHigh()) {
                continue;
            }
            try {
                $this->accept($lines->firstWhere('id', $lineId), $suggestion->candidate->record::class, (int) $suggestion->candidate->record->getKey(), $user);
                $count++;
            } catch (BankFeedException) {
                // taken in the meantime; the rest carry on
            }
        }

        return $count;
    }

    /** "Not this one": the record is not offered for this line again. */
    public function reject(BankFeedLine $line, string $recordType, int $recordId, User $user): void
    {
        $this->assertOwn($line, $user);
        if (! $line->isNew()) {
            return;
        }
        $keys = $line->rejectedKeys();
        $keys[] = $recordType.':'.$recordId;
        $line->forceFill(['rejected_matches' => array_values(array_unique($keys))])->save();
    }

    public function ignore(BankFeedLine $line, User $user): void
    {
        $this->assertOwn($line, $user);
        if ($line->isNew()) {
            $line->forceFill(['status' => BankFeedLineStatus::Ignored->value, 'matched_by' => $user->id, 'matched_at' => now()])->save();
        }
    }

    public function unignore(BankFeedLine $line, User $user): void
    {
        $this->assertOwn($line, $user);
        if ($line->status === BankFeedLineStatus::Ignored->value) {
            $line->forceFill(['status' => BankFeedLineStatus::New->value, 'matched_by' => null, 'matched_at' => null])->save();
        }
    }

    /**
     * Takes a match back. A record that was created from the line is a real
     * document and has to be changed or deleted on its own page.
     *
     * @throws BankFeedException
     */
    public function undo(BankFeedLine $line, User $user): void
    {
        $this->assertOwn($line, $user);
        if ($line->status !== BankFeedLineStatus::Matched->value) {
            throw new BankFeedException('Only a match can be undone. To undo a record made from this line, change or delete the record itself.');
        }
        $this->release($line);
    }

    /** Links a line to a record (matched, or created from the line) and marks it cleared. */
    public function link(BankFeedLine $line, Model $record, BankFeedLineStatus $status, User $user): void
    {
        try {
            $line->forceFill([
                'status' => $status->value,
                'matched_type' => $record::class,
                'matched_id' => $record->getKey(),
                'matched_by' => $user->id,
                'matched_at' => now(),
            ])->save();
        } catch (UniqueConstraintViolationException) {
            throw new BankFeedException('That record is already linked to another bank line.');
        }

        $this->markReconciled($line);
    }

    /**
     * Creates the cleared bank_transactions row, once the record is in the
     * books (an expense only after it is approved and paid).
     */
    public function markReconciled(BankFeedLine $line): void
    {
        if ($line->bank_transaction_id || ! $line->matched_type) {
            return;
        }
        $class = $line->matched_type;
        $record = is_subclass_of($class, Model::class) ? $class::withoutGlobalScopes()->find($line->matched_id) : null;
        if (! $record || ($record instanceof Expense && $record->status !== Expense::STATUS_PAID)) {
            return;
        }

        $bank = Bank::withoutGlobalScopes()->findOrFail($line->bank_id);
        $transfer = $record instanceof Journal && $record->journal_type === 'bank_transfer';
        $type = $line->isCredit()
            ? ($transfer ? BankTransaction::TYPE_TRANSFER_IN : BankTransaction::TYPE_DEPOSIT)
            : ($transfer ? BankTransaction::TYPE_TRANSFER_OUT : BankTransaction::TYPE_WITHDRAWAL);

        $row = new BankTransaction([
            'tenant_id' => $line->tenant_id,
            'bank_id' => $line->bank_id,
            'type' => $type,
            'date' => $line->date,
            'amount' => $line->amount,
            'balance_after' => $line->balance_after ?? $bank->current_balance ?? 0,
            'reference' => (string) ($record->getAttribute('payment_number') ?? $record->getAttribute('expense_number') ?? $record->getAttribute('journal_number') ?? $record->getAttribute('reference')),
            'description' => $line->narration,
            'category' => 'Bank feed',
            'is_reconciled' => true,
            'reconciled_date' => $line->date,
            'reconciled_by' => $line->matched_by,
            'transactionable_type' => $record::class,
            'transactionable_id' => $record->getKey(),
        ]);
        // Marking something cleared is not posting: a closed period must not block it.
        $row->withoutPeriodValidation()->save();

        $line->forceFill(['bank_transaction_id' => $row->id])->save();
    }

    /**
     * Frees the line: removes the cleared row and goes back to "to review".
     * Also called when the linked record is deleted.
     */
    public function release(BankFeedLine $line): void
    {
        DB::transaction(function () use ($line) {
            if ($line->bank_transaction_id) {
                BankTransaction::withoutGlobalScopes()->whereKey($line->bank_transaction_id)->forceDelete();
            }
            $line->forceFill([
                'status' => BankFeedLineStatus::New->value,
                'matched_type' => null,
                'matched_id' => null,
                'bank_transaction_id' => null,
                'matched_by' => null,
                'matched_at' => null,
            ])->save();
        });
    }

    /** A record linked to bank lines is being deleted: those lines are free again. */
    public function releaseFor(Model $record): void
    {
        BankFeedLine::withoutGlobalScopes()->where('matched_type', $record::class)->where('matched_id', $record->getKey())
            ->get()->each(fn (BankFeedLine $line) => $this->release($line));
    }

    /** Lines of this record, for the expense-paid listener. @return Collection<int, BankFeedLine> */
    public function linesFor(Model $record): Collection
    {
        return BankFeedLine::withoutGlobalScopes()->where('matched_type', $record::class)->where('matched_id', $record->getKey())->get();
    }

    private function assertOwn(BankFeedLine $line, User $user): void
    {
        if ((int) $line->tenant_id !== (int) $user->tenant_id) {
            throw new BankFeedException('That bank line was not found.');
        }
    }
}
