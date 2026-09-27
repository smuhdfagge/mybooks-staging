<?php

namespace Tests\Feature\Api;

use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Payroll;
use App\Models\Vendor;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    private string $token;

    private function createApiUser(array $permissions = []): string
    {
        $this->createAuthenticatedUser($permissions);
        return $this->user->createToken('test-device')->plainTextToken;
    }

    private function setUpReportUser(): void
    {
        $this->token = $this->createApiUser(['view reports']);
    }

    // ─────────────────────────────────────────────────────
    // Authentication & Authorization
    // ─────────────────────────────────────────────────────

    public function test_unauthenticated_request_gets_401(): void
    {
        $response = $this->getJson('/api/v1/reports/profit-loss');
        $response->assertUnauthorized();
    }

    public function test_user_without_permission_gets_403(): void
    {
        $token = $this->createApiUser([]); // no permissions
        $response = $this->withToken($token)->getJson('/api/v1/reports/profit-loss');
        $response->assertForbidden();
    }

    // ─────────────────────────────────────────────────────
    // Profit & Loss
    // ─────────────────────────────────────────────────────

    public function test_profit_loss_returns_json(): void
    {
        $this->setUpReportUser();
        $this->seedAccountsAndJournals();

        $response = $this->withToken($this->token)->getJson('/api/v1/reports/profit-loss');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['revenue', 'operating_expenses', 'net_profit']]);
    }

    public function test_profit_loss_with_date_filter(): void
    {
        $this->setUpReportUser();
        $this->seedAccountsAndJournals();

        $response = $this->withToken($this->token)->getJson('/api/v1/reports/profit-loss?' . http_build_query([
            'start_date' => now()->startOfYear()->format('Y-m-d'),
            'end_date' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertOk()
            ->assertJsonStructure(['data' => ['revenue', 'net_profit']]);
    }

    // ─────────────────────────────────────────────────────
    // Balance Sheet
    // ─────────────────────────────────────────────────────

    public function test_balance_sheet_returns_json(): void
    {
        $this->setUpReportUser();

        $response = $this->withToken($this->token)->getJson('/api/v1/reports/balance-sheet');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['assets', 'liabilities', 'equity']]);
    }

    // ─────────────────────────────────────────────────────
    // Cash Flow
    // ─────────────────────────────────────────────────────

    public function test_cash_flow_returns_json(): void
    {
        $this->setUpReportUser();

        $response = $this->withToken($this->token)->getJson('/api/v1/reports/cash-flow');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─────────────────────────────────────────────────────
    // Trial Balance
    // ─────────────────────────────────────────────────────

    public function test_trial_balance_returns_json(): void
    {
        $this->setUpReportUser();
        $this->seedAccountsAndJournals();

        $response = $this->withToken($this->token)->getJson('/api/v1/reports/trial-balance');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['accounts', 'totals']]);
    }

    // ─────────────────────────────────────────────────────
    // Accounts Receivable
    // ─────────────────────────────────────────────────────

    public function test_accounts_receivable_returns_json(): void
    {
        $this->setUpReportUser();

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Invoice::withoutEvents(fn () => Invoice::factory()->sent()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'unpaid',
            'balance_due' => 750,
        ]));

        $response = $this->withToken($this->token)->getJson('/api/v1/reports/accounts-receivable');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─────────────────────────────────────────────────────
    // Accounts Payable
    // ─────────────────────────────────────────────────────

    public function test_accounts_payable_returns_json(): void
    {
        $this->setUpReportUser();

        $vendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        Bill::withoutEvents(fn () => Bill::factory()->create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'status' => 'unpaid',
            'balance_due' => 300,
        ]));

        $response = $this->withToken($this->token)->getJson('/api/v1/reports/accounts-payable');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─────────────────────────────────────────────────────
    // Sales
    // ─────────────────────────────────────────────────────

    public function test_sales_report_returns_json(): void
    {
        $this->setUpReportUser();

        $response = $this->withToken($this->token)->getJson('/api/v1/reports/sales');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─────────────────────────────────────────────────────
    // Tax Summary
    // ─────────────────────────────────────────────────────

    public function test_tax_summary_returns_json(): void
    {
        $this->setUpReportUser();

        $response = $this->withToken($this->token)->getJson('/api/v1/reports/tax-summary');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─────────────────────────────────────────────────────
    // General Ledger
    // ─────────────────────────────────────────────────────

    public function test_general_ledger_returns_json(): void
    {
        $this->setUpReportUser();
        $this->seedAccountsAndJournals();

        $account = ChartOfAccount::where('tenant_id', $this->tenant->id)
            ->where('account_code', '1000')
            ->first();

        $response = $this->withToken($this->token)->getJson('/api/v1/reports/general-ledger?account_id=' . $account->id);

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─────────────────────────────────────────────────────
    // Sales by Customer
    // ─────────────────────────────────────────────────────

    public function test_sales_by_customer_returns_json(): void
    {
        $this->setUpReportUser();

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'paid',
        ]));

        $response = $this->withToken($this->token)->getJson('/api/v1/reports/sales-by-customer');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─────────────────────────────────────────────────────
    // Sales by Item
    // ─────────────────────────────────────────────────────

    public function test_sales_by_item_returns_json(): void
    {
        $this->setUpReportUser();

        $response = $this->withToken($this->token)->getJson('/api/v1/reports/sales-by-item');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─────────────────────────────────────────────────────
    // Purchase by Vendor
    // ─────────────────────────────────────────────────────

    public function test_purchase_by_vendor_returns_json(): void
    {
        $this->setUpReportUser();

        $response = $this->withToken($this->token)->getJson('/api/v1/reports/purchase-by-vendor');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─────────────────────────────────────────────────────
    // Inventory Summary
    // ─────────────────────────────────────────────────────

    public function test_inventory_summary_returns_json(): void
    {
        $this->setUpReportUser();

        Item::withoutEvents(fn () => Item::factory()->product()->create([
            'tenant_id' => $this->tenant->id,
        ]));

        $response = $this->withToken($this->token)->getJson('/api/v1/reports/inventory-summary');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─────────────────────────────────────────────────────
    // Payroll Summary
    // ─────────────────────────────────────────────────────

    public function test_payroll_summary_returns_json(): void
    {
        $this->setUpReportUser();

        $employee = Employee::withoutEvents(fn () => Employee::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => 'EMP-001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'hire_date' => now()->subYear(),
            'status' => 'active',
        ]));

        Payroll::withoutEvents(fn () => Payroll::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employee->id,
            'payroll_number' => 'PAY-TEST-001',
            'pay_period_start' => now()->startOfMonth(),
            'pay_period_end' => now()->endOfMonth(),
            'pay_date' => now(),
            'basic_salary' => 5000,
            'allowances' => 500,
            'gross_salary' => 5500,
            'tax_deduction' => 550,
            'total_deductions' => 550,
            'net_salary' => 4950,
            'status' => 'paid',
            'payment_method' => 'bank_transfer',
            'created_by' => $this->user->id,
        ]));

        $response = $this->withToken($this->token)->getJson('/api/v1/reports/payroll-summary');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─────────────────────────────────────────────────────
    // Customer Statement
    // ─────────────────────────────────────────────────────

    public function test_customer_statement_returns_json(): void
    {
        $this->setUpReportUser();

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)->getJson('/api/v1/reports/customer-statement?customer_id=' . $customer->id);

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─────────────────────────────────────────────────────
    // Cross-Tenant Isolation
    // ─────────────────────────────────────────────────────

    public function test_report_does_not_leak_cross_tenant_data(): void
    {
        $this->setUpReportUser();
        $this->seedAccountsAndJournals();

        // Create second tenant without triggering auto-seeding (avoids unique key conflict)
        $otherTenant = \App\Models\Tenant::withoutEvents(fn () => \App\Models\Tenant::factory()->create());
        $otherJournal = Journal::withoutEvents(fn () => Journal::create([
            'tenant_id' => $otherTenant->id,
            'journal_number' => 'JE-OTHER-001',
            'journal_date' => now(),
            'description' => 'Other tenant data',
            'total_debit' => 99999,
            'total_credit' => 99999,
            'status' => 'posted',
            'is_posted' => true,
            'posted_at' => now(),
        ]));

        // Our P&L should not include the other tenant's 99999
        $response = $this->withToken($this->token)->getJson('/api/v1/reports/profit-loss?' . http_build_query([
            'start_date' => now()->startOfYear()->format('Y-m-d'),
            'end_date' => now()->addDay()->format('Y-m-d'),
        ]));
        $response->assertOk();

        $data = $response->json('data');
        // Net profit should NOT be inflated by the other tenant's 99999 entry
        $this->assertLessThan(10000, abs($data['net_profit']));
    }

    // ─────────────────────────────────────────────────────
    // Helper: Seed accounts and journal entries
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

        // Revenue journal
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

        // Expense journal
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
