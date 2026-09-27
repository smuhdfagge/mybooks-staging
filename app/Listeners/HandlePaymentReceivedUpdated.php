<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\PaymentReceivedUpdated;

class HandlePaymentReceivedUpdated
{
    public function __construct(
        protected JournalServiceInterface $journalService
    ) {}

    public function handle(PaymentReceivedUpdated $event): void
    {
        $payment = $event->payment;

        if ($payment->is_deposit) {
            $payment->customer->updateDepositBalance();
        }

        if ($payment->amount > 0) {
            $this->journalService->createPaymentReceivedJournal($payment);
        }
    }
}
