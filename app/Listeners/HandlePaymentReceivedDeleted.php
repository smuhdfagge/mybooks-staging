<?php

namespace App\Listeners;

use App\Events\PaymentReceivedDeleted;

class HandlePaymentReceivedDeleted
{
    public function handle(PaymentReceivedDeleted $event): void
    {
        $payment = $event->payment;

        if ($payment->invoice) {
            $payment->invoice->updateBalances();
        }

        if ($payment->is_deposit && $payment->customer) {
            $payment->customer->updateDepositBalance();
        }
    }
}
