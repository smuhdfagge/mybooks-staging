<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\InvoiceDeleting;
use App\Models\Invoice;

class DeleteInvoiceJournal
{
    public function __construct(
        protected JournalServiceInterface $journalService
    ) {}

    public function handle(InvoiceDeleting $event): void
    {
        $this->journalService->deleteJournalForTransaction(
            Invoice::class,
            $event->invoice->id,
            $event->invoice->tenant_id
        );
    }
}
