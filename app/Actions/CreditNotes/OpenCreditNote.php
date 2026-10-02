<?php

namespace App\Actions\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Services\JournalService;
use App\Services\StockValuationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opening (posting) a customer credit note.
 *
 * Journal (JournalService::createCreditNoteJournal):
 *   Dr Sales revenue, Dr Output VAT, Cr Accounts receivable
 * and, when the goods came back (restock):
 *   Dr Inventory, Cr Cost of goods sold, at cost.
 *
 * Returned goods go back on hand at cost (finding A15: they used to stay
 * out of stock while the sale was reversed). The cost is what the goods
 * were sold at on the credited invoice's line (its stored unit cost), or
 * for a credit note without an invoice, the item's current average cost.
 * Each line keeps its cost so voiding takes back exactly the same.
 *
 * Goods on an invoice that hasn't been released are still in the store
 * (only reserved), so they can't be "returned": edit or cancel that
 * invoice instead.
 */
class OpenCreditNote
{
    public function __construct(
        private JournalService $journals,
        private StockValuationService $valuation,
    ) {}

    public function handle(CreditNote $note): void
    {
        if ($note->status !== CreditNoteStatus::Draft->value) {
            throw ValidationException::withMessages(['credit_note' => 'Only a draft credit note can be opened.']);
        }

        DB::transaction(function () use ($note) {
            if ($note->restock) {
                $this->returnGoods($note);
            }

            $note->update([
                'status' => CreditNoteStatus::Open->value,
                'balance' => $note->total,
            ]);

            $this->journals->createCreditNoteJournal($note->fresh());
        });
    }

    protected function returnGoods(CreditNote $note): void
    {
        $invoice = $note->invoice_id ? Invoice::with('items')->find($note->invoice_id) : null;
        $lines = $note->items()->with('item')->get()->filter(fn (CreditNoteItem $l) => $this->tracksStock($l->item));

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['restock' => 'None of the lines is a stock item, so no goods can go back to stock. Untick "goods returned".']);
        }

        if ($invoice) {
            if (! $invoice->isReleased()) {
                throw ValidationException::withMessages(['restock' => "The goods on invoice {$invoice->invoice_number} haven't left stock yet (the invoice hasn't been released). Edit or cancel the invoice instead of returning the goods."]);
            }
            $this->assertNotMoreThanSold($note, $invoice, $lines);
        }

        foreach ($lines as $line) {
            $quantity = (float) $line->quantity;
            $unitCost = $this->costOf($line, $invoice);
            $line->forceFill(['unit_cost' => $unitCost])->saveQuietly();

            $inventory = Inventory::where('tenant_id', $note->tenant_id)->where('item_id', $line->item_id)
                ->whereNull('warehouse_id')->lockForUpdate()->first()
                ?? Inventory::create(['tenant_id' => $note->tenant_id, 'item_id' => $line->item_id, 'quantity' => 0, 'reserved_quantity' => 0]);
            $inventory->quantity = round((float) $inventory->quantity + $quantity, 4);
            $inventory->save();

            $this->valuation->addLayer($note->tenant_id, (int) $line->item_id, $quantity, $unitCost, null, CreditNote::class, $note->id);

            InventoryHistory::create([
                'tenant_id' => $note->tenant_id,
                'item_id' => $line->item_id,
                'type' => 'in',
                'quantity' => $quantity,
                'reference_type' => 'credit_note',
                'reference_id' => $note->id,
                'notes' => "Returned by customer (credit note {$note->credit_note_number})",
                'created_by' => auth()->id(),
            ]);
        }
    }

    /** The quantity returned of each item can't be more than the invoice sold, less earlier returns. */
    protected function assertNotMoreThanSold(CreditNote $note, Invoice $invoice, $lines): void
    {
        $sold = $invoice->items->groupBy('item_id')->map(fn ($g) => (float) $g->sum('quantity'));
        $returnedBefore = CreditNoteItem::query()
            ->whereHas('creditNote', fn ($q) => $q->where('invoice_id', $invoice->id)->where('restock', true)
                ->whereIn('status', [CreditNoteStatus::Open->value, CreditNoteStatus::Closed->value])->whereKeyNot($note->id))
            ->whereNotNull('unit_cost')
            ->selectRaw('item_id, SUM(quantity) as qty')->groupBy('item_id')->pluck('qty', 'item_id');

        foreach ($lines->groupBy('item_id') as $itemId => $group) {
            $left = ($sold[$itemId] ?? 0) - (float) ($returnedBefore[$itemId] ?? 0);
            $asked = (float) $group->sum('quantity');
            if ($asked - $left > 0.0001) {
                $name = $group->first()->description;
                throw ValidationException::withMessages(['restock' => "Only {$left} of \"{$name}\" can be returned against invoice {$invoice->invoice_number}."]);
            }
        }
    }

    protected function costOf(CreditNoteItem $line, ?Invoice $invoice): float
    {
        $sold = $invoice?->items->first(fn (InvoiceItem $i) => (int) $i->item_id === (int) $line->item_id && $i->unit_cost !== null);
        if ($sold) {
            return round((float) $sold->unit_cost, 4);
        }

        return round($this->valuation->getWeightedAverageCost($line->item), 4);
    }

    protected function tracksStock(?Item $item): bool
    {
        return $item && $item->track_inventory && $item->type !== 'service';
    }
}
