<?php

namespace App\Actions\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\Invoice;
use App\Models\Warehouse;
use App\Services\JournalService;
use App\Services\StockValuationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opening (posting) a customer credit note, in one transaction.
 *
 * Journal (JournalService::createCreditNoteJournal):
 *   Dr Sales revenue, Dr Output VAT, Cr Accounts receivable
 * and, when the customer returned the goods (restock):
 *   Dr Inventory, Cr Cost of goods sold, at cost.
 *
 * Returned goods go back on hand at cost (finding A15: the sale was
 * reversed but the goods never came back into stock). The cost is what the
 * credited invoice took them out at (its lines' stored unit cost); for a
 * credit note without an invoice, the item's current average cost. Each
 * line keeps its cost, so voiding takes back exactly the same value.
 *
 * The goods go back into the credit note's warehouse if one was chosen,
 * else the warehouse the invoice took them from, else the default one
 * (session 12). The warehouse used is kept on the note for voiding.
 *
 * Goods on an invoice that hasn't been released are still in the store
 * (only reserved), so they can't be "returned": credit the amount only, or
 * edit the invoice.
 */
class OpenCreditNote
{
    public function __construct(
        private JournalService $journals,
        private StockValuationService $valuation,
    ) {}

    public function handle(CreditNote $note): CreditNote
    {
        if (! $note->isDraft()) {
            throw ValidationException::withMessages(['credit_note' => 'Only a draft credit note can be opened.']);
        }

        return DB::transaction(function () use ($note) {
            $note = CreditNote::lockForUpdate()->findOrFail($note->id);

            if ($note->restock) {
                $this->returnGoods($note);
            }

            $note->forceFill([
                'status' => CreditNoteStatus::Open->value,
                'balance' => $note->total,
            ])->save();

            $this->journals->createCreditNoteJournal($note);

            return $note;
        });
    }

    protected function returnGoods(CreditNote $note): void
    {
        $invoice = $note->invoice_id ? Invoice::with('items')->find($note->invoice_id) : null;
        $lines = $note->items()->with('item')->get()->filter(fn (CreditNoteItem $l) => $l->isStocked());

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['restock' => 'None of the lines is a stock item, so no goods can go back into stock. Untick "The customer returned the goods".']);
        }

        if ($invoice) {
            if (! $invoice->isReleased()) {
                throw ValidationException::withMessages(['restock' => "The goods on invoice {$invoice->invoice_number} haven't left your stock yet (the invoice hasn't been released), so they can't be returned. Untick \"The customer returned the goods\" to credit the amount only."]);
            }
            $this->assertNotMoreThanSold($note, $invoice, $lines);
        }

        $warehouseId = $note->warehouse_id
            ? Warehouse::resolveIdFor($note->tenant_id, $note->warehouse_id)
            : ($invoice?->warehouse_id ? (int) $invoice->warehouse_id : Warehouse::defaultIdFor($note->tenant_id));
        $note->forceFill(['warehouse_id' => $warehouseId])->saveQuietly();

        foreach ($lines as $line) {
            $quantity = (float) $line->quantity;
            $unitCost = $this->costOf($line, $invoice, $warehouseId);
            $line->forceFill(['unit_cost' => $unitCost])->saveQuietly();

            $inventory = $this->valuation->stockRow($note->tenant_id, (int) $line->item_id, $warehouseId);
            $this->valuation->updateWeightedAverageCost($inventory, $quantity, $unitCost);
            $inventory->quantity = round((float) $inventory->quantity + $quantity, 4);
            $inventory->save();

            $this->valuation->addLayer($note->tenant_id, (int) $line->item_id, $quantity, $unitCost, $warehouseId, CreditNote::class, $note->id);

            InventoryHistory::create([
                'tenant_id' => $note->tenant_id,
                'item_id' => $line->item_id,
                'warehouse_id' => $warehouseId,
                'type' => 'in',
                'quantity' => $quantity,
                'reference_type' => 'credit_note',
                'reference_id' => $note->id,
                'notes' => "Returned by the customer (credit note {$note->credit_note_number})",
                'created_by' => auth()->id(),
            ]);
        }
    }

    /**
     * Not more of an item can come back than the invoice sold, less what
     * earlier credit notes on it already took back.
     *
     * @param  Collection<int, CreditNoteItem>  $lines
     */
    protected function assertNotMoreThanSold(CreditNote $note, Invoice $invoice, Collection $lines): void
    {
        $sold = $invoice->items->groupBy('item_id')->map(fn ($g) => (float) $g->sum('quantity'));
        $returnedBefore = CreditNoteItem::query()
            ->whereHas('creditNote', fn ($q) => $q->where('invoice_id', $invoice->id)->where('restock', true)
                ->whereIn('status', [CreditNoteStatus::Open->value, CreditNoteStatus::Closed->value])->whereKeyNot($note->id))
            ->whereNotNull('unit_cost')
            ->get()->groupBy('item_id')->map(fn ($g) => (float) $g->sum('quantity'));

        foreach ($lines->groupBy('item_id') as $itemId => $group) {
            $left = ($sold[$itemId] ?? 0) - ($returnedBefore[$itemId] ?? 0);
            if ((float) $group->sum('quantity') - $left > 0.0001) {
                $name = $group->first()->description;
                $left = rtrim(rtrim(number_format(max(0, $left), 4, '.', ','), '0'), '.');
                throw ValidationException::withMessages(['restock' => "Only {$left} of \"{$name}\" can still be returned against invoice {$invoice->invoice_number}."]);
            }
        }
    }

    /** The cost the invoice took the goods out at, else the item's average cost now. */
    protected function costOf(CreditNoteItem $line, ?Invoice $invoice, ?int $warehouseId = null): float
    {
        $sold = $invoice?->items->filter(fn ($i) => (int) $i->item_id === (int) $line->item_id && $i->unit_cost !== null && (float) $i->quantity > 0);
        if ($sold && $sold->isNotEmpty()) {
            $qty = (float) $sold->sum('quantity');

            return round($sold->sum(fn ($i) => (float) $i->unit_cost * (float) $i->quantity) / $qty, 4);
        }

        return round($this->valuation->getWeightedAverageCost($line->item, $warehouseId), 4);
    }
}
