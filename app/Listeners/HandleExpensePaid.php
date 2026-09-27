<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\ExpensePaid;
use App\Services\BankService;

class HandleExpensePaid
{
    public function __construct(
        protected JournalServiceInterface $journalService,
        protected BankService $bankService
    ) {}

    public function handle(ExpensePaid $event): void
    {
        $expense = $event->expense;

        if ($expense->total > 0) {
            $this->journalService->createExpenseJournal($expense);
        }

        if ($expense->bank_id) {
            $this->bankService->debit(
                $expense->bank_id,
                $expense->total,
                "Expense #{$expense->expense_number} paid"
            );
        }
    }
}
