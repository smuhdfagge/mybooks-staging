<?php

namespace App\Exceptions;

use App\Models\Journal;
use RuntimeException;

/**
 * Thrown when a journal's debits and credits differ, so it is never posted
 * to the account balances (finding M2).
 */
class UnbalancedJournalException extends RuntimeException
{
    public static function for(Journal $journal): self
    {
        $debit = round((float) $journal->entries()->sum('debit'), 2);
        $credit = round((float) $journal->entries()->sum('credit'), 2);

        return new self(sprintf(
            'Journal %s is not balanced: debits %s, credits %s (%s: %s).',
            $journal->journal_number,
            number_format($debit, 2),
            number_format($credit, 2),
            class_basename((string) $journal->reference_type) ?: 'manual',
            $journal->reference ?? $journal->reference_id ?? '-'
        ));
    }
}
