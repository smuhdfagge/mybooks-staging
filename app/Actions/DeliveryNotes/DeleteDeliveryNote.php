<?php

namespace App\Actions\DeliveryNotes;

use App\Enums\DeliveryNoteStatus;
use App\Models\DeliveryNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deleting a delivery note. Only drafts and cancelled notes can go: a
 * dispatched or delivered note is the record of goods that left, so it is
 * cancelled instead (which takes its quantities off the order).
 */
class DeleteDeliveryNote
{
    public function handle(DeliveryNote $note): void
    {
        if ($reason = $this->blockedBecause($note)) {
            throw ValidationException::withMessages(['delivery_note' => $reason]);
        }

        DB::transaction(function () use ($note) {
            $note->items()->delete();
            $note->delete();
        });
    }

    public function blockedBecause(DeliveryNote $note): ?string
    {
        if (! in_array($note->status, [DeliveryNoteStatus::Draft->value, DeliveryNoteStatus::Cancelled->value], true)) {
            return "Delivery note {$note->delivery_number} has been {$note->status}. Cancel it instead.";
        }

        return null;
    }
}
