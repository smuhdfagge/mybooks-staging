<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\PaymentMadeCreated;

class HandlePaymentMadeCreated
{
    public function __construct(
        protected JournalServiceInterface $journalService
    ) {}

    public function handle(PaymentMadeCreated $event): void
    {
        $payment = $event->payment;

        if ($payment->bill) {
            $payment->bill->updateBalances();
        }

        if ($payment->amount > 0) {
            $this->journalService->createPaymentMadeJournal($payment);
        }
    }
}
