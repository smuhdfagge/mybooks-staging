<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\BillDeleting;
use App\Models\Bill;

class DeleteBillJournal
{
    public function __construct(
        protected JournalServiceInterface $journalService
    ) {}

    public function handle(BillDeleting $event): void
    {
        $this->journalService->deleteJournalForTransaction(
            Bill::class,
            $event->bill->id,
            $event->bill->tenant_id
        );
    }
}
