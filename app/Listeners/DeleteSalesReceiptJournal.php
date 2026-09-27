<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\SalesReceiptDeleting;
use App\Models\SalesReceipt;

class DeleteSalesReceiptJournal
{
    public function __construct(
        protected JournalServiceInterface $journalService
    ) {}

    public function handle(SalesReceiptDeleting $event): void
    {
        $this->journalService->deleteJournalForTransaction(
            SalesReceipt::class,
            $event->salesReceipt->id,
            $event->salesReceipt->tenant_id
        );
    }
}
