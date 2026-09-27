<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\SalesReceiptSaved;

class CreateSalesReceiptJournal
{
    public function __construct(
        protected JournalServiceInterface $journalService
    ) {}

    public function handle(SalesReceiptSaved $event): void
    {
        $receipt = $event->salesReceipt;

        if ($receipt->total > 0) {
            $this->journalService->createSalesReceiptJournal($receipt);
        }
    }
}
