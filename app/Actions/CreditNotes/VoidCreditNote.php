<?php

namespace App\Actions\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\InventoryLayer;
use App\Services\JournalService;
use App\Services\StockValuationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Voiding a credit note that hasn't been used (nothing applied or
 * refunded). Its journal is kept and reversed, and goods it put back into
 * stock are taken out again at the same cost: from the stock layer the
 * credit note added first, then (if some of that has been sold since) from
 * the item's other stock. If there isn't enough free stock left, it can't
 * be voided. A draft is simply marked void.
 */
class VoidCreditNote
{
    public function __construct(
        private JournalService $journals,
        private StockValuationService $valuation,
    ) {}

    public function handle(CreditNote $note): CreditNote
    {
        if ($reason = $this->blockedBecause($note)) {
            throw ValidationException::withMessages(['credit_note' => $reason]);
        }

        return DB::transaction(function () use ($note) {
            $note = CreditNote::lockForUpdate()->findOrFail($note->id);

            if ($note->isOpen()) {
                if ($note->restock) {
                    $this->takeGoodsBack($note);
                }
                $this->journals->reverseDocumentJournal(CreditNote::class, $note->id, 'Credit note voided');
            }

            $note->forceFill(['status' => CreditNoteStatus::Void->value, 'balance' => 0])->withoutPeriodValidation()->save();

            return $note;
        });
    }

    public function blockedBecause(CreditNote $note): ?string
    {
        if (! in_array($note->status, [CreditNoteStatus::Draft->value, CreditNoteStatus::Open->value], true)) {
            return "Credit note {$note->credit_note_number} is {$note->status}, so it can't be voided.";
        }
        if ($note->applications()->exists() || $note->refunds()->exists()) {
            return "Credit note {$note->credit_note_number} has been applied to an invoice or refunded, so it can't be voided.";
        }

        return null;
    }

    protected function takeGoodsBack(CreditNote $note): void
    {
        $lines = $note->items()->with('item')->whereNotNull('unit_cost')->get();

        foreach ($lines->groupBy('item_id') as $itemId => $group) {
            $item = $group->first()->item;
            $quantity = (float) $group->sum('quantity');
            $cost = round($group->sum(fn ($l) => (float) $l->unit_cost * (float) $l->quantity), 2);

            $inventory = Inventory::where('tenant_id', $note->tenant_id)->where('item_id', $itemId)->lockForUpdate()->first();
            $free = $inventory ? (float) $inventory->quantity - (float) $inventory->reserved_quantity : 0.0;
            if (! $item || $free < $quantity - 0.0001) {
                $name = $group->first()->description;
                throw ValidationException::withMessages(['credit_note' => 'Only '.rtrim(rtrim(number_format(max(0, $free), 4, '.', ','), '0'), '.')." of the returned \"{$name}\" is free in stock (the rest has been sold or reserved since), so this credit note can't be voided."]);
            }

            // The credit note's own stock layers first.
            $remaining = $quantity;
            $layers = InventoryLayer::where('tenant_id', $note->tenant_id)->where('item_id', $itemId)
                ->where('reference_type', CreditNote::class)->where('reference_id', $note->id)
                ->where('remaining_quantity', '>', 0)->lockForUpdate()->get();
            foreach ($layers as $layer) {
                $take = min($remaining, (float) $layer->remaining_quantity);
                $layer->remaining_quantity = round((float) $layer->remaining_quantity - $take, 4);
                $layer->save();
                $remaining -= $take;
                if ($remaining <= 0.00001) {
                    break;
                }
            }
            // Any sold since: the rest comes out of the item's other stock layers.
            if ($remaining > 0.00001) {
                $this->valuation->issue($item, $remaining, CreditNote::class, $note->id);
            }

            // Average cost as it was before the goods came back.
            $qtyBefore = (float) $inventory->quantity;
            $left = $qtyBefore - $quantity;
            if ($left > 0.00001) {
                $inventory->unit_cost = round(max(0, ($qtyBefore * (float) $inventory->unit_cost - $cost) / $left), 4);
            }
            $inventory->quantity = round($left, 4);
            $inventory->save();

            InventoryHistory::create([
                'tenant_id' => $note->tenant_id,
                'item_id' => $itemId,
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
