<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\PayrollDeleting;
use App\Models\Payroll;

class DeletePayrollJournal
{
    public function __construct(
        protected JournalServiceInterface $journalService
    ) {}

    public function handle(PayrollDeleting $event): void
    {
        $this->journalService->deleteJournalForTransaction(
            Payroll::class,
            $event->payroll->id,
            $event->payroll->tenant_id
        );
    }
}
