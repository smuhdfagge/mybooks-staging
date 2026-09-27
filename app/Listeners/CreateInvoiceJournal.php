<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\InvoiceSaved;

class CreateInvoiceJournal
{
    public function __construct(
        protected JournalServiceInterface $journalService
    ) {}

    public function handle(InvoiceSaved $event): void
    {
        $invoice = $event->invoice;

        if ($invoice->total > 0 && $invoice->status !== 'draft') {
            $this->journalService->createInvoiceJournal($invoice);
        }
    }
}
