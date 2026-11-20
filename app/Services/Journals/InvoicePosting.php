<?php

namespace App\Services\Journals;

use App\Models\Invoice;
use App\Models\Journal;
use Illuminate\Support\Facades\DB;

/**
 * An invoice's journal.
 *
 * Debit: Accounts Receivable (asset increases)
 * Credit: Sales Revenue (income increases)
 * Credit: Sales Tax Payable (liability increases) - if applicable
 *
 * For inventory items (Perpetual Inventory Method):
 * Debit: Cost of Goods Sold (expense increases)
 * Credit: Inventory (asset decreases)
 */
class InvoicePosting extends DocumentPosting
{
    public function post(Invoice $invoice): ?Journal
    {
        $j = $this->journals;

        if ($invoice->status === 'cancelled') {
            // A cancelled invoice keeps its original journal plus a reversal (C5),
            // and the stock it took goes back into its cost layers.
            $j->reverseDocumentJournal(Invoice::class, $invoice->id, 'Invoice cancelled');
            $j->returnDocumentStock(Invoice::class, $invoice->id);

            return null;
        }

        if ($invoice->total <= 0) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $j) {
            $t = $invoice->tenant_id;

            $journal = new Journal([
                'tenant_id' => $invoice->tenant_id,
                'journal_date' => $invoice->invoice_date,
                'reference' => $invoice->invoice_number,
                'description' => "Invoice {$invoice->invoice_number} - {$invoice->customer->name}",
                'reference_type' => Invoice::class,
                'reference_id' => $invoice->id,
                'created_by' => $invoice->created_by ?? auth()->id(),
            ]);

            // Debit: Accounts Receivable
            $j->createEntry($journal, $j->acct($t, 'accounts_receivable'), (float) $invoice->total, 0,
                "Invoice {$invoice->invoice_number} - {$invoice->customer->name}");

            // Credit: Sales Revenue (subtotal less discount)
            $revenueAmount = $invoice->subtotal - ($invoice->discount_amount ?? 0);
            if ($revenueAmount > 0) {
                $j->createEntry($journal, $j->acct($t, 'sales_revenue'), 0, $revenueAmount,
                    "Sales - Invoice {$invoice->invoice_number}");
            }

            // Credit: Sales Tax Payable (if tax amount exists)
            if ($invoice->tax_amount > 0) {
                $j->createEntry($journal, $j->acct($t, 'sales_tax_payable'), 0, (float) $invoice->tax_amount,
                    "Tax - Invoice {$invoice->invoice_number}");
            }

            // Record Cost of Goods Sold for inventory items (Perpetual Inventory Method).
            // An invoice reserves its stock; it comes off hand when released.
            $cogsAmount = $j->documentCogs($invoice, Invoice::class, false);
            if ($cogsAmount > 0) {
                // Debit: Cost of Goods Sold (expense)
                $j->createEntry($journal, $j->acct($t, 'cost_of_goods_sold'), $cogsAmount, 0,
                    "COGS - Invoice {$invoice->invoice_number}");
                // Credit: Inventory (reduce inventory asset)
                $j->createEntry($journal, $j->acct($t, 'inventory'), 0, $cogsAmount,
                    "Inventory Sold - Invoice {$invoice->invoice_number}");
            }

            return $j->postDocumentJournal($journal, 'Invoice edited');
        });
    }
}
