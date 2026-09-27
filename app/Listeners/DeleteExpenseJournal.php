<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\ExpenseDeleting;
use App\Models\Expense;

class DeleteExpenseJournal
{
    public function __construct(
        protected JournalServiceInterface $journalService
    ) {}

    public function handle(ExpenseDeleting $event): void
    {
        $this->journalService->deleteJournalForTransaction(
            Expense::class,
            $event->expense->id,
            $event->expense->tenant_id
        );
    }
}
