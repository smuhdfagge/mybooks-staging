<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\PaymentReceivedDeleting;
use App\Models\PaymentReceived;

class HandlePaymentReceivedDeleting
{
    public function __construct(
        protected JournalServiceInterface $journalService
    ) {}

    public function handle(PaymentReceivedDeleting $event): void
    {
        $this->journalService->deleteJournalForTransaction(
            PaymentReceived::class,
            $event->payment->id,
            $event->payment->tenant_id
        );
    }
}
