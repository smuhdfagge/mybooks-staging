<?php

namespace App\Services\Journals;

use App\Models\Journal;
use App\Models\PaymentReceived;
use Illuminate\Support\Facades\DB;

/**
 * A customer payment's journal.
 *
 * For regular payments:
 * Debit: Cash/Bank (asset increases)
 * Credit: Accounts Receivable (asset decreases)
 *
 * For customer deposits:
 * Debit: Cash/Bank (asset increases)
 * Credit: Customer Deposits (liability increases)
 *
 * For payments from deposit:
 * Debit: Customer Deposits (liability decreases)
 * Credit: Accounts Receivable (asset decreases)
 */
class PaymentReceivedPosting extends DocumentPosting
{
    public function post(PaymentReceived $payment): ?Journal
    {
        $j = $this->journals;

        if ($payment->amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($payment, $j) {
            $t = $payment->tenant_id;

            // Build description based on payment type
            if ($payment->is_deposit) {
                $description = "Customer Deposit {$payment->payment_number} - {$payment->customer->name}";
            } elseif ($payment->payment_method === 'deposit') {
                $invoiceRef = $payment->invoice ? " for Invoice {$payment->invoice->invoice_number}" : '';
                $description = "Deposit Applied {$payment->payment_number} - {$payment->customer->name}{$invoiceRef}";
            } else {
                $invoiceRef = $payment->invoice ? " for Invoice {$payment->invoice->invoice_number}" : '';
                $description = "Payment Received {$payment->payment_number} - {$payment->customer->name}{$invoiceRef}";
            }

            $journal = new Journal([
                'tenant_id' => $payment->tenant_id,
                'journal_date' => $payment->payment_date,
                'reference' => $payment->payment_number,
                'description' => $description,
                'reference_type' => PaymentReceived::class,
                'reference_id' => $payment->id,
                'created_by' => $payment->created_by ?? auth()->id(),
            ]);

            $amount = (float) $payment->amount;
            if ($payment->is_deposit) {
                // Customer deposit: Debit Cash, Credit Customer Deposits (liability)
                $paymentAccountCode = $j->paymentAccountFor($payment->bank, $payment->payment_method, $t);
                $j->createEntry($journal, $paymentAccountCode, $amount, 0,
                    "Customer Deposit - {$payment->payment_number}");

                $j->createEntry($journal, $j->acct($t, 'customer_deposits'), 0, $amount,
                    "Customer Deposit from {$payment->customer->name}");
            } elseif ($payment->payment_method === 'deposit') {
                // Payment from deposit: Debit Customer Deposits (reduce liability), Credit A/R
                $j->createEntry($journal, $j->acct($t, 'customer_deposits'), $amount, 0,
                    "Deposit Applied - {$payment->payment_number}");

                $j->createEntry($journal, $j->acct($t, 'accounts_receivable'), 0, $amount,
                    "Payment for {$payment->customer->name}");
            } else {
                $this->regularLines($journal, $payment);
            }

            return $j->postDocumentJournal($journal, 'Payment edited');
        });
    }

    /**
     * Regular payment: Dr the bank with the money received, Dr WHT Credit
     * Notes Receivable with any WHT the customer deducted, Cr Accounts
     * Receivable with what the payment settles.
     */
    private function regularLines(Journal $journal, PaymentReceived $payment): void
    {
        $j = $this->journals;
        $t = $payment->tenant_id;
        $wht = round((float) $payment->wht_amount, 2);

        $paymentAccountCode = $j->paymentAccountFor($payment->bank, $payment->payment_method, $t);
        $j->createEntry($journal, $paymentAccountCode, (float) $payment->amount, 0,
            "Payment Received - {$payment->payment_number}");

        if ($wht > 0) {
            $j->createEntry($journal, $j->acct($t, 'wht_receivable'), $wht, 0,
                "WHT deducted by {$payment->customer->name} - {$payment->payment_number}");
        }

        $j->createEntry($journal, $j->acct($t, 'accounts_receivable'), 0, $payment->settledAmount(),
            "Payment for {$payment->customer->name}");
    }
}
