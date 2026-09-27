<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\BillSaved;

class CreateBillJournal
{
    public function __construct(
        protected JournalServiceInterface $journalService
    ) {}

    public function handle(BillSaved $event): void
    {
        $bill = $event->bill;

        if ($bill->total > 0 && $bill->status !== 'draft') {
            $this->journalService->createBillJournal($bill);
        }
    }
}
