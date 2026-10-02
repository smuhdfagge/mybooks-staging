<?php

namespace App\Actions\Payments;

use App\Models\PaymentReceived;
use App\Services\BankService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deleting a customer payment, the same from the web and the API
 * (finding R3). The API let an applied deposit be deleted and left the
 * money in the bank's balance. The Deleting/Deleted events reverse the
 * journal and update the invoice and deposit balances.
 */
class DeletePaymentReceived
{
    public function __construct(protected BankService $bank) {}

    public function handle(PaymentReceived $payment): void
    {
        if ($reason = $this->blockedBecause($payment)) {
            throw ValidationException::withMessages(['payment' => $reason]);
        }

        DB::transaction(function () use ($payment) {
            $this->bank->debit($payment->bank_id, $payment->cashAmount(), "Payment received #{$payment->payment_number} deleted");
            $payment->whtCredit()->delete(); // the WHT goes with the payment (tax pack 2)
            $payment->delete();
        });
    }

    public function blockedBecause(PaymentReceived $payment): ?string
    {
        if ($payment->is_deposit && $payment->depositApplications()->exists()) {
            return 'Cannot delete a deposit that has been applied to invoices.';
        }

        return null;
    }
}
