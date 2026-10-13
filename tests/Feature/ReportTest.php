<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Payroll;
use App\Models\Vendor;
use Tests\TestCase;

class ReportTest extends TestCase
{
    // ─────────────────────────────────────────────────────
    // Authentication & Authorization
    // ─────────────────────────────────────────────────────

    public function test_unauthenticated_user_is_redirected_from_reports(): void
    {
        $response = $this->get(route('reports.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_user_without_permission_gets_403(): void
    {
        $this->createAuthenticatedUser([]); // no permissions
        $response = $this->get(route('reports.index'));
        $response->assertForbidden();
    }

    // ─────────────────────────────────────────────────────
    // Reports Index
    // ─────────────────────────────────────────────────────

    public function test_reports_index_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.index'));
        $response->assertOk();
        $response->assertSee('Reports');
    }

    // ─────────────────────────────────────────────────────
    // Profit & Loss
    // ─────────────────────────────────────────────────────

    public function test_profit_loss_report_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.profit-loss'));
        $response->assertOk();
        $response->assertSee('Profit', false);
    }

    public function test_profit_loss_with_journal_data(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $this->seedAccountsAndJournals();

        $response = $this->get(route('reports.profit-loss', [
            'start_date' => now()->startOfYear()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
    }

    // ─────────────────────────────────────────────────────
    // Balance Sheet
    // ─────────────────────────────────────────────────────

    public function test_balance_sheet_report_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.balance-sheet'));
        $response->assertOk();
        $response->assertSee('Balance sheet');
    }

    // ─────────────────────────────────────────────────────
    // Cash Flow
    // ─────────────────────────────────────────────────────

    public function test_cash_flow_report_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.cash-flow'));
        $response->assertOk();
        $response->assertSee('Cash flow statement');
    }

    // ─────────────────────────────────────────────────────
    // Trial Balance
    // ─────────────────────────────────────────────────────

    public function test_trial_balance_report_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.trial-balance'));
        $response->assertOk();
        $response->assertSee('Trial balance');
    }

    // ─────────────────────────────────────────────────────
    // General Ledger
    // ─────────────────────────────────────────────────────

    public function test_general_ledger_loads_without_account(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.general-ledger'));
        $response->assertOk();
        $response->assertSee('General ledger');
    }

    public function test_general_ledger_loads_with_account(): void
    {
        $this->createAuthenticatedUser(['view reports']);

        $account = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('account_code', '1000')
            ->first();

        $response = $this->get(route('reports.general-ledger', ['account_id' => $account->id]));
        $response->assertOk();
    }

    // ─────────────────────────────────────────────────────
    // Accounts Receivable
    // ─────────────────────────────────────────────────────

    public function test_accounts_receivable_report_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);

