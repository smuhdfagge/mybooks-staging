<?php

namespace App\Services\Journals;

use App\Models\Journal;
use App\Models\SalesReceipt;
use Illuminate\Support\Facades\DB;

/**
 * A cash sale's journal.
 *
 * Debit: Cash/Bank (by payment method)
 * Credit: Sales Revenue, Sales Tax Payable
 * Debit: Cost of Goods Sold, Credit: Inventory (stock items)
 */
class SalesReceiptPosting extends DocumentPosting
{
    public function post(SalesReceipt $receipt): ?Journal
    {
        $j = $this->journals;

        if ($receipt->total <= 0) {
            return null;
        }

        return DB::transaction(function () use ($receipt, $j) {
            $t = $receipt->tenant_id;

            $customerName = $receipt->customer ? $receipt->customer->name : 'Walk-in Customer';

            $journal = new Journal([
                'tenant_id' => $receipt->tenant_id,
                'journal_date' => $receipt->receipt_date,
                'reference' => $receipt->receipt_number,
                'description' => "Sales Receipt {$receipt->receipt_number} - {$customerName}",
                'reference_type' => SalesReceipt::class,
                'reference_id' => $receipt->id,
                'created_by' => $receipt->created_by ?? auth()->id(),
            ]);

            // Debit: Cash/Bank (based on payment method)
            $paymentAccountCode = $j->getPaymentAccountCode($receipt->payment_method ?? 'cash', $t);
            $j->createEntry($journal, $paymentAccountCode, (float) $receipt->total, 0,
                "Cash Sale - {$receipt->receipt_number}");

            // Credit: Sales Revenue (subtotal less discount)
            $revenueAmount = $receipt->subtotal - ($receipt->discount_amount ?? 0);
            if ($revenueAmount > 0) {
                $j->createEntry($journal, $j->acct($t, 'sales_revenue'), 0, $revenueAmount,
                    "Sales - Receipt {$receipt->receipt_number}");
            }

            // Credit: Sales Tax Payable (if tax amount exists)
            if ($receipt->tax_amount > 0) {
                $j->createEntry($journal, $j->acct($t, 'sales_tax_payable'), 0, (float) $receipt->tax_amount,
                    "Tax - Receipt {$receipt->receipt_number}");
            }

            // Record Cost of Goods Sold for inventory items. A cash sale hands
            // the goods over now, so it also lowers stock on hand.
            $cogsAmount = $j->documentCogs($receipt, SalesReceipt::class, true);
            if ($cogsAmount > 0) {
                $j->createEntry($journal, $j->acct($t, 'cost_of_goods_sold'), $cogsAmount, 0,
                    "COGS - Receipt {$receipt->receipt_number}");
                $j->createEntry($journal, $j->acct($t, 'inventory'), 0, $cogsAmount,
                    "Inventory Sold - Receipt {$receipt->receipt_number}");
            }

            return $j->postDocumentJournal($journal, 'Sales receipt edited');
        });
    }
}
