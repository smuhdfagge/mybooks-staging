<?php

namespace App\Actions\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deleting a credit note. Only drafts (never posted) and void ones
 * (already reversed) can be deleted; an open credit note is voided first,
 * so its journal and any returned stock are reversed properly.
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
        if (! in_array($note->status, [CreditNoteStatus::Draft->value, CreditNoteStatus::Void->value], true)) {
            return "Credit note {$note->credit_note_number} is {$note->status}. Void it instead (only an unused credit note can be voided).";
        }

        return null;
    }
}
