<?php

namespace App\Actions\CreditNotes;

use App\Models\CreditNote;
use App\Models\CreditNoteApplication;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Using (part of) an open credit note against one of the same customer's
 * unpaid invoices. The invoice's balance goes down (Invoice::updateBalances
 * counts applied credits) and so does the credit note's. Nothing is posted:
 * both sit in accounts receivable.
 */
class ApplyCreditNote
{
    public function handle(CreditNote $note, Invoice $invoice, float $amount, ?string $date = null): CreditNoteApplication
    {
        $amount = round($amount, 2);

        return DB::transaction(function () use ($note, $invoice, $amount, $date) {
            $note = CreditNote::lockForUpdate()->findOrFail($note->id);
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            if (! $note->isOpen()) {
                throw ValidationException::withMessages(['amount' => 'Only an open credit note can be applied.']);
            }
            if ((int) $invoice->customer_id !== (int) $note->customer_id) {
                throw ValidationException::withMessages(['invoice_id' => 'The invoice belongs to a different customer.']);
            }
            if (in_array($invoice->status, ['draft', 'cancelled', 'void', 'paid'], true)) {
                throw ValidationException::withMessages(['invoice_id' => "Invoice {$invoice->invoice_number} is {$invoice->status}."]);
            }
            if ($amount <= 0 || $amount - (float) $note->balance > 0.005) {
                throw ValidationException::withMessages(['amount' => 'Only '.number_format((float) $note->balance, 2).' of this credit is left.']);
            }
            if ($amount - (float) $invoice->balance_due > 0.005) {
                throw ValidationException::withMessages(['amount' => "Invoice {$invoice->invoice_number} only has ".number_format((float) $invoice->balance_due, 2).' left to pay.']);
            }

            $application = CreditNoteApplication::create([
                'credit_note_id' => $note->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'applied_date' => $date ?? now()->toDateString(),
                'applied_by' => auth()->id(),
            ]);

            $note->reduceBalance($amount);
            $invoice->updateBalances();

            return $application;
        });
    }
}
