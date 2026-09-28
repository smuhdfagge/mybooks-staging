<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\Bill;
use App\Models\Expense;
use App\Models\PaymentReceived;
use App\Models\PaymentMade;
use App\Models\SalesReceipt;
use App\Models\Payroll;
use App\Contracts\JournalServiceInterface;
use App\Services\AccountCodeService;
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
        $cogs = 0;
        $valuationService = app(StockValuationService::class);

        foreach ($invoice->items as $invoiceItem) {
            if ($invoiceItem->item && $invoiceItem->item->track_inventory) {
                $cogs += $valuationService->calculateCogs(
                    $invoiceItem->item,
                    $invoiceItem->quantity
                );
            }
        }

        return $cogs;
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
            $inventoryAmount = 0;
            $expenseAmount = 0;

            foreach ($bill->items as $item) {
                if ($item->item && $item->item->track_inventory) {
                    $inventoryAmount += $item->total;
                } else {
                    $expenseAmount += $item->total;
                }
            }

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
                $this->createEntry($journal, $this->acct($t, 'inventory'), $bill->subtotal, 0,
                    "Purchase - Bill {$bill->bill_number}");
            }

            // Debit: Tax if applicable (Input VAT is typically an asset)
            if ($bill->tax_amount > 0) {
                $this->createEntry($journal, $this->acct($t, 'prepaid_expenses'), $bill->tax_amount, 0,
                    "Input Tax - Bill {$bill->bill_number}"); // Prepaid/Input Tax
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

        $inventoryAmount = 0;
        $expenseAmount = 0;

        foreach ($bill->items as $item) {
            if ($item->item && $item->item->track_inventory) {
                $inventoryAmount += $item->total;
            } else {
                $expenseAmount += $item->total;
            }
        }

        if ($inventoryAmount > 0) {
            $this->createEntry($journal, $this->acct($t, 'inventory'), $inventoryAmount, 0,
                "Inventory Purchase - Bill {$bill->bill_number}");
        }

        if ($expenseAmount > 0) {
            $this->createEntry($journal, $this->acct($t, 'miscellaneous_expense'), $expenseAmount, 0,
                "Expense - Bill {$bill->bill_number}");
        }

        if ($inventoryAmount == 0 && $expenseAmount == 0 && $bill->subtotal > 0) {
            $this->createEntry($journal, $this->acct($t, 'inventory'), $bill->subtotal, 0,
                "Purchase - Bill {$bill->bill_number}");
        }

        if ($bill->tax_amount > 0) {
            $this->createEntry($journal, $this->acct($t, 'prepaid_expenses'), $bill->tax_amount, 0,
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
                $this->createEntry($journal, $this->acct($t, 'prepaid_expenses'), $expense->tax_amount, 0,
                    "Input Tax - {$expense->expense_number}");
            }

            // Credit: Payment Account (cash, bank, etc.)
            $paymentAccountCode = $expense->paidThroughAccount?->account_code 
                ?? $this->getPaymentAccountCode($expense->payment_method, $t);
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
            $this->createEntry($journal, $this->acct($t, 'prepaid_expenses'), $expense->tax_amount, 0,
                "Input Tax - {$expense->expense_number}");
        }

        $paymentAccountCode = $expense->paidThroughAccount?->account_code
            ?? $this->getPaymentAccountCode($expense->payment_method, $t);
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
                $paymentAccountCode = $this->getPaymentAccountCode($payment->payment_method, $t);
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
                // Regular payment: Debit Cash/Bank, Credit A/R
                $paymentAccountCode = $this->getPaymentAccountCode($payment->payment_method, $t);
                $this->createEntry($journal, $paymentAccountCode, $payment->amount, 0,
                    "Payment Received - {$payment->payment_number}");

                $this->createEntry($journal, $this->acct($t, 'accounts_receivable'), 0, $payment->amount,
                    "Payment for {$payment->customer->name}");
            }

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
            $paymentAccountCode = $this->getPaymentAccountCode($payment->payment_method, $t);
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
            // Regular payment: Debit Cash/Bank, Credit A/R
            $paymentAccountCode = $this->getPaymentAccountCode($payment->payment_method, $t);
            $this->createEntry($journal, $paymentAccountCode, $payment->amount, 0,
                "Payment Received - {$payment->payment_number}");

            $this->createEntry($journal, $this->acct($t, 'accounts_receivable'), 0, $payment->amount,
                "Payment for {$payment->customer->name}");
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
    public function createRefundJournal(\App\Models\InvoiceRefund $refund): ?Journal
    {
        if ($refund->amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($refund) {
            $t = $refund->tenant_id;

            $existingJournal = Journal::where('reference_type', \App\Models\InvoiceRefund::class)
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
                'reference_type' => \App\Models\InvoiceRefund::class,
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
    protected function updateRefundJournal(\App\Models\InvoiceRefund $refund, Journal $journal): Journal
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
     */
    public function createPaymentMadeJournal(PaymentMade $payment): ?Journal
    {
        if ($payment->amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($payment) {
            $t = $payment->tenant_id;

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

            // Debit: Accounts Payable (reduces liability)
            $this->createEntry($journal, $this->acct($t, 'accounts_payable'), $payment->amount, 0,
                "Payment to {$payment->vendor->name}");

            // Credit: Cash/Bank account
            $paymentAccountCode = $this->getPaymentAccountCode($payment->payment_method, $t);
            $this->createEntry($journal, $paymentAccountCode, 0, $payment->amount,
                "Payment Made - {$payment->payment_number}");

            $journal->updateTotals();
            $journal->save();

            $this->updateAccountBalances($journal);

            return $journal;
        });
    }

    /**
     * Update existing payment made journal
     */
    protected function updatePaymentMadeJournal(PaymentMade $payment, Journal $journal): Journal
    {
        $t = $payment->tenant_id;

        $this->reverseAccountBalances($journal);
        $journal->entries()->delete();

        $billRef = $payment->bill ? " for Bill {$payment->bill->bill_number}" : '';

        $journal->update([
            'journal_date' => $payment->payment_date,
            'reference' => $payment->payment_number,
            'description' => "Payment Made {$payment->payment_number} - {$payment->vendor->name}{$billRef}",
        ]);

        $this->createEntry($journal, $this->acct($t, 'accounts_payable'), $payment->amount, 0,
            "Payment to {$payment->vendor->name}");

        $paymentAccountCode = $this->getPaymentAccountCode($payment->payment_method, $t);
        $this->createEntry($journal, $paymentAccountCode, 0, $payment->amount,
            "Payment Made - {$payment->payment_number}");

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
        $cogs = 0;
        $valuationService = app(StockValuationService::class);

        foreach ($receipt->items as $receiptItem) {
            if ($receiptItem->item && $receiptItem->item->track_inventory) {
                $cogs += $valuationService->calculateCogs(
                    $receiptItem->item,
                    $receiptItem->quantity
                );
            }
        }

        return $cogs;
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

    /**
     * Create journal entry for payroll
     * 
     * Debit: Salaries & Wages (expense increases - basic salary)
     * Debit: Allowances Expense (expense increases - allowances)
     * Debit: Overtime Expense (expense increases - overtime, if any)
     * Debit: Payroll Taxes (expense increases - employer tax portion, if any)
     * Credit: Cash/Bank (asset decreases - net salary paid)
     * Credit: Payroll Liabilities (liability increases - tax deductions withheld)
     * Credit: Payroll Liabilities (liability increases - other deductions)
     * Credit: Payroll Liabilities (liability increases - employer contributions)
     */
    public function createPayrollJournal(Payroll $payroll): ?Journal
    {
        if ($payroll->net_salary <= 0) {
            return null;
        }

        return DB::transaction(function () use ($payroll) {
            $t = $payroll->tenant_id;

            $existingJournal = Journal::where('reference_type', Payroll::class)
                ->where('reference_id', $payroll->id)
                ->first();

            if ($existingJournal) {
                return $this->updatePayrollJournal($payroll, $existingJournal);
            }

            $journal = new Journal([
                'tenant_id' => $payroll->tenant_id,
                'journal_number' => Journal::generateNumber($payroll->tenant_id),
                'journal_date' => $payroll->pay_date ?? now(),
                'reference' => $payroll->payroll_number,
                'description' => "Payroll {$payroll->payroll_number} - {$payroll->employee->name}",
                'reference_type' => Payroll::class,
                'reference_id' => $payroll->id,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => $payroll->created_by ?? auth()->id(),
            ]);
            $journal->withoutPeriodValidation()->save();

            // Debit: Salaries & Wages (basic salary only)
            if ($payroll->basic_salary > 0) {
                $this->createEntry($journal, $this->acct($t, 'salaries_wages'), $payroll->basic_salary, 0,
                    "Basic Salary - {$payroll->employee->name} ({$payroll->payroll_number})");
            }

            // Debit: Allowances Expense
            if ($payroll->allowances > 0) {
                $this->createEntry($journal, $this->acct($t, 'allowances_expense'), $payroll->allowances, 0,
                    "Allowances - {$payroll->employee->name} ({$payroll->payroll_number})");
            }

            // Debit: Overtime Expense
            if ($payroll->overtime_amount > 0) {
                $this->createEntry($journal, $this->acct($t, 'overtime_expense'), $payroll->overtime_amount, 0,
                    "Overtime - {$payroll->employee->name} ({$payroll->payroll_number})");
            }

            // Debit: Employer Contributions (split by type using contribution details)
            $employerContributions = (float) ($payroll->employer_contributions ?? 0);
            if ($employerContributions > 0) {
                $contributionDetails = $payroll->employer_contribution_details ?? [];
                if (!empty($contributionDetails)) {
                    foreach ($contributionDetails as $contribution) {
                        $amount = (float) ($contribution['amount'] ?? 0);
                        if ($amount > 0) {
                            $expenseAccount = $this->mapContributionToExpenseAccount($contribution['name'] ?? '', $t);
                            $this->createEntry($journal, $expenseAccount, $amount, 0,
                                "{$contribution['name']} - {$payroll->payroll_number}");
                        }
                    }
                } else {
                    $this->createEntry($journal, $this->acct($t, 'payroll_taxes'), $employerContributions, 0,
                        "Employer Contributions - {$payroll->payroll_number}");
                }
            }

            // Credit: Cash/Bank (net salary paid to employee)
            $paymentAccountCode = $this->getPaymentAccountCode($payroll->payment_method ?? 'bank_transfer', $t);
            $this->createEntry($journal, $paymentAccountCode, 0, $payroll->net_salary,
                "Net Pay - {$payroll->payroll_number}");

            // Credit: Tax Payable (income tax withheld from employee)
            if ($payroll->tax_deduction > 0) {
                $this->createEntry($journal, $this->acct($t, 'tax_payable'), 0, $payroll->tax_deduction,
                    "Tax Withheld - {$payroll->payroll_number}");
            }

            // Credit: Liability accounts (other deductions split by type)
            if ($payroll->other_deductions > 0) {
                $deductionDetails = $payroll->deduction_details ?? [];
                $mappedTotal = 0;
                foreach ($deductionDetails as $deduction) {
                    if (str_starts_with($deduction['name'] ?? '', '_')) {
                        continue;
                    }
                    $amount = (float) ($deduction['amount'] ?? 0);
                    if ($amount > 0) {
                        $liabilityAccount = $this->mapDeductionToLiabilityAccount($deduction['name'] ?? '', $t);
                        $this->createEntry($journal, $liabilityAccount, 0, $amount,
                            "{$deduction['name']} - {$payroll->payroll_number}");
                        $mappedTotal += $amount;
                    }
                }
                $remainder = round($payroll->other_deductions - $mappedTotal, 2);
                if ($remainder > 0) {
                    $this->createEntry($journal, $this->acct($t, 'payroll_liabilities'), 0, $remainder,
                        "Other Deductions - {$payroll->payroll_number}");
                }
            }

            // Credit: Liability accounts (employer contributions split by type)
            if ($employerContributions > 0) {
                $contributionDetails = $payroll->employer_contribution_details ?? [];
                if (!empty($contributionDetails)) {
                    foreach ($contributionDetails as $contribution) {
                        $amount = (float) ($contribution['amount'] ?? 0);
                        if ($amount > 0) {
                            $liabilityAccount = $this->mapContributionToLiabilityAccount($contribution['name'] ?? '', $t);
                            $this->createEntry($journal, $liabilityAccount, 0, $amount,
                                "{$contribution['name']} Payable - {$payroll->payroll_number}");
                        }
                    }
                } else {
                    $this->createEntry($journal, $this->acct($t, 'payroll_liabilities'), 0, $employerContributions,
                        "Employer Contributions Payable - {$payroll->payroll_number}");
                }
            }

            $journal->updateTotals();
            $journal->withoutPeriodValidation()->save();

            $this->updateAccountBalances($journal);

            return $journal;
        });
    }

    /**
     * Update existing payroll journal
     */
    protected function updatePayrollJournal(Payroll $payroll, Journal $journal): Journal
    {
        $t = $payroll->tenant_id;

        $this->reverseAccountBalances($journal);
        $journal->entries()->delete();

        $journal->fill([
            'journal_date' => $payroll->pay_date ?? now(),
            'reference' => $payroll->payroll_number,
            'description' => "Payroll {$payroll->payroll_number} - {$payroll->employee->name}",
        ]);
        $journal->withoutPeriodValidation()->save();

        // Debit: Basic Salary
        if ($payroll->basic_salary > 0) {
            $this->createEntry($journal, $this->acct($t, 'salaries_wages'), $payroll->basic_salary, 0,
                "Basic Salary - {$payroll->employee->name} ({$payroll->payroll_number})");
        }

        // Debit: Allowances
        if ($payroll->allowances > 0) {
            $this->createEntry($journal, $this->acct($t, 'allowances_expense'), $payroll->allowances, 0,
                "Allowances - {$payroll->employee->name} ({$payroll->payroll_number})");
        }

        // Debit: Overtime
        if ($payroll->overtime_amount > 0) {
            $this->createEntry($journal, $this->acct($t, 'overtime_expense'), $payroll->overtime_amount, 0,
                "Overtime - {$payroll->employee->name} ({$payroll->payroll_number})");
        }

        // Debit: Employer Contributions (split by type)
        $employerContributions = (float) ($payroll->employer_contributions ?? 0);
        if ($employerContributions > 0) {
            $contributionDetails = $payroll->employer_contribution_details ?? [];
            if (!empty($contributionDetails)) {
                foreach ($contributionDetails as $contribution) {
                    $amount = (float) ($contribution['amount'] ?? 0);
                    if ($amount > 0) {
                        $expenseAccount = $this->mapContributionToExpenseAccount($contribution['name'] ?? '', $t);
                        $this->createEntry($journal, $expenseAccount, $amount, 0,
                            "{$contribution['name']} - {$payroll->payroll_number}");
                    }
                }
            } else {
                $this->createEntry($journal, $this->acct($t, 'payroll_taxes'), $employerContributions, 0,
                    "Employer Contributions - {$payroll->payroll_number}");
            }
        }

        $paymentAccountCode = $this->getPaymentAccountCode($payroll->payment_method ?? 'bank_transfer', $t);
        $this->createEntry($journal, $paymentAccountCode, 0, $payroll->net_salary,
            "Net Pay - {$payroll->payroll_number}");

        if ($payroll->tax_deduction > 0) {
            $this->createEntry($journal, $this->acct($t, 'tax_payable'), 0, $payroll->tax_deduction,
                "Tax Withheld - {$payroll->payroll_number}");
        }

        if ($payroll->other_deductions > 0) {
            $deductionDetails = $payroll->deduction_details ?? [];
            $mappedTotal = 0;
            foreach ($deductionDetails as $deduction) {
                if (str_starts_with($deduction['name'] ?? '', '_')) {
                    continue;
                }
                $amount = (float) ($deduction['amount'] ?? 0);
                if ($amount > 0) {
                    $liabilityAccount = $this->mapDeductionToLiabilityAccount($deduction['name'] ?? '', $t);
                    $this->createEntry($journal, $liabilityAccount, 0, $amount,
                        "{$deduction['name']} - {$payroll->payroll_number}");
                    $mappedTotal += $amount;
                }
            }
            $remainder = round($payroll->other_deductions - $mappedTotal, 2);
            if ($remainder > 0) {
                $this->createEntry($journal, $this->acct($t, 'payroll_liabilities'), 0, $remainder,
                    "Other Deductions - {$payroll->payroll_number}");
            }
        }

        if ($employerContributions > 0) {
            $contributionDetails = $payroll->employer_contribution_details ?? [];
            if (!empty($contributionDetails)) {
                foreach ($contributionDetails as $contribution) {
                    $amount = (float) ($contribution['amount'] ?? 0);
                    if ($amount > 0) {
                        $liabilityAccount = $this->mapContributionToLiabilityAccount($contribution['name'] ?? '', $t);
                        $this->createEntry($journal, $liabilityAccount, 0, $amount,
                            "{$contribution['name']} Payable - {$payroll->payroll_number}");
                    }
                }
            } else {
                $this->createEntry($journal, $this->acct($t, 'payroll_liabilities'), 0, $employerContributions,
                    "Employer Contributions Payable - {$payroll->payroll_number}");
            }
        }

        $journal->updateTotals();
        $journal->withoutPeriodValidation()->save();

        $this->updateAccountBalances($journal);

        return $journal;
    }

    /**
     * Reverse/void a journal entry
     */
    public function reverseJournal(Journal $journal, string $reason = 'Reversed'): Journal
    {
        return DB::transaction(function () use ($journal, $reason) {
            $this->reverseAccountBalances($journal);

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
     * Delete journal entries for a transaction (when transaction is deleted)
     */
    public function deleteJournalForTransaction(string $referenceType, int $referenceId, ?int $tenantId = null): void
    {
        // Resolve tenant_id from parameter, auth context, or fail safely
        $tenantId = $tenantId ?? auth()->user()?->tenant_id;

        $query = Journal::withoutGlobalScopes()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId);

        // Always scope to tenant to prevent cross-tenant deletion
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $journal = $query->first();

        if ($journal) {
            // Reverse the account balances first
            $this->reverseAccountBalances($journal);
            
            // Force delete entries and journal (not soft delete) 
            // since these are accounting records that should be removed when source transaction is deleted
            $journal->entries()->forceDelete();
            $journal->forceDelete();
        }
    }

    /**
     * Create a journal entry line
     */
    public function createEntry(Journal $journal, string $accountCode, float $debit, float $credit, string $description): JournalEntry
    {
        $account = ChartOfAccount::where('tenant_id', $journal->tenant_id)
            ->where('account_code', $accountCode)
            ->first();

        if (!$account) {
            throw new InvalidArgumentException("Account with code {$accountCode} not found for tenant {$journal->tenant_id}");
        }

        return JournalEntry::create([
            'journal_id' => $journal->id,
            'account_id' => $account->id,
            'description' => $description,
            'debit' => $debit,
            'credit' => $credit,
        ]);
    }

    /**
     * Get the payment account code based on payment method, resolved per-tenant.
     */
    protected function getPaymentAccountCode(?string $paymentMethod, int $tenantId = 0): string
    {
        if ($tenantId > 0) {
            return AccountCodeService::resolvePaymentMethod($tenantId, $paymentMethod);
        }

        // Legacy fallback when tenantId not provided
        if ($paymentMethod === null || $paymentMethod === '') {
            return $this->acct($t, 'cash');
        }

        $method = strtolower(str_replace(' ', '_', $paymentMethod));
        return $this->paymentMethodAccounts[$method] ?? $this->acct($t, 'cash');
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
                ->update(['current_balance' => \DB::raw('current_balance + (' . (float) $delta . ')')]);
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
                ->update(['current_balance' => \DB::raw('current_balance - (' . (float) $delta . ')')]);
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
     * Check if a journal is balanced
     */
    public function isJournalBalanced(Journal $journal): bool
    {
        $totalDebit = $journal->entries()->sum('debit');
        $totalCredit = $journal->entries()->sum('credit');
        
        return abs($totalDebit - $totalCredit) < 0.01; // Allow for small floating point differences
    }
}
