<?php

namespace App\Services;

use App\Contracts\JournalServiceInterface;
use App\Exceptions\UnbalancedJournalException;
use App\Models\Bank;
use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\EmployeeLoan;
use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\Invoice;
use App\Models\InvoiceRefund;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\Payroll;
use App\Models\SalesReceipt;
use App\Models\WhtCreditUtilisation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class JournalService implements JournalServiceInterface
{
    /**
     * Standard account codes for double-entry bookkeeping.
     * These constants serve as system defaults.
     * At runtime, use $this->acct() which resolves per-tenant overrides.
     */
    const ACCOUNT_CASH = '1000';

    const ACCOUNT_CHECKING = '1100';

    const ACCOUNT_ACCOUNTS_RECEIVABLE = '1200';

    const ACCOUNT_INVENTORY = '1300';

    const ACCOUNT_ACCOUNTS_PAYABLE = '2000';

    const ACCOUNT_CUSTOMER_DEPOSITS = '2350';

    const ACCOUNT_SALES_TAX_PAYABLE = '2400';

    const ACCOUNT_SALES_REVENUE = '4000';

    const ACCOUNT_COST_OF_GOODS_SOLD = '5000';

    const ACCOUNT_EMPLOYEE_ADVANCES = '1250';

    const ACCOUNT_ACCRUED_SALARIES = '2210';

    const ACCOUNT_SALARIES_WAGES = '6000';

    const ACCOUNT_PAYROLL_TAXES = '6020';

    const ACCOUNT_ALLOWANCES_EXPENSE = '6030';

    const ACCOUNT_OVERTIME_EXPENSE = '6040';

    const ACCOUNT_EMPLOYER_PENSION = '6050';

    const ACCOUNT_EMPLOYER_HEALTH_INSURANCE = '6060';

    const ACCOUNT_WORKERS_COMP = '6070';

    const ACCOUNT_PAYROLL_LIABILITIES = '2300';

    const ACCOUNT_TAX_PAYABLE = '2310';

    const ACCOUNT_PENSION_PAYABLE = '2320';

    const ACCOUNT_INSURANCE_PAYABLE = '2330';

    const ACCOUNT_UNION_DUES_PAYABLE = '2340';

    const ACCOUNT_GARNISHMENTS_PAYABLE = '2360';

    /**
     * Resolve an account code for a tenant.
     * Delegates to AccountCodeService which checks tenant settings then falls back to defaults.
     */
    protected function acct(int $tenantId, string $logicalName): string
    {
        return AccountCodeService::resolve($tenantId, $logicalName);
    }

    /**
     * Payment method to account mapping — delegated to AccountCodeService.
     */
    protected array $paymentMethodAccounts = [
        'cash' => '1000',      // Cash
        'check' => '1100',     // Checking Account
        'cheque' => '1100',    // Checking Account
        'bank_transfer' => '1100', // Checking Account
        'credit_card' => '2100',   // Credit Card Payable (when paying bills)
        'debit_card' => '1100',    // Checking Account
        'online' => '1100',        // Checking Account
        'mobile_money' => '1000',  // Cash equivalent
        'deposit' => '2350',       // Customer Deposits (when applying deposit to invoice)
        'other' => '1000',         // Default to cash
    ];

    /**
     * Map a deduction name to its specific liability account code.
     * Uses keyword matching since deduction names are tenant-defined.
     */
    protected function mapDeductionToLiabilityAccount(string $name, int $tenantId): string
    {
        $name = strtolower($name);

        if (preg_match('/\btax\b|\\bpaye\\b|\\bwithholding\\b/', $name)) {
            return $this->acct($tenantId, 'tax_payable');
        }
        if (preg_match('/pension|provident|retirement|401k|superannuation|nssf/', $name)) {
            return $this->acct($tenantId, 'pension_payable');
        }
        if (preg_match('/insurance|health|medical|nhif|hmo/', $name)) {
            return $this->acct($tenantId, 'insurance_payable');
        }
        if (preg_match('/union|dues/', $name)) {
            return $this->acct($tenantId, 'union_dues_payable');
        }
        if (preg_match('/garnish|court|child.?support|alimony/', $name)) {
            return $this->acct($tenantId, 'garnishments_payable');
        }

        return $this->acct($tenantId, 'payroll_liabilities');
    }

    /**
     * Map an employer contribution name to its specific expense account code.
     */
    protected function mapContributionToExpenseAccount(string $name, int $tenantId): string
    {
        $name = strtolower($name);

        if (preg_match('/pension|provident|retirement|401k|superannuation|nssf/', $name)) {
            return $this->acct($tenantId, 'employer_pension');
        }
        if (preg_match('/insurance|health|medical|nhif|hmo/', $name)) {
            return $this->acct($tenantId, 'employer_health_insurance');
        }
        if (preg_match('/worker|comp|wc\\b|wcf/', $name)) {
            return $this->acct($tenantId, 'workers_comp');
        }

        return $this->acct($tenantId, 'payroll_taxes');
    }

    /**
     * Map an employer contribution name to its specific liability account code.
     */
    protected function mapContributionToLiabilityAccount(string $name, int $tenantId): string
    {
        $name = strtolower($name);

        if (preg_match('/pension|provident|retirement|401k|superannuation|nssf/', $name)) {
            return $this->acct($tenantId, 'pension_payable');
        }
        if (preg_match('/insurance|health|medical|nhif|hmo/', $name)) {
            return $this->acct($tenantId, 'insurance_payable');
        }
        if (preg_match('/worker|comp|wc\\b|wcf/', $name)) {
            return $this->acct($tenantId, 'payroll_liabilities');
        }

        return $this->acct($tenantId, 'payroll_liabilities');
    }

    /**
     * Create journal entry for an invoice (Accounts Receivable)
     *
     * Debit: Accounts Receivable (asset increases)
     * Credit: Sales Revenue (income increases)
     * Credit: Sales Tax Payable (liability increases) - if applicable
     *
     * For inventory items (Perpetual Inventory Method):
     * Debit: Cost of Goods Sold (expense increases)
     * Credit: Inventory (asset decreases)
     */
    public function createInvoiceJournal(Invoice $invoice): ?Journal
    {
        if ($invoice->status === 'cancelled') {
            // A cancelled invoice keeps its original journal plus a reversal (C5),
            // and the stock it took goes back into its cost layers.
            $this->reverseDocumentJournal(Invoice::class, $invoice->id, 'Invoice cancelled');
            $this->returnDocumentStock(Invoice::class, $invoice->id);

            return null;
        }

        if ($invoice->total <= 0) {
            return null;
        }

        return DB::transaction(function () use ($invoice) {
            $t = $invoice->tenant_id;

            // Check if journal already exists for this invoice
            $existingJournal = Journal::where('reference_type', Invoice::class)
                ->where('reference_id', $invoice->id)
                ->first();

            if ($existingJournal) {
                // Update existing journal
                return $this->updateInvoiceJournal($invoice, $existingJournal);
            }

            $journal = Journal::create([
                'tenant_id' => $invoice->tenant_id,
                'journal_number' => Journal::generateNumber($invoice->tenant_id),
                'journal_date' => $invoice->invoice_date,
                'reference' => $invoice->invoice_number,
                'description' => "Invoice {$invoice->invoice_number} - {$invoice->customer->name}",
                'reference_type' => Invoice::class,
                'reference_id' => $invoice->id,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => $invoice->created_by ?? auth()->id(),
            ]);

            // Debit: Accounts Receivable
            $this->createEntry($journal, $this->acct($t, 'accounts_receivable'), $invoice->total, 0,
                "Invoice {$invoice->invoice_number} - {$invoice->customer->name}");

            // Credit: Sales Revenue (subtotal less discount)
            $revenueAmount = $invoice->subtotal - ($invoice->discount_amount ?? 0);
            if ($revenueAmount > 0) {
                $this->createEntry($journal, $this->acct($t, 'sales_revenue'), 0, $revenueAmount,
                    "Sales - Invoice {$invoice->invoice_number}");
            }

            // Credit: Sales Tax Payable (if tax amount exists)
            if ($invoice->tax_amount > 0) {
                $this->createEntry($journal, $this->acct($t, 'sales_tax_payable'), 0, $invoice->tax_amount,
                    "Tax - Invoice {$invoice->invoice_number}");
            }

            // Record Cost of Goods Sold for inventory items (Perpetual Inventory Method)
            $cogsAmount = $this->calculateCOGS($invoice);
            if ($cogsAmount > 0) {
                // Debit: Cost of Goods Sold (expense)
                $this->createEntry($journal, $this->acct($t, 'cost_of_goods_sold'), $cogsAmount, 0,
                    "COGS - Invoice {$invoice->invoice_number}");
                // Credit: Inventory (reduce inventory asset)
                $this->createEntry($journal, $this->acct($t, 'inventory'), 0, $cogsAmount,
                    "Inventory Sold - Invoice {$invoice->invoice_number}");
            }

            $journal->updateTotals();
            $journal->save();

            $this->updateAccountBalances($journal);

            return $journal;
        });
    }

    /**
     * Calculate Cost of Goods Sold for an invoice using stock valuation service.
     */
    protected function calculateCOGS(Invoice $invoice): float
    {
        return $this->documentCogs($invoice, Invoice::class, false);
    }

    /**
     * Update existing invoice journal when invoice is modified
     */
    protected function updateInvoiceJournal(Invoice $invoice, Journal $journal): Journal
    {
        $t = $invoice->tenant_id;

        // Delete old entries
        $this->reverseAccountBalances($journal);
        $journal->entries()->delete();

        // Update journal details
        $journal->update([
            'journal_date' => $invoice->invoice_date,
            'reference' => $invoice->invoice_number,
            'description' => "Invoice {$invoice->invoice_number} - {$invoice->customer->name}",
        ]);

        // Recreate entries
        $this->createEntry($journal, $this->acct($t, 'accounts_receivable'), $invoice->total, 0,
            "Invoice {$invoice->invoice_number} - {$invoice->customer->name}");

        $revenueAmount = $invoice->subtotal - ($invoice->discount_amount ?? 0);
        if ($revenueAmount > 0) {
            $this->createEntry($journal, $this->acct($t, 'sales_revenue'), 0, $revenueAmount,
                "Sales - Invoice {$invoice->invoice_number}");
        }

        if ($invoice->tax_amount > 0) {
            $this->createEntry($journal, $this->acct($t, 'sales_tax_payable'), 0, $invoice->tax_amount,
                "Tax - Invoice {$invoice->invoice_number}");
        }

        // Record Cost of Goods Sold for inventory items
        $cogsAmount = $this->calculateCOGS($invoice);
        if ($cogsAmount > 0) {
            $this->createEntry($journal, $this->acct($t, 'cost_of_goods_sold'), $cogsAmount, 0,
                "COGS - Invoice {$invoice->invoice_number}");
            $this->createEntry($journal, $this->acct($t, 'inventory'), 0, $cogsAmount,
                "Inventory Sold - Invoice {$invoice->invoice_number}");
        }

        $journal->updateTotals();
        $journal->save();

        $this->updateAccountBalances($journal);

        return $journal;
    }

    /**
     * Create journal entry for a bill (Accounts Payable)
     *
     * Debit: Expense Account or Inventory (depending on items)
     * Credit: Accounts Payable (liability increases)
     */
    public function createBillJournal(Bill $bill): ?Journal
    {
        if ($bill->status === 'cancelled') {
            $this->reverseDocumentJournal(Bill::class, $bill->id, 'Bill cancelled');

            return null;
        }

        if ($bill->total <= 0) {
            return null;
        }

        return DB::transaction(function () use ($bill) {
            $t = $bill->tenant_id;

            $existingJournal = Journal::where('reference_type', Bill::class)
                ->where('reference_id', $bill->id)
                ->first();

            if ($existingJournal) {
                return $this->updateBillJournal($bill, $existingJournal);
            }

            $journal = Journal::create([
                'tenant_id' => $bill->tenant_id,
                'journal_number' => Journal::generateNumber($bill->tenant_id),
                'journal_date' => $bill->bill_date,
                'reference' => $bill->bill_number,
                'description' => "Bill {$bill->bill_number} - {$bill->vendor->name}",
                'reference_type' => Bill::class,
                'reference_id' => $bill->id,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => $bill->created_by ?? auth()->id(),
            ]);

            // Debit: Inventory (for items that track inventory) or General Expense
            [$inventoryAmount, $expenseAmount] = $this->billDebitSplit($bill);

            if ($inventoryAmount > 0) {
                $this->createEntry($journal, $this->acct($t, 'inventory'), $inventoryAmount, 0,
                    "Inventory Purchase - Bill {$bill->bill_number}");
            }

            if ($expenseAmount > 0) {
                // Use a general expense account or could be more specific based on item categories
                $this->createEntry($journal, $this->acct($t, 'miscellaneous_expense'), $expenseAmount, 0,
                    "Expense - Bill {$bill->bill_number}");
            }

            // If no items or all have zero totals, debit inventory by default
            if ($inventoryAmount == 0 && $expenseAmount == 0 && $bill->subtotal > 0) {
                $this->createEntry($journal, $this->acct($t, 'inventory'), round((float) $bill->subtotal - (float) ($bill->discount_amount ?? 0), 2), 0,
                    "Purchase - Bill {$bill->bill_number}");
            }

            // Debit: Tax if applicable (Input VAT is typically an asset)
            if ($bill->tax_amount > 0) {
                $this->createEntry($journal, $this->acct($t, 'input_vat'), $bill->tax_amount, 0,
                    "Input VAT - Bill {$bill->bill_number}"); // own account, not Prepaid Expenses (A5)
            }

            // Credit: Accounts Payable
            $this->createEntry($journal, $this->acct($t, 'accounts_payable'), 0, $bill->total,
                "Bill {$bill->bill_number} - {$bill->vendor->name}");

            $journal->updateTotals();
            $journal->save();

            $this->updateAccountBalances($journal);

            return $journal;
        });
    }

    /**
     * Update existing bill journal when bill is modified
     */
    protected function updateBillJournal(Bill $bill, Journal $journal): Journal
    {
        $t = $bill->tenant_id;

        $this->reverseAccountBalances($journal);
        $journal->entries()->delete();

        $journal->update([
            'journal_date' => $bill->bill_date,
            'reference' => $bill->bill_number,
            'description' => "Bill {$bill->bill_number} - {$bill->vendor->name}",
        ]);

        [$inventoryAmount, $expenseAmount] = $this->billDebitSplit($bill);

        if ($inventoryAmount > 0) {
            $this->createEntry($journal, $this->acct($t, 'inventory'), $inventoryAmount, 0,
                "Inventory Purchase - Bill {$bill->bill_number}");
        }

        if ($expenseAmount > 0) {
            $this->createEntry($journal, $this->acct($t, 'miscellaneous_expense'), $expenseAmount, 0,
                "Expense - Bill {$bill->bill_number}");
        }

        if ($inventoryAmount == 0 && $expenseAmount == 0 && $bill->subtotal > 0) {
            $this->createEntry($journal, $this->acct($t, 'inventory'), round((float) $bill->subtotal - (float) ($bill->discount_amount ?? 0), 2), 0,
                "Purchase - Bill {$bill->bill_number}");
        }

        if ($bill->tax_amount > 0) {
            $this->createEntry($journal, $this->acct($t, 'input_vat'), $bill->tax_amount, 0,
                "Input Tax - Bill {$bill->bill_number}");
        }

        $this->createEntry($journal, $this->acct($t, 'accounts_payable'), 0, $bill->total,
            "Bill {$bill->bill_number} - {$bill->vendor->name}");

        $journal->updateTotals();
        $journal->save();

        $this->updateAccountBalances($journal);

        return $journal;
    }

    /**
     * Create journal entry for an expense
     *
     * Debit: Expense Account (expense increases)
     * Credit: Cash/Bank Account (asset decreases)
     */
    public function createExpenseJournal(Expense $expense): ?Journal
    {
        if ($expense->total <= 0) {
            return null;
        }

        return DB::transaction(function () use ($expense) {
            $t = $expense->tenant_id;

            $existingJournal = Journal::where('reference_type', Expense::class)
                ->where('reference_id', $expense->id)
                ->first();

            if ($existingJournal) {
                return $this->updateExpenseJournal($expense, $existingJournal);
            }

            $journal = Journal::create([
                'tenant_id' => $expense->tenant_id,
                'journal_number' => Journal::generateNumber($expense->tenant_id),
                'journal_date' => $expense->expense_date,
                'reference' => $expense->expense_number,
                'description' => "Expense {$expense->expense_number} - {$expense->name}",
                'reference_type' => Expense::class,
                'reference_id' => $expense->id,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => $expense->created_by ?? auth()->id(),
            ]);

            // Debit: Expense Account (use the expense's assigned account)
            $expenseAccountCode = $expense->expenseAccount?->account_code ?? $this->acct($t, 'miscellaneous_expense');
            $this->createEntry($journal, $expenseAccountCode, $expense->amount, 0,
                "Expense - {$expense->name}");

            // Debit: Input Tax (if applicable)
            if ($expense->tax_amount > 0) {
                $this->createEntry($journal, $this->acct($t, 'input_vat'), $expense->tax_amount, 0,
                    "Input Tax - {$expense->expense_number}");
            }

            // Credit: Payment Account (cash, bank, etc.)
            $paymentAccountCode = $expense->paidThroughAccount?->account_code
                ?? $this->paymentAccountFor($expense->bank, $expense->payment_method, $t);
            $this->createEntry($journal, $paymentAccountCode, 0, $expense->total,
                "Payment - {$expense->expense_number}");

            $journal->updateTotals();
            $journal->save();

            $this->updateAccountBalances($journal);

            return $journal;
        });
    }

    /**
     * Update existing expense journal
     */
    protected function updateExpenseJournal(Expense $expense, Journal $journal): Journal
    {
        $t = $expense->tenant_id;

        $this->reverseAccountBalances($journal);
        $journal->entries()->delete();

        $journal->update([
            'journal_date' => $expense->expense_date,
            'reference' => $expense->expense_number,
            'description' => "Expense {$expense->expense_number} - {$expense->name}",
        ]);

        $expenseAccountCode = $expense->expenseAccount?->account_code ?? $this->acct($t, 'miscellaneous_expense');
        $this->createEntry($journal, $expenseAccountCode, $expense->amount, 0,
            "Expense - {$expense->name}");

        if ($expense->tax_amount > 0) {
            $this->createEntry($journal, $this->acct($t, 'input_vat'), $expense->tax_amount, 0,
                "Input Tax - {$expense->expense_number}");
        }

        $paymentAccountCode = $expense->paidThroughAccount?->account_code
            ?? $this->paymentAccountFor($expense->bank, $expense->payment_method, $t);
        $this->createEntry($journal, $paymentAccountCode, 0, $expense->total,
            "Payment - {$expense->expense_number}");

        $journal->updateTotals();
        $journal->save();

        $this->updateAccountBalances($journal);

        return $journal;
    }

    /**
     * Create journal entry for payment received (customer payment on invoice)
     *
     * For regular payments:
     * Debit: Cash/Bank (asset increases)
     * Credit: Accounts Receivable (asset decreases)
     *
     * For customer deposits:
     * Debit: Cash/Bank (asset increases)
     * Credit: Customer Deposits (liability increases)
     *
     * For payments from deposit:
     * Debit: Customer Deposits (liability decreases)
     * Credit: Accounts Receivable (asset decreases)
     */
    public function createPaymentReceivedJournal(PaymentReceived $payment): ?Journal
    {
        if ($payment->amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($payment) {
            $t = $payment->tenant_id;

            $existingJournal = Journal::where('reference_type', PaymentReceived::class)
                ->where('reference_id', $payment->id)
                ->first();

            if ($existingJournal) {
                return $this->updatePaymentReceivedJournal($payment, $existingJournal);
            }

            // Build description based on payment type
            if ($payment->is_deposit) {
                $description = "Customer Deposit {$payment->payment_number} - {$payment->customer->name}";
            } elseif ($payment->payment_method === 'deposit') {
                $invoiceRef = $payment->invoice ? " for Invoice {$payment->invoice->invoice_number}" : '';
                $description = "Deposit Applied {$payment->payment_number} - {$payment->customer->name}{$invoiceRef}";
            } else {
                $invoiceRef = $payment->invoice ? " for Invoice {$payment->invoice->invoice_number}" : '';
                $description = "Payment Received {$payment->payment_number} - {$payment->customer->name}{$invoiceRef}";
            }

            $journal = Journal::create([
                'tenant_id' => $payment->tenant_id,
                'journal_number' => Journal::generateNumber($payment->tenant_id),
                'journal_date' => $payment->payment_date,
                'reference' => $payment->payment_number,
                'description' => $description,
                'reference_type' => PaymentReceived::class,
                'reference_id' => $payment->id,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => $payment->created_by ?? auth()->id(),
            ]);

            if ($payment->is_deposit) {
                // Customer deposit: Debit Cash, Credit Customer Deposits (liability)
                $paymentAccountCode = $this->paymentAccountFor($payment->bank, $payment->payment_method, $t);
                $this->createEntry($journal, $paymentAccountCode, $payment->amount, 0,
                    "Customer Deposit - {$payment->payment_number}");

                $this->createEntry($journal, $this->acct($t, 'customer_deposits'), 0, $payment->amount,
                    "Customer Deposit from {$payment->customer->name}");
            } elseif ($payment->payment_method === 'deposit') {
                // Payment from deposit: Debit Customer Deposits (reduce liability), Credit A/R
                $this->createEntry($journal, $this->acct($t, 'customer_deposits'), $payment->amount, 0,
                    "Deposit Applied - {$payment->payment_number}");

                $this->createEntry($journal, $this->acct($t, 'accounts_receivable'), 0, $payment->amount,
                    "Payment for {$payment->customer->name}");
            } else {
                $this->writeRegularPaymentReceivedLines($journal, $payment);
            }

            $journal->updateTotals();
            $journal->save();

            $this->updateAccountBalances($journal);

            return $journal;
        });
    }

    /**
     * Regular payment: Dr the bank with the money received, Dr WHT Credit
     * Notes Receivable with any WHT the customer deducted, Cr Accounts
     * Receivable with what the payment settles.
     */
    protected function writeRegularPaymentReceivedLines(Journal $journal, PaymentReceived $payment): void
    {
        $t = $payment->tenant_id;
        $wht = round((float) $payment->wht_amount, 2);

        $paymentAccountCode = $this->paymentAccountFor($payment->bank, $payment->payment_method, $t);
        $this->createEntry($journal, $paymentAccountCode, $payment->amount, 0,
            "Payment Received - {$payment->payment_number}");

        if ($wht > 0) {
            $this->createEntry($journal, $this->acct($t, 'wht_receivable'), $wht, 0,
                "WHT deducted by {$payment->customer->name} - {$payment->payment_number}");
        }

        $this->createEntry($journal, $this->acct($t, 'accounts_receivable'), 0, $payment->settledAmount(),
            "Payment for {$payment->customer->name}");
    }

    /**
     * WHT credit notes used against income tax:
     * Dr Income Tax Payable, Cr WHT Credit Notes Receivable.
     */
    public function createWhtUtilisationJournal(WhtCreditUtilisation $utilisation): Journal
    {
        return DB::transaction(function () use ($utilisation) {
            $t = $utilisation->tenant_id;
            $journal = Journal::create([
                'tenant_id' => $t,
                'journal_number' => Journal::generateNumber($t),
                'journal_date' => $utilisation->utilisation_date,
                'reference' => $utilisation->reference ?: 'WHT-CREDIT-'.$utilisation->id,
                'description' => 'WHT credit notes used against income tax',
                'reference_type' => WhtCreditUtilisation::class,
                'reference_id' => $utilisation->id,
                'journal_type' => self::WHT_CREDIT_UTILISATION,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => $utilisation->created_by ?? auth()->id(),
            ]);

            $amount = round((float) $utilisation->amount, 2);
            $this->createEntry($journal, $this->acct($t, 'income_tax_payable'), $amount, 0, 'Income tax settled with WHT credit notes');
            $this->createEntry($journal, $this->acct($t, 'wht_receivable'), 0, $amount, 'WHT credit notes used');

            $journal->updateTotals();
            $journal->save();
            $this->updateAccountBalances($journal);

            return $journal;
        });
    }

    /**
     * Update existing payment received journal
     */
    protected function updatePaymentReceivedJournal(PaymentReceived $payment, Journal $journal): Journal
    {
        $t = $payment->tenant_id;

        $this->reverseAccountBalances($journal);
        $journal->entries()->delete();

        // Build description based on payment type
        if ($payment->is_deposit) {
            $description = "Customer Deposit {$payment->payment_number} - {$payment->customer->name}";
        } elseif ($payment->payment_method === 'deposit') {
            $invoiceRef = $payment->invoice ? " for Invoice {$payment->invoice->invoice_number}" : '';
            $description = "Deposit Applied {$payment->payment_number} - {$payment->customer->name}{$invoiceRef}";
        } else {
            $invoiceRef = $payment->invoice ? " for Invoice {$payment->invoice->invoice_number}" : '';
            $description = "Payment Received {$payment->payment_number} - {$payment->customer->name}{$invoiceRef}";
        }

        $journal->update([
            'journal_date' => $payment->payment_date,
            'reference' => $payment->payment_number,
            'description' => $description,
        ]);

        if ($payment->is_deposit) {
            // Customer deposit: Debit Cash, Credit Customer Deposits (liability)
            $paymentAccountCode = $this->paymentAccountFor($payment->bank, $payment->payment_method, $t);
            $this->createEntry($journal, $paymentAccountCode, $payment->amount, 0,
                "Customer Deposit - {$payment->payment_number}");

            $this->createEntry($journal, $this->acct($t, 'customer_deposits'), 0, $payment->amount,
                "Customer Deposit from {$payment->customer->name}");
        } elseif ($payment->payment_method === 'deposit') {
            // Payment from deposit: Debit Customer Deposits (reduce liability), Credit A/R
            $this->createEntry($journal, $this->acct($t, 'customer_deposits'), $payment->amount, 0,
                "Deposit Applied - {$payment->payment_number}");

            $this->createEntry($journal, $this->acct($t, 'accounts_receivable'), 0, $payment->amount,
                "Payment for {$payment->customer->name}");
        } else {
            $this->writeRegularPaymentReceivedLines($journal, $payment);
        }

        $journal->updateTotals();
        $journal->save();

        $this->updateAccountBalances($journal);

        return $journal;
    }

    /**
     * Create journal entry for invoice refund
     *
     * Reverses the original invoice and payment entries:
     * Debit: Sales Revenue (income decreases - reduces revenue)
     * Debit: Sales Tax Payable (liability decreases - reduces tax liability, if applicable)
     * Credit: Cash/Bank (asset decreases - money going out)
     *
     * This properly reflects that we're giving money back to the customer
     * and reducing our recorded revenue.
     */
    public function createRefundJournal(InvoiceRefund $refund): ?Journal
    {
        if ($refund->amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($refund) {
            $t = $refund->tenant_id;

            $existingJournal = Journal::where('reference_type', InvoiceRefund::class)
                ->where('reference_id', $refund->id)
                ->first();

            if ($existingJournal) {
                return $this->updateRefundJournal($refund, $existingJournal);
            }

            $invoice = $refund->invoice;
            $description = "Refund {$refund->refund_number} - Invoice {$invoice->invoice_number} - {$refund->customer->name}";

            $journal = Journal::create([
                'tenant_id' => $refund->tenant_id,
                'journal_number' => Journal::generateNumber($refund->tenant_id),
                'journal_date' => $refund->refund_date,
                'reference' => $refund->refund_number,
                'description' => $description,
                'reference_type' => InvoiceRefund::class,
                'reference_id' => $refund->id,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => $refund->created_by ?? auth()->id(),
            ]);

            // Calculate proportional amounts based on refund percentage
            $refundPercentage = $refund->amount / max($invoice->total, 0.01);
            $revenueRefund = ($invoice->subtotal - ($invoice->discount_amount ?? 0)) * $refundPercentage;
            $taxRefund = ($invoice->tax_amount ?? 0) * $refundPercentage;

            // Debit: Sales Revenue (reduce income)
            if ($revenueRefund > 0) {
                $this->createEntry($journal, $this->acct($t, 'sales_revenue'), $revenueRefund, 0,
                    "Sales Refund - {$refund->refund_number}");
            }

            // Debit: Sales Tax Payable (reduce tax liability) - if there was tax
            if ($taxRefund > 0) {
                $this->createEntry($journal, $this->acct($t, 'sales_tax_payable'), $taxRefund, 0,
                    "Tax Refund - {$refund->refund_number}");
            }

            // Credit: Payment Account (cash/bank going out)
            $paymentAccountCode = $this->getPaymentAccountCode($refund->refund_method, $t);
            $this->createEntry($journal, $paymentAccountCode, 0, $refund->amount,
                "Refund Payment - {$refund->refund_number}");

            $journal->updateTotals();
            $journal->save();

            $this->updateAccountBalances($journal);

            return $journal;
        });
    }

    /**
     * Update existing refund journal
     */
    protected function updateRefundJournal(InvoiceRefund $refund, Journal $journal): Journal
    {
        $t = $refund->tenant_id;

        $this->reverseAccountBalances($journal);
        $journal->entries()->delete();

        $invoice = $refund->invoice;
        $description = "Refund {$refund->refund_number} - Invoice {$invoice->invoice_number} - {$refund->customer->name}";

        $journal->update([
            'journal_date' => $refund->refund_date,
            'reference' => $refund->refund_number,
            'description' => $description,
        ]);

        // Calculate proportional amounts
        $refundPercentage = $refund->amount / max($invoice->total, 0.01);
        $revenueRefund = ($invoice->subtotal - ($invoice->discount_amount ?? 0)) * $refundPercentage;
        $taxRefund = ($invoice->tax_amount ?? 0) * $refundPercentage;

        if ($revenueRefund > 0) {
            $this->createEntry($journal, $this->acct($t, 'sales_revenue'), $revenueRefund, 0,
                "Sales Refund - {$refund->refund_number}");
        }

        if ($taxRefund > 0) {
            $this->createEntry($journal, $this->acct($t, 'sales_tax_payable'), $taxRefund, 0,
                "Tax Refund - {$refund->refund_number}");
        }

        $paymentAccountCode = $this->getPaymentAccountCode($refund->refund_method, $t);
        $this->createEntry($journal, $paymentAccountCode, 0, $refund->amount,
            "Refund Payment - {$refund->refund_number}");

        $journal->updateTotals();
        $journal->save();

        $this->updateAccountBalances($journal);

        return $journal;
    }

    /**
     * Create journal entry for payment made (vendor payment on bill)
     *
     * Debit: Accounts Payable (liability decreases)
     * Credit: Cash/Bank (asset decreases)
     * Credit: WHT Payable (WHT withheld, owed to the tax authority)
     */
    public function createPaymentMadeJournal(PaymentMade $payment): ?Journal
    {
        if ($payment->amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($payment) {
            $existingJournal = Journal::where('reference_type', PaymentMade::class)
                ->where('reference_id', $payment->id)
                ->first();

            if ($existingJournal) {
                return $this->updatePaymentMadeJournal($payment, $existingJournal);
            }

            $billRef = $payment->bill ? " for Bill {$payment->bill->bill_number}" : '';

            $journal = Journal::create([
                'tenant_id' => $payment->tenant_id,
                'journal_number' => Journal::generateNumber($payment->tenant_id),
                'journal_date' => $payment->payment_date,
                'reference' => $payment->payment_number,
                'description' => "Payment Made {$payment->payment_number} - {$payment->vendor->name}{$billRef}",
                'reference_type' => PaymentMade::class,
                'reference_id' => $payment->id,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => $payment->created_by ?? auth()->id(),
            ]);

            $this->writePaymentMadeLines($journal, $payment);

            $journal->updateTotals();
            $journal->save();

            $this->updateAccountBalances($journal);

            return $journal;
        });
    }

    /**
     * Dr Accounts Payable with what the payment settles, Cr the bank with
     * the money paid, and Cr WHT Payable with any WHT withheld.
     */
    protected function writePaymentMadeLines(Journal $journal, PaymentMade $payment): void
    {
        $t = $payment->tenant_id;
        $wht = round((float) $payment->wht_amount, 2);

        $this->createEntry($journal, $this->acct($t, 'accounts_payable'), $payment->settledAmount(), 0,
            "Payment to {$payment->vendor->name}");

        $paymentAccountCode = $this->paymentAccountFor($payment->bank, $payment->payment_method, $t);
        $this->createEntry($journal, $paymentAccountCode, 0, $payment->amount,
            "Payment Made - {$payment->payment_number}");

        if ($wht > 0) {
            $this->createEntry($journal, $this->acct($t, 'wht_payable'), 0, $wht,
                "WHT withheld from {$payment->vendor->name} - {$payment->payment_number}");
        }
    }

    /**
     * Update existing payment made journal
     */
    protected function updatePaymentMadeJournal(PaymentMade $payment, Journal $journal): Journal
    {
        $this->reverseAccountBalances($journal);
        $journal->entries()->delete();

        $billRef = $payment->bill ? " for Bill {$payment->bill->bill_number}" : '';

        $journal->update([
            'journal_date' => $payment->payment_date,
            'reference' => $payment->payment_number,
            'description' => "Payment Made {$payment->payment_number} - {$payment->vendor->name}{$billRef}",
        ]);

        $this->writePaymentMadeLines($journal, $payment);

        $journal->updateTotals();
        $journal->save();

        $this->updateAccountBalances($journal);

        return $journal;
    }

    /**
     * Create journal entry for a sales receipt (cash sale)
     *
     * Debit: Cash/Bank (asset increases - based on payment method)
     * Credit: Sales Revenue (income increases)
     * Credit: Sales Tax Payable (liability increases) - if applicable
     *
     * For inventory items (Perpetual Inventory Method):
     * Debit: Cost of Goods Sold (expense increases)
     * Credit: Inventory (asset decreases)
     */
    public function createSalesReceiptJournal(SalesReceipt $receipt): ?Journal
    {
        if ($receipt->total <= 0) {
            return null;
        }

        return DB::transaction(function () use ($receipt) {
            $t = $receipt->tenant_id;

            $existingJournal = Journal::where('reference_type', SalesReceipt::class)
                ->where('reference_id', $receipt->id)
                ->first();

            if ($existingJournal) {
                return $this->updateSalesReceiptJournal($receipt, $existingJournal);
            }

            $customerName = $receipt->customer ? $receipt->customer->name : 'Walk-in Customer';

            $journal = Journal::create([
                'tenant_id' => $receipt->tenant_id,
                'journal_number' => Journal::generateNumber($receipt->tenant_id),
                'journal_date' => $receipt->receipt_date,
                'reference' => $receipt->receipt_number,
                'description' => "Sales Receipt {$receipt->receipt_number} - {$customerName}",
                'reference_type' => SalesReceipt::class,
                'reference_id' => $receipt->id,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => $receipt->created_by ?? auth()->id(),
            ]);

            // Debit: Cash/Bank (based on payment method)
            $paymentAccountCode = $this->getPaymentAccountCode($receipt->payment_method ?? 'cash', $t);
            $this->createEntry($journal, $paymentAccountCode, $receipt->total, 0,
                "Cash Sale - {$receipt->receipt_number}");

            // Credit: Sales Revenue (subtotal less discount)
            $revenueAmount = $receipt->subtotal - ($receipt->discount_amount ?? 0);
            if ($revenueAmount > 0) {
                $this->createEntry($journal, $this->acct($t, 'sales_revenue'), 0, $revenueAmount,
                    "Sales - Receipt {$receipt->receipt_number}");
            }

            // Credit: Sales Tax Payable (if tax amount exists)
            if ($receipt->tax_amount > 0) {
                $this->createEntry($journal, $this->acct($t, 'sales_tax_payable'), 0, $receipt->tax_amount,
                    "Tax - Receipt {$receipt->receipt_number}");
            }

            // Record Cost of Goods Sold for inventory items
            $cogsAmount = $this->calculateSalesReceiptCOGS($receipt);
            if ($cogsAmount > 0) {
                $this->createEntry($journal, $this->acct($t, 'cost_of_goods_sold'), $cogsAmount, 0,
                    "COGS - Receipt {$receipt->receipt_number}");
                $this->createEntry($journal, $this->acct($t, 'inventory'), 0, $cogsAmount,
                    "Inventory Sold - Receipt {$receipt->receipt_number}");
            }

            $journal->updateTotals();
            $journal->save();

            $this->updateAccountBalances($journal);

            return $journal;
        });
    }

    /**
     * Calculate COGS for a sales receipt using stock valuation service.
     */
    protected function calculateSalesReceiptCOGS(SalesReceipt $receipt): float
    {
        // A cash sale hands the goods over now, so it also lowers stock on hand.
        return $this->documentCogs($receipt, SalesReceipt::class, true);
    }

    /**
     * Cost of goods sold for an invoice or sales receipt (findings M3, N6).
     *
     * Each stock line's cost is decided once, when the document is first
     * posted, and stored on the line (unit_cost). Later rebuilds of the
     * journal (payments, status changes) reuse the stored cost, so changing
     * an item's cost price no longer rewrites past COGS.
     *
     * If a line has no stored cost (first posting, or the lines were
     * replaced by an edit), everything the document took is put back and all
     * its lines are costed again.
     */
    protected function documentCogs($document, string $documentType, bool $reduceOnHand): float
    {
        $lines = $document->items()->with('item')->get();
        $stockLines = $lines->filter(fn ($line) => $line->item && $line->item->track_inventory && (float) $line->quantity > 0);

        if ($stockLines->contains(fn ($line) => $line->unit_cost === null)) {
            $valuation = app(StockValuationService::class);
            $valuation->returnStock($documentType, $document->id);

            foreach ($stockLines as $line) {
                $cost = $valuation->issue($line->item, (float) $line->quantity, $documentType, $document->id, $reduceOnHand);
                $line->forceFill(['unit_cost' => round($cost / (float) $line->quantity, 4)])->saveQuietly();
            }
        }

        $cogs = 0.0;
        foreach ($stockLines as $line) {
            $cogs += round((float) $line->unit_cost * (float) $line->quantity, 2);
        }

        return round($cogs, 2);
    }

    /**
     * Put back the stock a sales document took (when it is cancelled or deleted).
     */
    protected function returnDocumentStock(string $referenceType, int $referenceId): void
    {
        if (in_array($referenceType, [Invoice::class, SalesReceipt::class], true)) {
            app(StockValuationService::class)->returnStock($referenceType, $referenceId);
        }
    }

    /**
     * Update existing sales receipt journal when receipt is modified
     */
    protected function updateSalesReceiptJournal(SalesReceipt $receipt, Journal $journal): Journal
    {
        $t = $receipt->tenant_id;

        $this->reverseAccountBalances($journal);
        $journal->entries()->delete();

        $customerName = $receipt->customer ? $receipt->customer->name : 'Walk-in Customer';

        $journal->update([
            'journal_date' => $receipt->receipt_date,
            'reference' => $receipt->receipt_number,
            'description' => "Sales Receipt {$receipt->receipt_number} - {$customerName}",
        ]);

        $paymentAccountCode = $this->getPaymentAccountCode($receipt->payment_method ?? 'cash', $t);
        $this->createEntry($journal, $paymentAccountCode, $receipt->total, 0,
            "Cash Sale - {$receipt->receipt_number}");

        $revenueAmount = $receipt->subtotal - ($receipt->discount_amount ?? 0);
        if ($revenueAmount > 0) {
            $this->createEntry($journal, $this->acct($t, 'sales_revenue'), 0, $revenueAmount,
                "Sales - Receipt {$receipt->receipt_number}");
        }

        if ($receipt->tax_amount > 0) {
            $this->createEntry($journal, $this->acct($t, 'sales_tax_payable'), 0, $receipt->tax_amount,
                "Tax - Receipt {$receipt->receipt_number}");
        }

        $cogsAmount = $this->calculateSalesReceiptCOGS($receipt);
        if ($cogsAmount > 0) {
            $this->createEntry($journal, $this->acct($t, 'cost_of_goods_sold'), $cogsAmount, 0,
                "COGS - Receipt {$receipt->receipt_number}");
            $this->createEntry($journal, $this->acct($t, 'inventory'), 0, $cogsAmount,
                "Inventory Sold - Receipt {$receipt->receipt_number}");
        }

        $journal->updateTotals();
        $journal->save();

        $this->updateAccountBalances($journal);

        return $journal;
    }

    /** Journal types for payroll (finding A10) and employee loans (A9). */
    public const PAYROLL_ACCRUAL = 'payroll_accrual';

    public const PAYROLL_PAYMENT = 'payroll_payment';

    public const PAYROLL_REMITTANCE = 'payroll_remittance';

    public const LOAN_DISBURSEMENT = 'loan_disbursement';

    /** Withholding tax: paying WHT to the authority, and using WHT credits against income tax. */
    public const WHT_REMITTANCE = 'wht_remittance';

    public const WHT_CREDIT_UTILISATION = 'wht_credit_utilisation';

    /**
     * Payroll cost, posted when the payroll is approved (A10):
     *   Dr salaries, allowances, overtime, employer contributions
     *   Cr Accrued Salaries (net pay owed to the employee)
     *   Cr PAYE, pension and other deduction liabilities
     *   Cr Employee Advances for loan repayments, found by the loan link (A9)
     *   Cr employer contribution liabilities
     * Dated the end of the pay period, and refused if that period is locked.
     * Paying the employee is a separate journal (createPayrollPaymentJournal).
     *
     * Payrolls paid before this change have one combined journal (no type)
     * that credited the bank directly; those are left as they are.
     */
    public function createPayrollJournal(Payroll $payroll): ?Journal
    {
        if ((float) $payroll->gross_salary <= 0 && (float) $payroll->net_salary <= 0) {
            return null;
        }

        return DB::transaction(function () use ($payroll) {
            $existing = Journal::where('reference_type', Payroll::class)
                ->where('reference_id', $payroll->id)
                ->where(fn ($q) => $q->whereNull('journal_type')->orWhere('journal_type', self::PAYROLL_ACCRUAL))
                ->where('status', 'posted')
                ->orderBy('id')
                ->first();

            if ($existing) {
                return $this->updatePayrollJournal($payroll, $existing);
            }

            $journal = Journal::create([
                'tenant_id' => $payroll->tenant_id,
                'journal_number' => Journal::generateNumber($payroll->tenant_id),
                'journal_date' => $payroll->pay_period_end ?? $payroll->pay_date ?? now(),
                'reference' => $payroll->payroll_number,
                'description' => "Payroll {$payroll->payroll_number} - {$payroll->employee?->full_name}",
                'reference_type' => Payroll::class,
                'reference_id' => $payroll->id,
                'journal_type' => self::PAYROLL_ACCRUAL,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => $payroll->created_by ?? auth()->id(),
            ]); // period check applies: no posting into a locked month (A10)

            $this->writePayrollLines($journal, $payroll, $this->acct($payroll->tenant_id, 'accrued_salaries'));

            $journal->updateTotals();
            $journal->save();
            $this->updateAccountBalances($journal);

            return $journal;
        });
    }

    /**
     * Rebuild a payroll's cost journal after the payroll changed. An old
     * combined journal (no type) keeps crediting the bank for net pay.
     */
    protected function updatePayrollJournal(Payroll $payroll, Journal $journal): Journal
    {
        $this->reverseAccountBalances($journal);
        $journal->entries()->delete();

        $journal->fill([
            'journal_date' => $journal->journal_type === self::PAYROLL_ACCRUAL
                ? ($payroll->pay_period_end ?? $payroll->pay_date ?? now())
                : ($payroll->pay_date ?? now()),
            'reference' => $payroll->payroll_number,
            'description' => "Payroll {$payroll->payroll_number} - {$payroll->employee?->full_name}",
        ]);
        $journal->withoutPeriodValidation()->save();

        $netAccount = $journal->journal_type === self::PAYROLL_ACCRUAL
            ? $this->acct($payroll->tenant_id, 'accrued_salaries')
            : $this->getPaymentAccountCode($payroll->payment_method ?? 'bank_transfer', $payroll->tenant_id);
        $this->writePayrollLines($journal, $payroll, $netAccount);

        $journal->updateTotals();
        $journal->withoutPeriodValidation()->save();
        $this->updateAccountBalances($journal);

        return $journal;
    }

    /**
     * Paying net pay (A10): Dr Accrued Salaries, Cr bank. Dated today.
     * Nothing to do for an old combined journal (it already paid the bank)
     * or if this payroll's payment is already posted.
     */
    public function createPayrollPaymentJournal(Payroll $payroll): ?Journal
    {
        if ((float) $payroll->net_salary <= 0) {
            return null;
        }

        $journals = Journal::where('reference_type', Payroll::class)
            ->where('reference_id', $payroll->id)
            ->where('status', 'posted')
            ->get();
        if ($journals->contains(fn ($j) => $j->journal_type === null && ! str_starts_with((string) $j->reference, 'REV-'))) {
            return null;
        }
        if ($existing = $journals->firstWhere('journal_type', self::PAYROLL_PAYMENT)) {
            return $existing;
        }

        return DB::transaction(function () use ($payroll) {
            $t = $payroll->tenant_id;
            $journal = Journal::create([
                'tenant_id' => $t,
                'journal_number' => Journal::generateNumber($t),
                'journal_date' => now()->toDateString(),
                'reference' => $payroll->payroll_number,
                'description' => "Net pay - Payroll {$payroll->payroll_number} - {$payroll->employee?->full_name}",
                'reference_type' => Payroll::class,
                'reference_id' => $payroll->id,
                'journal_type' => self::PAYROLL_PAYMENT,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => auth()->id() ?? $payroll->created_by,
            ]);

            $this->createEntry($journal, $this->acct($t, 'accrued_salaries'), (float) $payroll->net_salary, 0,
                "Net pay owed - {$payroll->payroll_number}");
            $this->createEntry($journal, $this->getPaymentAccountCode($payroll->payment_method ?? 'bank_transfer', $t), 0, (float) $payroll->net_salary,
                "Net Pay - {$payroll->payroll_number}");

            $journal->updateTotals();
            $journal->save();
            $this->updateAccountBalances($journal);

            return $journal;
        });
    }

    /**
     * Paying a payroll liability to the authority or fund (PAYE to the state,
     * pension to the PFA, NHF, NSITF, ITF...): Dr the liability, Cr bank.
     */
    public function createPayrollRemittanceJournal(int $tenantId, string $liabilityCode, float $amount, string $date, ?string $paymentMethod, ?string $reference): Journal
    {
        return DB::transaction(function () use ($tenantId, $liabilityCode, $amount, $date, $paymentMethod, $reference) {
            $account = ChartOfAccount::where('tenant_id', $tenantId)->where('account_code', $liabilityCode)->firstOrFail();
            $journal = Journal::create([
                'tenant_id' => $tenantId,
                'journal_number' => Journal::generateNumber($tenantId),
                'journal_date' => $date,
                'reference' => $reference ?: 'REMIT-'.$liabilityCode,
                'description' => "Remittance - {$account->name}",
                'journal_type' => self::PAYROLL_REMITTANCE,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => auth()->id(),
            ]);

            $this->createEntry($journal, $liabilityCode, $amount, 0, "Remitted: {$account->name}");
            $this->createEntry($journal, $this->getPaymentAccountCode($paymentMethod ?? 'bank_transfer', $tenantId), 0, $amount, "Remittance: {$account->name}");

            $journal->updateTotals();
            $journal->save();
            $this->updateAccountBalances($journal);

            return $journal;
        });
    }

    /**
     * An employee loan or advance paid out (A9): Dr Employee Advances, Cr bank.
     */
    public function createLoanDisbursementJournal(EmployeeLoan $loan): ?Journal
    {
        $already = Journal::where('reference_type', EmployeeLoan::class)
            ->where('reference_id', $loan->id)
            ->where('journal_type', self::LOAN_DISBURSEMENT)
            ->exists();
        if ($already || (float) $loan->principal_amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($loan) {
            $t = $loan->tenant_id;
            $name = $loan->employee?->full_name;
            $journal = Journal::create([
                'tenant_id' => $t,
                'journal_number' => Journal::generateNumber($t),
                'journal_date' => $loan->disbursement_date ?? now()->toDateString(),
                'reference' => $loan->loan_number,
                'description' => "Loan paid out {$loan->loan_number} - {$name}",
                'reference_type' => EmployeeLoan::class,
                'reference_id' => $loan->id,
                'journal_type' => self::LOAN_DISBURSEMENT,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => $loan->created_by ?? auth()->id(),
            ]);

            $this->createEntry($journal, $this->acct($t, 'employee_advances'), (float) $loan->principal_amount, 0, "Loan {$loan->loan_number} - {$name}");
            $this->createEntry($journal, $this->getPaymentAccountCode('bank_transfer', $t), 0, (float) $loan->principal_amount, "Loan {$loan->loan_number} paid out");

            $journal->updateTotals();
            $journal->save();
            $this->updateAccountBalances($journal);

            return $journal;
        });
    }

    /**
     * The lines of a payroll's cost journal. $netAccount receives net pay:
     * Accrued Salaries for the cost journal, the bank for old combined ones.
     */
    protected function writePayrollLines(Journal $journal, Payroll $payroll, string $netAccount): void
    {
        $t = $payroll->tenant_id;
        $who = $payroll->employee?->full_name;

        if ($payroll->basic_salary > 0) {
            $this->createEntry($journal, $this->acct($t, 'salaries_wages'), (float) $payroll->basic_salary, 0,
                "Basic Salary - {$who} ({$payroll->payroll_number})");
        }
        if ($payroll->allowances > 0) {
            $this->createEntry($journal, $this->acct($t, 'allowances_expense'), (float) $payroll->allowances, 0,
                "Allowances - {$who} ({$payroll->payroll_number})");
        }
        if ($payroll->overtime_amount > 0) {
            $this->createEntry($journal, $this->acct($t, 'overtime_expense'), (float) $payroll->overtime_amount, 0,
                "Overtime - {$who} ({$payroll->payroll_number})");
        }

        $employerContributions = (float) ($payroll->employer_contributions ?? 0);
        $contributionDetails = $payroll->employer_contribution_details ?? [];
        if ($employerContributions > 0) {
            if (! empty($contributionDetails)) {
                foreach ($contributionDetails as $contribution) {
                    $amount = (float) ($contribution['amount'] ?? 0);
                    if ($amount > 0) {
                        $this->createEntry($journal, $this->mapContributionToExpenseAccount($contribution['name'] ?? '', $t), $amount, 0,
                            "{$contribution['name']} - {$payroll->payroll_number}");
                    }
                }
            } else {
                $this->createEntry($journal, $this->acct($t, 'payroll_taxes'), $employerContributions, 0,
                    "Employer Contributions - {$payroll->payroll_number}");
            }
        }

        if ($payroll->net_salary > 0) {
            $this->createEntry($journal, $netAccount, 0, (float) $payroll->net_salary, "Net Pay - {$payroll->payroll_number}");
        }

        if ($payroll->tax_deduction > 0) {
            $this->createEntry($journal, $this->acct($t, 'tax_payable'), 0, (float) $payroll->tax_deduction,
                "Tax Withheld - {$payroll->payroll_number}");
        }

        if ($payroll->other_deductions > 0) {
            $mappedTotal = 0;
            foreach ($payroll->deduction_details ?? [] as $deduction) {
                if (str_starts_with($deduction['name'] ?? '', '_')) {
                    continue;
                }
                $amount = (float) ($deduction['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                if (! empty($deduction['_loan_id']) && ($loan = EmployeeLoan::find($deduction['_loan_id']))) {
                    // Loan repayment: reduces the advance; any interest is income (A9).
                    $interest = $loan->interestPortionFor($amount);
                    $this->createEntry($journal, $this->acct($t, 'employee_advances'), 0, round($amount - $interest, 2),
                        "{$deduction['name']} - {$payroll->payroll_number}");
                    if ($interest > 0) {
                        $this->createEntry($journal, $this->acct($t, 'interest_income'), 0, $interest,
                            "Interest {$loan->loan_number} - {$payroll->payroll_number}");
                    }
                } else {
                    $this->createEntry($journal, $this->mapDeductionToLiabilityAccount($deduction['name'] ?? '', $t), 0, $amount,
                        "{$deduction['name']} - {$payroll->payroll_number}");
                }
                $mappedTotal += $amount;
            }
            $remainder = round($payroll->other_deductions - $mappedTotal, 2);
            if ($remainder > 0) {
                $this->createEntry($journal, $this->acct($t, 'payroll_liabilities'), 0, $remainder,
                    "Other Deductions - {$payroll->payroll_number}");
            }
        }

        if ($employerContributions > 0) {
            if (! empty($contributionDetails)) {
                foreach ($contributionDetails as $contribution) {
                    $amount = (float) ($contribution['amount'] ?? 0);
                    if ($amount > 0) {
                        $this->createEntry($journal, $this->mapContributionToLiabilityAccount($contribution['name'] ?? '', $t), 0, $amount,
                            "{$contribution['name']} Payable - {$payroll->payroll_number}");
                    }
                }
            } else {
                $this->createEntry($journal, $this->acct($t, 'payroll_liabilities'), 0, $employerContributions,
                    "Employer Contributions Payable - {$payroll->payroll_number}");
            }
        }
    }

    public const ASSET_ACQUISITION = 'asset_acquisition';

    public const ASSET_DEPRECIATION = 'asset_depreciation';

    public const ASSET_DISPOSAL = 'asset_disposal';

    /**
     * Ledger accounts for a fixed asset: the category's own accounts when
     * set, otherwise the mapped defaults (finding A11).
     *
     * @return array{asset: string, accumulated: string, expense: string, gain: string, loss: string}
     */
    public function fixedAssetAccounts(FixedAsset $asset): array
    {
        $t = $asset->tenant_id;
        $category = $asset->category;
        $code = fn (?int $id) => $id ? ChartOfAccount::where('tenant_id', $t)->whereKey($id)->value('account_code') : null;

        return [
            'asset' => $code($category?->asset_account_id) ?? $this->acct($t, 'fixed_assets'),
            'accumulated' => $code($category?->accumulated_depreciation_account_id) ?? $this->acct($t, 'accumulated_depreciation'),
            'expense' => $code($category?->depreciation_expense_account_id) ?? $this->acct($t, 'depreciation_expense'),
            'gain' => $code($category?->gain_loss_account_id) ?? $this->acct($t, 'other_income'),
            'loss' => $code($category?->gain_loss_account_id) ?? $this->acct($t, 'miscellaneous_expense'),
        ];
    }

    /**
     * Buying (or registering) an asset (A11): Dr the asset account, Cr
     * according to how it was paid for: bank, cash, the vendor (accounts
     * payable), the expense a vendor bill already posted it to, or owner's
     * capital for an asset the business already had.
     */
    public function createFixedAssetAcquisitionJournal(FixedAsset $asset): ?Journal
    {
        if ((float) $asset->purchase_cost <= 0) {
            return null;
        }
        $t = $asset->tenant_id;
        $credit = match ($asset->funding_source) {
            'cash' => $this->acct($t, 'cash'),
            'on_account' => $this->acct($t, 'accounts_payable'),
            'bill' => $this->acct($t, 'miscellaneous_expense'),
            'opening_balance' => $this->acct($t, 'owners_capital'),
            default => $this->getPaymentAccountCode('bank_transfer', $t),
        };
        $label = FixedAsset::FUNDING_SOURCES[$asset->funding_source] ?? 'Paid from the bank';

        return $this->postSimple($asset, self::ASSET_ACQUISITION, $asset->purchase_date, "Asset purchase - {$asset->name} ({$label})", [
            [$this->fixedAssetAccounts($asset)['asset'], (float) $asset->purchase_cost, 0, "Asset - {$asset->name}"],
            [$credit, 0, (float) $asset->purchase_cost, $label],
        ]);
    }

    public function createDepreciationJournal(FixedAsset $asset, Carbon $date, float $amount): ?Journal
    {
        $accounts = $this->fixedAssetAccounts($asset);

        return $this->postSimple($asset, self::ASSET_DEPRECIATION, $date, "Depreciation - {$asset->name} ({$date->format('M Y')})", [
            [$accounts['expense'], $amount, 0, "Depreciation - {$asset->name}"],
            [$accounts['accumulated'], 0, $amount, "Accumulated Depreciation - {$asset->name}"],
        ]);
    }

    /**
     * Disposal: remove cost and accumulated depreciation, record any money
     * received, and the gain or loss.
     */
    public function createDisposalJournal(FixedAsset $asset, Carbon $date, float $proceeds, float $gainLoss): ?Journal
    {
        $a = $this->fixedAssetAccounts($asset);
        $lines = [];
        if ((float) $asset->accumulated_depreciation > 0) {
            $lines[] = [$a['accumulated'], (float) $asset->accumulated_depreciation, 0, "Remove accumulated depreciation - {$asset->name}"];
        }
        if ($proceeds > 0) {
            $lines[] = [$this->getPaymentAccountCode('bank_transfer', $asset->tenant_id), $proceeds, 0, "Proceeds - {$asset->name}"];
        }
        $lines[] = [$a['asset'], 0, (float) $asset->purchase_cost, "Remove asset - {$asset->name}"];
        if ($gainLoss > 0) {
            $lines[] = [$a['gain'], 0, $gainLoss, "Gain on disposal - {$asset->name}"];
        } elseif ($gainLoss < 0) {
            $lines[] = [$a['loss'], -$gainLoss, 0, "Loss on disposal - {$asset->name}"];
        }

        return $this->postSimple($asset, self::ASSET_DISPOSAL, $date, "Asset disposal - {$asset->name}", $lines);
    }

    /**
     * One balanced journal for a document, inside a transaction, with the
     * normal period check.
     *
     * @param  array<int, array{0: string, 1: float, 2: float, 3: string}>  $lines  code, debit, credit, text
     */
    protected function postSimple(FixedAsset $document, string $type, mixed $date, string $description, array $lines): Journal
    {
        return DB::transaction(function () use ($document, $type, $date, $description, $lines) {
            $journal = Journal::create([
                'tenant_id' => $document->tenant_id,
                'journal_number' => Journal::generateNumber($document->tenant_id),
                'journal_date' => $date,
                'reference' => $document->asset_number,
                'description' => $description,
                'reference_type' => $document::class,
                'reference_id' => $document->getKey(),
                'journal_type' => $type,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => auth()->id(),
            ]);
            foreach ($lines as [$code, $debit, $credit, $text]) {
                $this->createEntry($journal, $code, round($debit, 2), round($credit, 2), $text);
            }
            $journal->updateTotals();
            $journal->save();
            $this->updateAccountBalances($journal); // refuses an unbalanced journal (M2)

            return $journal;
        });
    }

    /**
     * Opening a credit note reduces what the customer owes (finding N5):
     *   Dr Sales revenue      subtotal
     *   Dr Sales tax payable  tax
     *   Cr Accounts receivable total
     * Applying it to an invoice later is only an allocation within
     * receivables, so it posts nothing.
     */
    public function createCreditNoteJournal(CreditNote $creditNote): ?Journal
    {
        if ((float) $creditNote->total <= 0) {
            return null;
        }

        return DB::transaction(function () use ($creditNote) {
            $t = $creditNote->tenant_id;

            $existing = Journal::where('reference_type', CreditNote::class)
                ->where('reference_id', $creditNote->id)
                ->where('status', 'posted')
                ->first();
            if ($existing) {
                return $existing;
            }

            $customer = $creditNote->customer?->name ?? 'Customer';
            $journal = Journal::create([
                'tenant_id' => $t,
                'journal_number' => Journal::generateNumber($t),
                'journal_date' => $creditNote->credit_note_date,
                'reference' => $creditNote->credit_note_number,
                'description' => "Credit note {$creditNote->credit_note_number} - {$customer}",
                'reference_type' => CreditNote::class,
                'reference_id' => $creditNote->id,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => $creditNote->created_by ?? auth()->id(),
            ]);

            $tax = round((float) $creditNote->tax_amount, 2);
            $total = round((float) $creditNote->total, 2);
            $revenue = round($total - $tax, 2);

            if ($revenue > 0) {
                $this->createEntry($journal, $this->acct($t, 'sales_revenue'), $revenue, 0,
                    "Sales returns/allowances - {$creditNote->credit_note_number}");
            }
            if ($tax > 0) {
                $this->createEntry($journal, $this->acct($t, 'sales_tax_payable'), $tax, 0,
                    "Tax on credit note - {$creditNote->credit_note_number}");
            }
            $this->createEntry($journal, $this->acct($t, 'accounts_receivable'), 0, $total,
                "Credit to {$customer} - {$creditNote->credit_note_number}");

            $journal->updateTotals();
            $journal->save();

            $this->updateAccountBalances($journal);

            return $journal;
        });
    }

    /**
     * Reverse/void a journal entry
     */
    public function reverseJournal(Journal $journal, string $reason = 'Reversed'): Journal
    {
        return DB::transaction(function () use ($journal, $reason) {
            // The reversing journal's own lines undo the original when they are
            // applied below. Also un-applying the original here reversed it
            // twice (a 1,075 invoice left receivables at -1,075).

            $reversingJournal = Journal::create([
                'tenant_id' => $journal->tenant_id,
                'journal_number' => Journal::generateNumber($journal->tenant_id),
                'journal_date' => now()->toDateString(),
                'reference' => "REV-{$journal->journal_number}",
                'description' => "{$reason}: {$journal->description}",
                'reference_type' => $journal->reference_type,
                'reference_id' => $journal->reference_id,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => auth()->id(),
            ]);

            // Create reversing entries (swap debit and credit)
            foreach ($journal->entries as $entry) {
                $this->createEntry($reversingJournal, $entry->account->account_code,
                    $entry->credit, $entry->debit, "Reversal: {$entry->description}");
            }

            $reversingJournal->updateTotals();
            $reversingJournal->save();

            $this->updateAccountBalances($reversingJournal);

            // Mark original journal as reversed
            $journal->update(['status' => 'reversed']);

            return $reversingJournal;
        });
    }

    /**
     * Reverse the posted journal of a document (once). The original journal
     * and the reversal both stay in the ledger.
     */
    public function reverseDocumentJournal(string $referenceType, int $referenceId, string $reason): ?Journal
    {
        $journal = Journal::with('entries.account')
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('status', 'posted')
            ->whereNot('reference', 'like', 'REV-%')
            ->first();

        return $journal ? $this->reverseJournal($journal, $reason) : null;
    }

    /**
     * Called when a document (invoice, bill, payment, expense, payroll,
     * sales receipt, refund) is deleted.
     *
     * A posted journal is kept and reversed with a new journal dated today,
     * so the ledger shows what happened (finding M6). Previously it was
     * force-deleted, leaving no trace. A journal that was never posted (so
     * never reached the balances) is still removed.
     */
    public function deleteJournalForTransaction(string $referenceType, int $referenceId, ?int $tenantId = null): void
    {
        $this->returnDocumentStock($referenceType, $referenceId);

        foreach ($this->journalsForTransaction($referenceType, $referenceId, $tenantId) as $journal) {
            if ($journal->status === 'posted' && ! str_starts_with((string) $journal->reference, 'REV-')) {
                $this->reverseJournal($journal, class_basename($referenceType).' deleted');
            } elseif (! in_array($journal->status, ['posted', 'reversed'], true)) {
                $journal->entries()->forceDelete();
                $journal->forceDelete();
            }
        }
    }

    /**
     * Remove a document's journals completely, undoing their effect on the
     * balances. Only for repair commands that rebuild journals from scratch
     * (e.g. payroll:fix-journals); normal deletes use deleteJournalForTransaction().
     */
    public function purgeJournalForTransaction(string $referenceType, int $referenceId, ?int $tenantId = null): void
    {
        foreach ($this->journalsForTransaction($referenceType, $referenceId, $tenantId) as $journal) {
            if ($journal->status === 'posted') {
                $this->reverseAccountBalances($journal);
            }
            $journal->entries()->forceDelete();
            $journal->forceDelete();
        }
    }

    protected function journalsForTransaction(string $referenceType, int $referenceId, ?int $tenantId)
    {
        $tenantId = $tenantId ?? auth()->user()?->tenant_id;

        $query = Journal::withoutGlobalScopes()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->orderBy('id');

        // Always scope to tenant to prevent cross-tenant changes
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get();
    }

    /**
     * Create a journal entry line
     */
    public function createEntry(Journal $journal, string $accountCode, float $debit, float $credit, string $description): JournalEntry
    {
        $account = ChartOfAccount::where('tenant_id', $journal->tenant_id)
            ->where('account_code', $accountCode)
            ->first();

        if (! $account) {
            throw new InvalidArgumentException("Account with code {$accountCode} not found for tenant {$journal->tenant_id}");
        }

        return JournalEntry::create([
            'journal_id' => $journal->id,
            'account_id' => $account->id,
            'description' => $description,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
        ]);
    }

    /**
     * Get the payment account code based on payment method, resolved per-tenant.
     */
    protected function getPaymentAccountCode(?string $paymentMethod, int $tenantId): string
    {
        // tenantId is required: the old fallback for a missing tenant used an
        // undefined variable and would have crashed (L16).
        return AccountCodeService::resolvePaymentMethod($tenantId, $paymentMethod);
    }

    /**
     * Ledger account for money moving through a bank or payment method.
     *
     * When a bank account is chosen and it is linked to its own ledger
     * account (Banks > chart of account), post there, so each bank can be
     * reconciled in the ledger (M5). Otherwise use the payment method's
     * default account (cash, checking, ...), as before.
     */
    protected function paymentAccountFor(?Bank $bank, ?string $paymentMethod, int $tenantId): string
    {
        if ($bank && $bank->chart_of_account_id) {
            $code = ChartOfAccount::where('tenant_id', $tenantId)
                ->whereKey($bank->chart_of_account_id)
                ->value('account_code');
            if ($code) {
                return $code;
            }
        }

        return $this->getPaymentAccountCode($paymentMethod, $tenantId);
    }

    /**
     * Update chart of account balances based on journal entries
     */
    public function updateAccountBalances(Journal $journal): void
    {
        // Always read the journal's lines as they are now. The update paths
        // load the old lines (to reverse them), delete them and create new
        // ones; without a reload this re-applied the old amounts (C4).
        $journal->load('entries.account');

        // Never let an unbalanced journal reach the account balances (M2).
        // Callers run inside DB::transaction, so this rolls the posting back.
        $this->assertBalanced($journal);

        foreach ($journal->entries as $entry) {
            $account = $entry->account;

            // For debit-balance accounts (Assets, Expenses): Debits increase, Credits decrease
            // For credit-balance accounts (Liabilities, Equity, Income): Credits increase, Debits decrease
            if ($account->isDebitBalance()) {
                $delta = (float) ($entry->debit - $entry->credit);
            } else {
                $delta = (float) ($entry->credit - $entry->debit);
            }

            ChartOfAccount::where('id', $account->id)
                ->update(['current_balance' => \DB::raw('current_balance + ('.(float) $delta.')')]);
        }
    }

    /**
     * Reverse account balance changes from a journal
     */
    protected function reverseAccountBalances(Journal $journal): void
    {
        $journal->load('entries.account');

        foreach ($journal->entries as $entry) {
            $account = $entry->account;

            if ($account->isDebitBalance()) {
                $delta = (float) ($entry->debit - $entry->credit);
            } else {
                $delta = (float) ($entry->credit - $entry->debit);
            }

            ChartOfAccount::where('id', $account->id)
                ->update(['current_balance' => \DB::raw('current_balance - ('.(float) $delta.')')]);
        }
    }

    /**
     * Get journal entries for a specific transaction
     */
    public function getJournalForTransaction(string $referenceType, int $referenceId): ?Journal
    {
        return Journal::with('entries.account')
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->first();
    }

    /**
     * Split a bill's cost (net of tax) between inventory and expense.
     *
     * Line totals include their tax, and the tax is debited separately to
     * input tax, so each line is taken net of tax. Previously the full line
     * total was used, so a taxed bill's journal was out by the tax amount as
     * soon as it was rebuilt with its lines (e.g. on payment).
     *
     * Any rounding difference against subtotal - discount goes to the larger
     * side, so debits always equal the bill total.
     *
     * @return array{0: float, 1: float} [inventory, expense]
     */
    protected function billDebitSplit(Bill $bill): array
    {
        $inventory = 0.0;
        $expense = 0.0;

        foreach ($bill->items as $item) {
            $net = (float) $item->total - (float) ($item->tax_amount ?? 0);
            if ($item->item && $item->item->track_inventory) {
                $inventory += $net;
            } else {
                $expense += $net;
            }
        }

        $inventory = round($inventory, 2);
        $expense = round($expense, 2);

        if ($inventory == 0.0 && $expense == 0.0) {
            return [0.0, 0.0];
        }

        $target = round((float) $bill->subtotal - (float) ($bill->discount_amount ?? 0), 2);
        $difference = round($target - ($inventory + $expense), 2);

        if ($difference != 0.0 && abs($difference) <= 0.05) {
            if ($inventory >= $expense) {
                $inventory = round($inventory + $difference, 2);
            } else {
                $expense = round($expense + $difference, 2);
            }
        }

        return [$inventory, $expense];
    }

    /**
     * Check if a journal is balanced
     */
    public function isJournalBalanced(Journal $journal): bool
    {
        $totalDebit = round((float) $journal->entries()->sum('debit'), 2);
        $totalCredit = round((float) $journal->entries()->sum('credit'), 2);

        // Amounts are stored to the kobo, so after rounding they must match exactly.
        return abs($totalDebit - $totalCredit) < 0.005;
    }

    /**
     * @throws UnbalancedJournalException
     */
    public function assertBalanced(Journal $journal): void
    {
        if (! $this->isJournalBalanced($journal)) {
            throw UnbalancedJournalException::for($journal);
        }
    }
}
