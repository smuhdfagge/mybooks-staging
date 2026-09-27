<?php

namespace App\Listeners;

use App\Events\PaymentMadeDeleted;

class HandlePaymentMadeDeleted
{
    public function handle(PaymentMadeDeleted $event): void
    {
        $payment = $event->payment;

        if ($payment->bill) {
            $payment->bill->updateBalances();
        }
    }
}
