<?php

namespace App\Listeners;

use App\Contracts\JournalServiceInterface;
use App\Events\PayrollPaid;
use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanRepayment;
use App\Models\Journal;
use App\Models\Payroll;

class HandlePayrollPaid
{
    public function __construct(
        protected JournalServiceInterface $journalService
    ) {}

    public function handle(PayrollPaid $event): void
    {
        $payroll = $event->payroll;

        // Approved before the cost was posted on approval (older payrolls):
        // post the cost now, then the payment (A10).
        $hasCostJournal = Journal::where('reference_type', Payroll::class)
            ->where('reference_id', $payroll->id)
            ->where('status', 'posted')
            ->exists();
        if (! $hasCostJournal) {
            $this->journalService->createPayrollJournal($payroll);
        }

        $this->journalService->createPayrollPaymentJournal($payroll);

        // Loan repayments deducted in this payroll reduce the loan (A9).
        // Once per payroll and loan, so a retried batch doesn't repeat it.
        foreach ($payroll->deduction_details ?? [] as $deduction) {
            $loanId = $deduction['_loan_id'] ?? null;
            $amount = (float) ($deduction['amount'] ?? 0);
            if (! $loanId || $amount <= 0) {
                continue;
            }
            if (EmployeeLoanRepayment::where('employee_loan_id', $loanId)->where('payroll_id', $payroll->id)->exists()) {
                continue;
            }
            EmployeeLoan::find($loanId)?->recordRepayment($amount, $payroll->id, optional($payroll->pay_date)->toDateString());
        }
    }
}
