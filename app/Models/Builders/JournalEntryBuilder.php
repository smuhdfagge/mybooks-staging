<?php

namespace App\Models\Builders;

use App\Exceptions\BusinessRuleException;
use App\Models\Journal;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Builder;

/**
 * Journal lines, refusing to change or remove the lines of a posted
 * journal (audit MB-15). A posted journal is put right by reversing it and
 * posting a new one; a query like $journal->entries()->delete() went
 * round the line's own model events, so editing an invoice rewrote its
 * posted journal with no trace of what it said before.
 *
 * Saving or deleting one line goes through here too, so this covers both.
 * Only repair commands that rebuild the ledger get round it, through
 * JournalEntry::rebuilding().
 *
 * @extends Builder<JournalEntry>
 */
class JournalEntryBuilder extends Builder
{
    public function update(array $values)
    {
        $this->refusePosted('changed');

        return parent::update($values);
    }

    public function delete()
    {
        $this->refusePosted('deleted');

        return parent::delete();
    }

    public function forceDelete()
    {
        $this->refusePosted('deleted');

        return parent::forceDelete();
    }

    private function refusePosted(string $what): void
    {
        if (JournalEntry::$rebuilding) {
            return;
        }

        $posted = $this->clone()
            ->whereIn('journal_id', Journal::withoutGlobalScopes()->where('is_posted', true)->select('id'))
            ->exists();
        if ($posted) {
            throw new BusinessRuleException("A posted journal's lines can't be {$what}. Reverse the journal and post a new one instead.");
        }
    }
}
