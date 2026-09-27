<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\PaymentMadeUpdated;

class HandlePaymentMadeUpdated
{
    public function __construct(
        protected JournalServiceInterface $journalService
    ) {}

    public function handle(PaymentMadeUpdated $event): void
    {
        $payment = $event->payment;

        if ($payment->amount > 0) {
            $this->journalService->createPaymentMadeJournal($payment);
        }
    }
}
