<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\InvoiceRefundDeleting;
use App\Models\InvoiceRefund;

class DeleteInvoiceRefundJournal
{
    public function __construct(
        protected JournalServiceInterface $journalService
    ) {}

    public function handle(InvoiceRefundDeleting $event): void
    {
        $this->journalService->deleteJournalForTransaction(
            InvoiceRefund::class,
            $event->refund->id,
            $event->refund->tenant_id
        );
    }
}
