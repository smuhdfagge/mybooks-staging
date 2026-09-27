<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\PaymentReceivedCreated;

class HandlePaymentReceivedCreated
{
    public function __construct(
        protected JournalServiceInterface $journalService
    ) {}

    public function handle(PaymentReceivedCreated $event): void
    {
        $payment = $event->payment;

        if ($payment->invoice) {
            $payment->invoice->updateBalances();
        }

        if ($payment->is_deposit) {
            $payment->customer->updateDepositBalance();
        }

        if ($payment->amount > 0) {
            $this->journalService->createPaymentReceivedJournal($payment);
        }
    }
}
