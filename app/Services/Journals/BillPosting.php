<?php

namespace App\Services\Journals;

use App\Models\Bill;
use App\Models\Journal;
use Illuminate\Support\Facades\DB;

/**
 * A bill's journal.
 *
 * Debit: Inventory for stocked items, each other line's own account (MB-14)
 * Debit: Input VAT
 * Credit: Accounts Payable
 */
class BillPosting extends DocumentPosting
{
    public function post(Bill $bill): ?Journal
    {
        $j = $this->journals;

        if ($bill->status === 'cancelled') {
            $j->reverseDocumentJournal(Bill::class, $bill->id, 'Bill cancelled');

            return null;
        }

        if ($bill->total <= 0) {
            return null;
        }

        return DB::transaction(function () use ($bill, $j) {
            $t = $bill->tenant_id;

            $journal = new Journal([
                'tenant_id' => $bill->tenant_id,
                'journal_date' => $bill->bill_date,
                'reference' => $bill->bill_number,
                'description' => "Bill {$bill->bill_number} - {$bill->vendor->name}",
                'reference_type' => Bill::class,
                'reference_id' => $bill->id,
                'created_by' => $bill->created_by ?? auth()->id(),
            ]);

            // Debit: Inventory for stocked items, and each other line's own account (MB-14)
            [$inventoryAmount, $expenses] = $j->billDebitSplit($bill);
            $this->postDebits($journal, $bill, $inventoryAmount, $expenses);

            // If no items or all have zero totals, debit inventory by default
            if ($inventoryAmount == 0 && ! $expenses && $bill->subtotal > 0) {
                $j->createEntry($journal, $j->acct($t, 'inventory'), round((float) $bill->subtotal - (float) ($bill->discount_amount ?? 0), 2), 0,
                    "Purchase - Bill {$bill->bill_number}");
            }

            // Debit: Tax if applicable (Input VAT is typically an asset)
            if ($bill->tax_amount > 0) {
                $j->createEntry($journal, $j->acct($t, 'input_vat'), (float) $bill->tax_amount, 0,
                    "Input VAT - Bill {$bill->bill_number}"); // own account, not Prepaid Expenses (A5)
            }

            // Credit: Accounts Payable
            $j->createEntry($journal, $j->acct($t, 'accounts_payable'), 0, (float) $bill->total,
                "Bill {$bill->bill_number} - {$bill->vendor->name}");

            return $j->postDocumentJournal($journal, 'Bill edited');
        });
    }

    /**
     * @param  array<string, float>  $expenses
     */
    private function postDebits(Journal $journal, Bill $bill, float $inventory, array $expenses): void
    {
        $j = $this->journals;
        if ($inventory > 0) {
            $j->createEntry($journal, $j->acct($bill->tenant_id, 'inventory'), $inventory, 0,
                "Inventory Purchase - Bill {$bill->bill_number}");
        }
        // Account codes are numeric, so they arrive as int keys.
        foreach ($expenses as $code => $amount) {
            if ($amount > 0) {
                $j->createEntry($journal, (string) $code, $amount, 0, "Expense - Bill {$bill->bill_number}");
            } elseif ($amount < 0) {
                $j->createEntry($journal, (string) $code, 0, -$amount, "Expense - Bill {$bill->bill_number}");
            }
        }
    }
}
