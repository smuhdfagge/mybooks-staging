<?php

namespace App\Services\Journals;

use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\Journal;
use Illuminate\Support\Facades\DB;

/**
 * An expense's journal.
 *
 * Debit: Expense Account (expense increases), Input Tax
 * Credit: Cash/Bank Account (asset decreases)
 */
class ExpensePosting extends DocumentPosting
{
    public function post(Expense $expense): ?Journal
    {
        $j = $this->journals;

        if ($expense->total <= 0) {
            return null;
        }

        return DB::transaction(function () use ($expense, $j) {
            $t = $expense->tenant_id;

            $journal = new Journal([
                'tenant_id' => $expense->tenant_id,
                'journal_date' => $expense->expense_date,
                'reference' => $expense->expense_number,
                'description' => "Expense {$expense->expense_number} - {$expense->name}",
                'reference_type' => Expense::class,
                'reference_id' => $expense->id,
                'created_by' => $expense->created_by ?? auth()->id(),
            ]);

            // Debit: Expense Account (use the expense's assigned account)
            // Either account may be unset, or deleted since: then the defaults.
            $expenseAccountCode = ChartOfAccount::whereKey($expense->expense_account_id)->value('account_code') ?? $j->acct($t, 'miscellaneous_expense');
            $j->createEntry($journal, $expenseAccountCode, (float) $expense->amount, 0,
                "Expense - {$expense->name}");

            // Debit: Input Tax (if applicable)
            if ($expense->tax_amount > 0) {
                $j->createEntry($journal, $j->acct($t, 'input_vat'), (float) $expense->tax_amount, 0,
                    "Input Tax - {$expense->expense_number}");
            }

            // Credit: Payment Account (cash, bank, etc.)
            $paymentAccountCode = ChartOfAccount::whereKey($expense->paid_through_id)->value('account_code')
                ?? $j->paymentAccountFor($expense->bank, $expense->payment_method, $t);
            $j->createEntry($journal, $paymentAccountCode, 0, (float) $expense->total,
                "Payment - {$expense->expense_number}");

            return $j->postDocumentJournal($journal, 'Expense edited');
        });
    }
}
