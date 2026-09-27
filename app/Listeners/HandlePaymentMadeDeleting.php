<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\PaymentMadeDeleting;
use App\Models\PaymentMade;

class HandlePaymentMadeDeleting
{
    public function __construct(
        protected JournalServiceInterface $journalService
    ) {}

    public function handle(PaymentMadeDeleting $event): void
    {
        $this->journalService->deleteJournalForTransaction(
            PaymentMade::class,
            $event->payment->id,
            $event->payment->tenant_id
        );
    }
}
