<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\Sales\DocumentTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creating and changing an invoice, the same way from every screen
 * (finding R3): the web form, the API, converting a sales order and
 * recurring invoices all come through here.
 *
 * In one transaction it checks there is enough free stock (rows locked),
 * works out the lines and totals with DocumentTotals (VAT after discounts,
 * A4), saves the invoice and its lines, reserves the stock, and only then
 * saves the totals, which posts the journal (InvoiceSaved) with the lines
 * already there.
 *
 * $data keys: customer_id, invoice_date, due_date, reference, notes, terms,
 * discount_type, discount_amount, items[] (item_id, description, quantity,
 * unit_price, discount, discount_type, tax_rate), and optionally status
 * (draft or unpaid; default draft), sales_order_id, recurrent_invoice_id.
 */
class SaveInvoice
{
    /** Statuses a new invoice may start in. Payments and dates set the rest (I3). */
    public const START_STATUSES = ['draft', 'sent', 'unpaid'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $tenantId, array $data, ?int $userId = null): Invoice
    {
        $status = $data['status'] ?? 'draft';
        if (! in_array($status, self::START_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'A new invoice can only be draft, sent or unpaid.']);
        }

        return DB::transaction(function () use ($tenantId, $data, $userId, $status) {
            $this->assertStock($data['items'], $tenantId, null);
            $totals = $this->totals($data);

            $invoice = Invoice::withoutEvents(fn () => Invoice::create([
                'tenant_id' => $tenantId,
                'customer_id' => $data['customer_id'],
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'recurrent_invoice_id' => $data['recurrent_invoice_id'] ?? null,
                'invoice_number' => Invoice::generateNumber($tenantId),
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'discount_type' => $data['discount_type'] ?? null,
                'status' => $status,
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total' => 0,
                'amount_paid' => 0,
                'balance_due' => 0,
                'created_by' => $userId,
            ]));

            $this->writeLines($invoice, $totals['lines']);
            $invoice->reserveInventory();

            // Saving the totals posts the journal, now that the lines exist.
            $invoice->update($this->totalsColumns($totals, 0.0));

            return $invoice->fresh(['items']);
        });
    }

    /**
     * @param  array<string, mixed>  $data  same keys as create(); missing header fields keep their values
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        if ($invoice->status === 'paid' || $invoice->status === 'cancelled') {
            throw ValidationException::withMessages(['invoice' => "A {$invoice->status} invoice can't be changed."]);
        }

        return DB::transaction(function () use ($invoice, $data) {
            $lines = $data['items'] ?? $invoice->items()->get()->map(fn ($l) => $l->only(['item_id', 'description', 'quantity', 'unit_price', 'discount', 'tax_rate']))->all();

            if (! $invoice->isReleased()) {
                $this->assertStock($lines, $invoice->tenant_id, $invoice);
            }

            // No new document discount sent: keep the current one (stored as money).
            if (! array_key_exists('discount_amount', $data)) {
                $data['discount_type'] = 'fixed';
                $data['discount_amount'] = $invoice->discount_amount;
            }
            $totals = $this->totals(['items' => $lines] + $data);

            if ((float) $invoice->amount_paid > $totals['total'] + 0.005) {
                throw ValidationException::withMessages(['items' => 'The new total is less than what has already been paid ('.number_format((float) $invoice->amount_paid, 2).').']);
            }

            $invoice->load('items');
            $invoice->releaseInventoryReservation();

            Invoice::withoutEvents(fn () => $invoice->update(array_filter([
                'customer_id' => $data['customer_id'] ?? null,
                'invoice_date' => $data['invoice_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
            ]) + array_intersect_key($data, array_flip(['reference', 'notes', 'terms'])) + [
                'discount_type' => $data['discount_type'] ?? null,
            ]));

            $invoice->items()->delete();
            $this->writeLines($invoice, $totals['lines']);

            if (! $invoice->isReleased()) {
                $invoice->reserveInventory();
            }

            // Saving the totals re-posts the journal with the new lines.
            $invoice->update($this->totalsColumns($totals, (float) $invoice->amount_paid));

            return $invoice->fresh(['items']);
        });
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $lines
     */
    protected function assertStock(array $lines, int $tenantId, ?Invoice $existing): void
    {
        $shortages = Invoice::stockShortages($lines, $tenantId, $existing);
        if ($shortages) {
            throw ValidationException::withMessages($shortages);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{lines: array<int|string, array<string, mixed>>, subtotal: float, discount_amount: float, tax_amount: float, total: float}
     */
    protected function totals(array $data): array
    {
        return DocumentTotals::calculate($data['items'], $data['discount_type'] ?? null, $data['discount_amount'] ?? 0);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $lines
     */
    protected function writeLines(Invoice $invoice, array $lines): void
    {
        foreach ($lines as $line) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'item_id' => $line['item_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount' => $line['discount'] ?? 0,
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'total' => $line['total'],
            ]);
        }
    }

    /**
     * @param  array{subtotal: float, discount_amount: float, tax_amount: float, total: float}  $totals
     * @return array<string, float>
     */
    protected function totalsColumns(array $totals, float $paid): array
    {
        return [
            'subtotal' => $totals['subtotal'],
            'tax_amount' => $totals['tax_amount'],
            'discount_amount' => $totals['discount_amount'],
            'total' => $totals['total'],
            'balance_due' => round($totals['total'] - $paid, 2),
        ];
    }
}
