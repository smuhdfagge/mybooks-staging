<?php

namespace App\Actions\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Services\Sales\DocumentTotals;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating and changing a customer credit note. Saved as a draft (posts
 * nothing) or opened straight away (OpenCreditNote posts it). Only a draft
 * can be changed.
 *
 * Lines and VAT are worked out like an invoice's (DocumentTotals). A line
 * without VAT can say how it is treated for the VAT return (zero-rated,
 * exempt, out of scope); otherwise it takes the credited invoice line's.
 *
 * A credit note raised against an invoice must be for that invoice's
 * customer, each line must be on the invoice, no line can credit more
 * (quantity or amount) than the invoice line less earlier credit notes,
 * and the whole note can't be more than the invoice's total less earlier
 * credit notes.
 *
 * $data keys: customer_id, invoice_id, credit_note_date, reason, notes,
 * restock (goods returned), status (draft|open, create only; default
 * draft), items[] (item_id, description, quantity, unit_price, tax_rate,
 * vat_treatment).
 */
class SaveCreditNote
{
    public function __construct(protected OpenCreditNote $open) {}

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $userId = null): CreditNote
    {
        $status = $data['status'] ?? CreditNoteStatus::Draft->value;
        if (! in_array($status, CreditNoteStatus::startValues(), true)) {
            throw ValidationException::withMessages(['status' => 'A new credit note can only be a draft or open.']);
        }

        return DB::transaction(function () use ($tenantId, $data, $userId, $status) {
            $totals = $this->totals($data);
            $this->check($data, $totals, null);

            $note = CreditNote::create([
                'tenant_id' => $tenantId,
                'customer_id' => $data['customer_id'],
                'invoice_id' => $data['invoice_id'] ?? null,
                'credit_note_number' => CreditNote::generateNumber($tenantId),
                'credit_note_date' => $data['credit_note_date'],
                'reason' => $data['reason'] ?? null,
                'restock' => (bool) ($data['restock'] ?? false),
                'notes' => $data['notes'] ?? null,
                'status' => CreditNoteStatus::Draft->value,
                'created_by' => $userId,
            ] + $this->totalsColumns($totals));

            $this->writeLines($note, $totals['lines']);

            if ($status === CreditNoteStatus::Open->value) {
                $this->open->handle($note);
            }

            return $note->fresh(['items']);
        });
    }

    /** @param array<string, mixed> $data same keys as create() */
    public function update(CreditNote $note, array $data): CreditNote
    {
        if (! $note->isDraft()) {
            throw ValidationException::withMessages(['credit_note' => "Credit note {$note->credit_note_number} is {$note->status}, so it can't be changed."]);
        }

        return DB::transaction(function () use ($note, $data) {
            $totals = $this->totals($data);
            $this->check($data, $totals, $note);

            $note->update([
                'customer_id' => $data['customer_id'],
                'invoice_id' => $data['invoice_id'] ?? null,
                'credit_note_date' => $data['credit_note_date'],
                'reason' => $data['reason'] ?? null,
                'restock' => (bool) ($data['restock'] ?? false),
                'notes' => $data['notes'] ?? null,
            ] + $this->totalsColumns($totals));

            $note->items()->delete();
            $this->writeLines($note, $totals['lines']);

            return $note->fresh(['items']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{lines: array<int|string, array<string, mixed>>, total: float}  $totals
     */
    protected function check(array $data, array $totals, ?CreditNote $current): void
    {
        if ($totals['total'] <= 0) {
            throw ValidationException::withMessages(['items' => 'The credit must be for more than zero.']);
        }

        if (! empty($data['restock'])) {
            $stocked = collect($totals['lines'])->contains(function ($l) {
                $item = ! empty($l['item_id']) ? Item::find($l['item_id']) : null;

                return $item && $item->track_inventory && $item->type !== 'service';
            });
            if (! $stocked) {
                throw ValidationException::withMessages(['restock' => 'None of the lines is a stock item, so no goods can go back into stock. Untick "The customer returned the goods".']);
            }
        }

        if (empty($data['invoice_id'])) {
            return;
        }

        $invoice = Invoice::with('items')->find($data['invoice_id']);
        if (! $invoice || (int) $invoice->customer_id !== (int) $data['customer_id']) {
            throw ValidationException::withMessages(['invoice_id' => 'That invoice belongs to a different customer.']);
        }
        if (in_array($invoice->status, ['draft', 'cancelled', 'void'], true)) {
            throw ValidationException::withMessages(['invoice_id' => "Invoice {$invoice->invoice_number} is {$invoice->status}: edit or delete it instead of crediting it."]);
        }

        $earlierIds = CreditNote::where('invoice_id', $invoice->id)
            ->where('status', '!=', CreditNoteStatus::Void->value)
            ->when($current, fn ($q) => $q->whereKeyNot($current->id))
            ->pluck('id');

        $room = Money::subtract($invoice->total, (float) CreditNote::whereIn('id', $earlierIds)->sum('total'));
        if ($totals['total'] - $room > 0.005) {
            throw ValidationException::withMessages(['items' => "Invoice {$invoice->invoice_number} can only be credited up to ".Money::format(max(0, $room)).' more.']);
        }

        $this->checkLines($invoice, collect($totals['lines']), CreditNoteItem::whereIn('credit_note_id', $earlierIds)->get());
    }

    /**
     * Each line must match a line of the invoice (same item, else same
     * description) and can't credit more than that line: not more quantity,
     * and not more money (before VAT) than is left after earlier credit notes.
     *
     * @param  Collection<int|string, array<string, mixed>>  $lines
     * @param  Collection<int, CreditNoteItem>  $earlier
     */
    protected function checkLines(Invoice $invoice, Collection $lines, Collection $earlier): void
    {
        $key = fn ($itemId, $description) => $itemId ? 'item:'.(int) $itemId : 'text:'.mb_strtolower(trim((string) $description));

        $nets = self::invoiceLineNets($invoice);
        $invoiced = $invoice->items->groupBy(fn (InvoiceItem $l) => $key($l->item_id, $l->description))
            ->map(fn ($g) => ['qty' => (float) $g->sum('quantity'), 'net' => round($g->sum(fn ($l) => $nets[$l->id]), 2)]);
        $credited = $earlier->groupBy(fn (CreditNoteItem $l) => $key($l->item_id, $l->description))
            ->map(fn ($g) => round($g->sum(fn (CreditNoteItem $l) => $l->net()), 2));

        foreach ($lines->values()->groupBy(fn ($l) => $key($l['item_id'] ?? null, $l['description'] ?? '')) as $k => $group) {
            $name = $group->first()['description'] ?? 'this line';
            if (! isset($invoiced[$k])) {
                throw ValidationException::withMessages(['items' => "\"{$name}\" isn't on invoice {$invoice->invoice_number}. Credit it on a separate credit note without an invoice."]);
            }

            $qty = (float) $group->sum('quantity');
            if ($qty - $invoiced[$k]['qty'] > 0.0001) {
                throw ValidationException::withMessages(['items' => "Invoice {$invoice->invoice_number} only has {$this->qty($invoiced[$k]['qty'])} of \"{$name}\"."]);
            }

            $net = round($group->sum(fn ($l) => (float) $l['total'] - (float) $l['tax_amount']), 2);
            $left = round($invoiced[$k]['net'] - ($credited[$k] ?? 0), 2);
            if ($net - $left > 0.005) {
                throw ValidationException::withMessages(['items' => 'Only '.Money::format(max(0, $left))." (before VAT) of \"{$name}\" on invoice {$invoice->invoice_number} is left to credit."]);
            }
        }
    }

    /**
     * What each invoice line was actually sold for before VAT: its amount
     * less its share of the invoice's discount (shared like the VAT return
     * does).
     *
     * @return array<int, float> invoice line id => net
     */
    public static function invoiceLineNets(Invoice $invoice): array
    {
        $items = $invoice->items->sortBy('id')->values();
        $nets = $items->map(fn (InvoiceItem $l) => Money::subtract($l->total, $l->tax_amount))->all();
        $discount = (float) $invoice->discount_amount;
        $shares = ($discount > 0 && $nets) ? Money::allocate($discount, $nets) : array_fill(0, count($nets), 0.0);

        $out = [];
        foreach ($items as $k => $line) {
            $out[$line->id] = Money::subtract($nets[$k], $shares[$k]);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{lines: array<int|string, array<string, mixed>>, subtotal: float, discount_amount: float, tax_amount: float, total: float}
     */
    protected function totals(array $data): array
    {
        // No discounts on a credit note: the price is what is being credited.
        $lines = array_map(fn ($l) => array_diff_key($l, ['discount' => true, 'discount_type' => true]), $data['items'] ?? []);
        $lines = array_values(array_filter($lines, fn ($l) => (float) ($l['quantity'] ?? 0) > 0));
        if ($lines === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one line with a quantity.']);
        }

        return DocumentTotals::calculate($lines, null, 0);
    }

    /** @param array<int|string, array<string, mixed>> $lines */
    protected function writeLines(CreditNote $note, array $lines): void
    {
        foreach ($lines as $line) {
            CreditNoteItem::create([
                'credit_note_id' => $note->id,
                'item_id' => $line['item_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'vat_treatment' => $line['vat_treatment'] ?? null,
                'total' => $line['total'],
            ]);
        }
    }

    /**
     * @param  array{subtotal: float, tax_amount: float, total: float}  $totals
     * @return array<string, float>
     */
    protected function totalsColumns(array $totals): array
    {
        return [
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
            'balance' => 0,
        ];
    }

    private function qty(float $q): string
    {
        return rtrim(rtrim(number_format($q, 4, '.', ','), '0'), '.');
    }
}
