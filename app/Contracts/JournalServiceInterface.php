<?php

namespace App\Contracts;

use App\Models\Bill;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceRefund;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\Payroll;
use App\Models\SalesReceipt;

interface JournalServiceInterface
{
    public function createInvoiceJournal(Invoice $invoice): ?Journal;

    public function createBillJournal(Bill $bill): ?Journal;

    public function createExpenseJournal(Expense $expense): ?Journal;

    public function createPaymentReceivedJournal(PaymentReceived $payment): ?Journal;

    public function createRefundJournal(InvoiceRefund $refund): ?Journal;

    public function createPaymentMadeJournal(PaymentMade $payment): ?Journal;

    public function createSalesReceiptJournal(SalesReceipt $receipt): ?Journal;

    public function createPayrollJournal(Payroll $payroll): ?Journal;

    public function reverseJournal(Journal $journal, string $reason = 'Reversed'): Journal;

    public function deleteJournalForTransaction(string $referenceType, int $referenceId, ?int $tenantId = null): void;

    public function purgeJournalForTransaction(string $referenceType, int $referenceId, ?int $tenantId = null): void;

    public function createEntry(Journal $journal, string $accountCode, float $debit, float $credit, string $description): JournalEntry;

    public function updateAccountBalances(Journal $journal): void;

    public function getJournalForTransaction(string $referenceType, int $referenceId): ?Journal;

    public function isJournalBalanced(Journal $journal): bool;
}
