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

        // Stock arrives when the bill is posted, the same moment the journal
        // debits Inventory (A21). It used to wait until the bill was fully
        // paid, so stock and the ledger disagreed for every unpaid bill.
        // (The bill is saved once before its lines exist; wait for the lines.)
        if (! in_array($bill->status, ['draft', 'cancelled'], true) && ! $bill->inventory_updated_at && $bill->items()->exists()) {
            $bill->updateInventory();
        }
    }
}