        Invoice::withoutEvents(fn () => Invoice::factory()->sent()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'status' => 'unpaid',
            'balance_due' => 500,
        ]));

        $response = $this->get(route('reports.accounts-receivable'));
        $response->assertOk();
        $response->assertSee('Aged receivables');
    }

    // ─────────────────────────────────────────────────────
    // Accounts Payable
    // ─────────────────────────────────────────────────────

    public function test_accounts_payable_report_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);

        Bill::withoutEvents(fn () => Bill::factory()->create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => Vendor::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'status' => 'unpaid',
            'balance_due' => 300,
        ]));

        $response = $this->get(route('reports.accounts-payable'));
        $response->assertOk();
        $response->assertSee('Aged payables');
    }

    // ─────────────────────────────────────────────────────
    // Sales by Customer
    // ─────────────────────────────────────────────────────

    public function test_sales_by_customer_report_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.sales-by-customer'));
        $response->assertOk();
    }

    // ─────────────────────────────────────────────────────
    // Sales by Item
    // ─────────────────────────────────────────────────────

    public function test_sales_by_item_report_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.sales-by-item'));
        $response->assertOk();
    }

    // ─────────────────────────────────────────────────────
    // Purchase by Vendor
    // ─────────────────────────────────────────────────────

    public function test_purchase_by_vendor_report_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.purchase-by-vendor'));
        $response->assertOk();
    }

    // ─────────────────────────────────────────────────────
    // Customer Statement
    // ─────────────────────────────────────────────────────

    public function test_customer_statement_loads_without_customer(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.customer-statement'));
        $response->assertOk();
    }

    public function test_customer_statement_loads_with_customer(): void
    {
        $this->createAuthenticatedUser(['view reports']);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        Invoice::withoutEvents(fn () => Invoice::factory()->sent()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]));

        $response = $this->get(route('reports.customer-statement', ['customer_id' => $customer->id]));
        $response->assertOk();
    }

    // ─────────────────────────────────────────────────────
    // Inventory Summary
    // ─────────────────────────────────────────────────────

    public function test_inventory_summary_report_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.inventory-summary'));
        $response->assertOk();
    }

    // ─────────────────────────────────────────────────────
    // Payroll Summary
    // ─────────────────────────────────────────────────────

    public function test_payroll_summary_report_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.payroll-summary'));
        $response->assertOk();
    }

    // ─────────────────────────────────────────────────────
    // Comparative Reports
    // ─────────────────────────────────────────────────────

    public function test_comparative_profit_loss_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.comparative.profit-loss'));
        $response->assertOk();
    }

    public function test_comparative_balance_sheet_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.comparative.balance-sheet'));
        $response->assertOk();
    }

    public function test_comparative_cash_flow_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.comparative.cash-flow'));
        $response->assertOk();
    }

    // ─────────────────────────────────────────────────────
    // Tax Reports
    // ─────────────────────────────────────────────────────

    public function test_vat_gst_return_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.vat-gst-return'));
        $response->assertOk();
    }

    public function test_tax_liability_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.tax-liability'));
        $response->assertOk();
    }

    // ─────────────────────────────────────────────────────
    // Custom Report Builder
    // ─────────────────────────────────────────────────────

    public function test_custom_report_index_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.custom.index'));
        $response->assertOk();
    }

    public function test_custom_report_create_loads(): void
    {
        $this->createAuthenticatedUser(['view reports']);
        $response = $this->get(route('reports.custom.create'));
        $response->assertOk();
    }

    // ─────────────────────────────────────────────────────
    // Helper: Seed accounts and journal entries for P&L
    // ─────────────────────────────────────────────────────

    private function seedAccountsAndJournals(): void
    {
        $tenantId = $this->tenant->id;

        $revenueAccount = ChartOfAccount::firstOrCreate(
            ['tenant_id' => $tenantId, 'account_code' => '4000'],
            ['name' => 'Revenue', 'type' => 'income', 'sub_type' => 'revenue', 'is_active' => true, 'current_balance' => 0]
        );

        $expenseAccount = ChartOfAccount::firstOrCreate(
            ['tenant_id' => $tenantId, 'account_code' => '6990'],
            ['name' => 'General Expense', 'type' => 'expense', 'sub_type' => 'operating_expense', 'is_active' => true, 'current_balance' => 0]
        );

        $cashAccount = ChartOfAccount::firstOrCreate(
            ['tenant_id' => $tenantId, 'account_code' => '1000'],
            ['name' => 'Cash', 'type' => 'asset', 'sub_type' => 'cash', 'is_active' => true, 'current_balance' => 0]
        );

        // Create a posted journal with revenue
        $journal = Journal::withoutEvents(fn () => Journal::create([
            'tenant_id' => $tenantId,
            'journal_number' => 'JE-TEST-001',
            'journal_date' => now(),
            'description' => 'Test revenue entry',
            'total_debit' => 5000,
            'total_credit' => 5000,
            'status' => 'posted',
            'is_posted' => true,
            'posted_at' => now(),
        ]));

        JournalEntry::withoutEvents(fn () => JournalEntry::create([
            'journal_id' => $journal->id,
            'account_id' => $cashAccount->id,
            'description' => 'Cash received',
            'debit' => 5000,
            'credit' => 0,
        ]));

        JournalEntry::withoutEvents(fn () => JournalEntry::create([
            'journal_id' => $journal->id,
            'account_id' => $revenueAccount->id,
            'description' => 'Revenue earned',
            'debit' => 0,
            'credit' => 5000,
        ]));

        // Create a posted journal with expense
        $journal2 = Journal::withoutEvents(fn () => Journal::create([
            'tenant_id' => $tenantId,
            'journal_number' => 'JE-TEST-002',
            'journal_date' => now(),
            'description' => 'Test expense entry',
            'total_debit' => 2000,
            'total_credit' => 2000,
            'status' => 'posted',
            'is_posted' => true,
            'posted_at' => now(),
        ]));

        JournalEntry::withoutEvents(fn () => JournalEntry::create([
            'journal_id' => $journal2->id,
            'account_id' => $expenseAccount->id,
            'description' => 'General expense',
            'debit' => 2000,
            'credit' => 0,
        ]));

        JournalEntry::withoutEvents(fn () => JournalEntry::create([
            'journal_id' => $journal2->id,
            'account_id' => $cashAccount->id,
            'description' => 'Cash paid',
            'debit' => 0,
            'credit' => 2000,
        ]));
    }
}
