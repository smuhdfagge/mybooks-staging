<?php

namespace App\Actions\CreditNotes;

use App\Models\CreditNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deleting a credit note. Only a draft (it never posted anything) can be
 * deleted; an open one is voided instead, so its journal and any returned
 * stock are reversed and the record stays.
 */
class DeleteCreditNote
{
    public function handle(CreditNote $note): void
    {
        if ($reason = $this->blockedBecause($note)) {
            throw ValidationException::withMessages(['credit_note' => $reason]);
        }

        DB::transaction(function () use ($note) {
            $note->items()->delete();
            $note->delete();
        });
    }

    public function blockedBecause(CreditNote $note): ?string
    {
        if (! $note->isDraft()) {
            return "Credit note {$note->credit_note_number} is {$note->status}. Only a draft can be deleted; void an unused credit note instead.";
        }

        return null;
    }
}
