<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deleting an invoice, the same from the web, the API and the bulk action
 * on the invoice list (finding R3). They used to check different things:
 * the amount paid, or whether payment records exist, and none looked at
 * applied credit notes or refunds.
 *
 * The journal is kept and reversed by the InvoiceDeleting event (M6).
 */
class DeleteInvoice
{
    public function handle(Invoice $invoice): void
    {
        if ($reason = $this->blockedBecause($invoice)) {
            throw ValidationException::withMessages(['invoice' => $reason]);
        }

        DB::transaction(function () use ($invoice) {
            $invoice->load('items');
            $invoice->releaseInventoryReservation();
            $invoice->items()->delete();
            $invoice->delete();
        });
    }

    /** Why this invoice can't be deleted, or null if it can. */
    public function blockedBecause(Invoice $invoice): ?string
    {
        if ((float) $invoice->amount_paid > 0 || $invoice->payments()->exists()) {
            return "Invoice {$invoice->invoice_number} has payments. Remove them first.";
        }
        if ($invoice->creditNoteApplications()->exists()) {
            return "Invoice {$invoice->invoice_number} has credit notes applied.";
        }
        if ($invoice->refunds()->where('status', '!=', 'cancelled')->exists()) {
            return "Invoice {$invoice->invoice_number} has refunds.";
        }
        if ($invoice->isReleased()) {
            return "Invoice {$invoice->invoice_number} has been released from stock.";
        }

        return null;
    }
}
