<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\PayrollPaid;

class HandlePayrollPaid
{
    public function __construct(
        protected JournalServiceInterface $journalService
    ) {}

    public function handle(PayrollPaid $event): void
    {
        $payroll = $event->payroll;

        if ($payroll->net_salary > 0) {
            $this->journalService->createPayrollJournal($payroll);
        }
    }
}
