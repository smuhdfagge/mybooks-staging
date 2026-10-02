<?php

namespace App\Actions\Payments;

use App\Models\PaymentMade;
use App\Services\BankService;
use Illuminate\Support\Facades\DB;

/**
 * Deleting a vendor payment, the same from the web and the API (finding
 * R3): the money goes back into the bank's balance, which the API skipped.
 * The Deleting/Deleted events reverse the journal and update the bill.
 */
class DeletePaymentMade
{
    public function __construct(protected BankService $bank) {}

    public function handle(PaymentMade $payment): void
    {
        DB::transaction(function () use ($payment) {
            $this->bank->credit($payment->bank_id, $payment->cashAmount(), "Payment made #{$payment->payment_number} deleted");
            $payment->delete();
        });
    }
}
