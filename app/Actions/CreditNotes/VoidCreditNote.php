<?php

namespace App\Actions\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\InventoryLayer;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Voiding a credit note that hasn't been used (nothing applied or
 * refunded). Its journal is reversed (the original stays) and goods it put
 * back into stock are taken out again, at the same cost. If some of those
 * goods have been sold since, it can't be voided.
 */
class VoidCreditNote
{
    public function __construct(private JournalService $journals) {}

    public function handle(CreditNote $note): void
    {
        if ($reason = $this->blockedBecause($note)) {
            throw ValidationException::withMessages(['credit_note' => $reason]);
        }

        DB::transaction(function () use ($note) {
            if ($note->status === CreditNoteStatus::Open->value) {
                if ($note->restock) {
                    $this->takeGoodsBack($note);
                }
                $this->journals->reverseDocumentJournal(CreditNote::class, $note->id, 'Credit note voided');
            }

            $note->update(['status' => CreditNoteStatus::Void->value, 'balance' => 0]);
        });
    }

    public function blockedBecause(CreditNote $note): ?string
    {
        if (! in_array($note->status, [CreditNoteStatus::Draft->value, CreditNoteStatus::Open->value], true)) {
            return "A {$note->status} credit note can't be voided.";
        }
        if ($note->applications()->exists() || $note->refunds()->exists()) {
            return 'This credit note has been applied to an invoice or refunded, so it can\'t be voided.';
        }

        return null;
    }

    protected function takeGoodsBack(CreditNote $note): void
    {
        foreach ($note->items()->whereNotNull('unit_cost')->get() as $line) {
            $quantity = (float) $line->quantity;
            $layer = InventoryLayer::where('reference_type', CreditNote::class)->where('reference_id', $note->id)
                ->where('item_id', $line->item_id)->where('remaining_quantity', '>=', $quantity - 0.0001)
                ->lockForUpdate()->first();
            $inventory = Inventory::where('tenant_id', $note->tenant_id)->where('item_id', $line->item_id)
                ->whereNull('warehouse_id')->lockForUpdate()->first();

            if (! $layer || ! $inventory || (float) $inventory->quantity - (float) $inventory->reserved_quantity < $quantity - 0.0001) {
                throw ValidationException::withMessages(['credit_note' => "Some of the returned \"{$line->description}\" has been sold or used since, so this credit note can't be voided."]);
            }

            $layer->remaining_quantity = round((float) $layer->remaining_quantity - $quantity, 4);
            $layer->save();
            $inventory->quantity = round((float) $inventory->quantity - $quantity, 4);
            $inventory->save();

            InventoryHistory::create([
                'tenant_id' => $note->tenant_id,
                'item_id' => $line->item_id,
                'type' => 'out',
                'quantity' => -$quantity,
                'reference_type' => 'credit_note',
                'reference_id' => $note->id,
                'notes' => "Credit note {$note->credit_note_number} voided: returned goods taken back out",
                'created_by' => auth()->id(),
            ]);
        }
    }
}
