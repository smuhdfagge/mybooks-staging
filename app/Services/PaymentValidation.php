<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\PaymentReceived;

/**
 * Business checks for recording payments (finding M5), shared by the web and
 * API controllers. Each method returns validation errors keyed by field, or
 * an empty array when the payment is acceptable.
 */
class PaymentValidation
{
    /** Amounts are stored to the kobo; allow for float noise only. */
    private const TOLERANCE = 0.005;

    /** Documents that cannot be paid. */
    private const UNPAYABLE = ['draft', 'cancelled', 'void', 'voided'];

    /**
     * A payment of $amount against an invoice.
     *
     * $alreadyCounted is the amount of this same payment already included in
     * the invoice's amount_paid (when editing an existing payment).
     */
    public static function forInvoice(?Invoice $invoice, int|string $customerId, float $amount, float $alreadyCounted = 0.0): array
    {
        if (! $invoice) {
            return [];
        }

        if ((int) $invoice->customer_id !== (int) $customerId) {
            return ['invoice_id' => 'This invoice belongs to a different customer.'];
        }

        if (in_array($invoice->status, self::UNPAYABLE, true)) {
            return ['invoice_id' => "Invoice {$invoice->invoice_number} is {$invoice->status} and cannot take payments."];
        }

        $outstanding = round((float) $invoice->balance_due + $alreadyCounted, 2);
        if ($amount - $outstanding > self::TOLERANCE) {
            return ['amount' => 'The amount is more than the '.number_format($outstanding, 2)." still owed on invoice {$invoice->invoice_number}. Record the extra as a customer deposit instead."];
        }

        return [];
    }

    /**
     * Applying $depositAmount from a customer deposit to an invoice, plus an
     * optional extra $cashAmount paid on top.
     */
    public static function forDepositApplication(?PaymentReceived $deposit, ?Invoice $invoice, int|string $customerId, float $depositAmount, float $cashAmount): array
    {
        if (! $deposit || ! $deposit->is_deposit) {
            return ['apply_deposit_id' => 'Choose a customer deposit to apply.'];
        }

        if ((int) $deposit->customer_id !== (int) $customerId) {
            return ['apply_deposit_id' => 'This deposit belongs to a different customer.'];
        }

        if ($depositAmount - (float) $deposit->unused_amount > self::TOLERANCE) {
            return ['deposit_amount' => 'Only '.number_format((float) $deposit->unused_amount, 2).' of this deposit is left to apply.'];
        }

        if (! $invoice) {
            return ['invoice_id' => 'Choose the invoice to apply the deposit to.'];
        }

        return self::forInvoice($invoice, $customerId, $depositAmount + max(0.0, $cashAmount));
    }

    /**
     * A payment of $amount against a bill.
     */
    public static function forBill(?Bill $bill, int|string $vendorId, float $amount, float $alreadyCounted = 0.0): array
    {
        if (! $bill) {
            return [];
        }

        if ((int) $bill->vendor_id !== (int) $vendorId) {
            return ['bill_id' => 'This bill belongs to a different vendor.'];
        }

        if (in_array($bill->status, self::UNPAYABLE, true)) {
            return ['bill_id' => "Bill {$bill->bill_number} is {$bill->status} and cannot take payments."];
        }

        $outstanding = round((float) $bill->balance_due + $alreadyCounted, 2);
        if ($amount - $outstanding > self::TOLERANCE) {
            return ['amount' => 'The amount is more than the '.number_format($outstanding, 2)." still owed on bill {$bill->bill_number}."];
        }

        return [];
    }
}
