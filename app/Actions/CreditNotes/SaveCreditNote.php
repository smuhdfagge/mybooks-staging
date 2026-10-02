<?php

namespace App\Actions\CreditNotes;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Services\Sales\DocumentTotals;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating and changing a draft customer credit note. A draft posts
 * nothing; OpenCreditNote posts it.
 *
 * Lines and VAT are worked out like an invoice's (DocumentTotals). A credit
 * note raised against an invoice must be for that invoice's customer and
 * can't credit more than the invoice was for, less earlier credit notes.
 *
 * $data keys: customer_id, invoice_id, credit_note_date, reason, notes,
 * restock (goods returned), items[] (item_id, description, quantity,
 * unit_price, tax_rate).
 */
class SaveCreditNote
{
    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $userId = null): CreditNote
    {
        return DB::transaction(function () use ($tenantId, $data, $userId) {
            $totals = $this->totals($data);
            $this->assertInvoice($data, $totals['total'], null);

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

            return $note->fresh(['items']);
        });
    }

    /** @param array<string, mixed> $data same keys as create() */
    public function update(CreditNote $note, array $data): CreditNote
    {
        if ($note->status !== CreditNoteStatus::Draft->value) {
            throw ValidationException::withMessages(['credit_note' => "A {$note->status} credit note can't be changed."]);
        }

        return DB::transaction(function () use ($note, $data) {
            $totals = $this->totals($data);
            $this->assertInvoice($data, $totals['total'], $note);

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

    /** @param array<string, mixed> $data */
    protected function assertInvoice(array $data, float $total, ?CreditNote $current): void
    {
        if (empty($data['invoice_id'])) {
            return;
        }

        $invoice = Invoice::find($data['invoice_id']);
        if (! $invoice || (int) $invoice->customer_id !== (int) $data['customer_id']) {
            throw ValidationException::withMessages(['invoice_id' => 'That invoice belongs to a different customer.']);
        }
        if (in_array($invoice->status, ['draft', 'cancelled'], true)) {
            throw ValidationException::withMessages(['invoice_id' => "Invoice {$invoice->invoice_number} is {$invoice->status}: edit or delete it instead of crediting it."]);
        }

        $earlier = (float) CreditNote::where('invoice_id', $invoice->id)
            ->where('status', '!=', CreditNoteStatus::Void->value)
            ->when($current, fn ($q) => $q->whereKeyNot($current->id))
            ->sum('total');
        $room = Money::subtract($invoice->total, $earlier);
        if ($total - $room > 0.005) {
            throw ValidationException::withMessages(['items' => "Invoice {$invoice->invoice_number} can only be credited up to ".number_format(max(0, $room), 2).' more.']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{lines: array<int|string, array<string, mixed>>, subtotal: float, discount_amount: float, tax_amount: float, total: float}
     */
    protected function totals(array $data): array
    {
        $lines = array_map(fn ($l) => array_diff_key($l, ['discount' => true, 'discount_type' => true]), $data['items']);

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
}
