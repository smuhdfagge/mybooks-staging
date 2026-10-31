<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\PaymentReceived;
use App\Models\Payroll;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\StockValuationService;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoBusinessDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo business data may only be seeded in local or testing environments.');
        }

        $tenant = Tenant::where('slug', 'demo-company')->firstOrFail();
        // Seeding runs with nobody signed in: the demo data goes in the
        // demo business (audit MB-26).
        Tenant::run($tenant, fn () => $this->seedDemo($tenant));
    }

    private function seedDemo(Tenant $tenant): void
    {
        $user = User::where('email', 'demo@mybooks.local')->firstOrFail();
        $tenantId = (int) $tenant->id;

        $customer = Customer::firstOrCreate(
            ['tenant_id' => $tenantId, 'email' => 'orders@northstar-demo.test'],
            [
                'name' => 'Northstar Office Supplies',
                'company_name' => 'Northstar Office Supplies Ltd',
                'phone' => '+234 801 555 0101',
                'billing_address' => '18 Marina Road',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'country' => 'Nigeria',
                'payment_terms' => 30,
                'is_active' => true,
            ]
        );

        $vendor = Vendor::firstOrCreate(
            ['tenant_id' => $tenantId, 'email' => 'sales@lagos-trading-demo.test'],
            [
                'name' => 'Lagos Trading Company',
                'company_name' => 'Lagos Trading Company Ltd',
                'phone' => '+234 802 555 0102',
                'address' => '42 Broad Street',
                'city' => 'Lagos',
                'state' => 'Lagos',
                'country' => 'Nigeria',
                'payment_terms' => 30,
                'is_active' => true,
            ]
        );

        $product = Item::firstOrCreate(
            ['tenant_id' => $tenantId, 'sku' => 'DEMO-PROD-001'],
            [
                'name' => 'Premium A4 Paper (Box)',
                'description' => 'Demo tracked inventory product',
                'type' => Item::TYPE_PRODUCT,
                'unit' => 'box',
                'selling_price' => 50000,
                'cost_price' => 35000,
                'tax_rate' => 7.5,
                'is_taxable' => true,
                'track_inventory' => true,
                'valuation_method' => Item::VALUATION_FIFO,
                'reorder_level' => 5,
                'is_active' => true,
            ]
        );

        Item::firstOrCreate(
            ['tenant_id' => $tenantId, 'sku' => 'DEMO-SVC-001'],
            [
                'name' => 'Document Delivery',
                'description' => 'Local delivery service',
                'type' => Item::TYPE_SERVICE,
                'unit' => 'job',
                'selling_price' => 12000,
                'cost_price' => 4000,
                'is_taxable' => false,
                'track_inventory' => false,
                'is_active' => true,
            ]
        );

        $warehouseId = Warehouse::defaultIdFor($tenantId);
        $stock = Inventory::firstOrCreate(
            ['tenant_id' => $tenantId, 'item_id' => $product->id, 'warehouse_id' => $warehouseId],
            ['quantity' => 40, 'reserved_quantity' => 0, 'unit_cost' => 35000]
        );
        // Its cost, so the demo sale below can be costed (stock with no cost
        // layer is refused, audit MB-31).
        if ($stock->wasRecentlyCreated) {
            app(StockValuationService::class)->addLayer($tenantId, $product->id, 40, 35000, $warehouseId, 'opening_stock');
        }

        $cashAccount = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('type', 'asset')
            ->orderBy('account_code')
            ->firstOrFail();
        $incomeAccount = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('type', 'income')
            ->orderBy('account_code')
            ->firstOrFail();
        $expenseAccount = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('type', 'expense')
            ->orderBy('account_code')
            ->firstOrFail();

        $bank = Bank::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Demo Operating Account'],
            [
                'chart_of_account_id' => $cashAccount->id,
                'bank_name' => 'Demo Bank',
                'account_number' => '0123456789',
                'account_type' => Bank::TYPE_CHECKING,
                'currency' => $tenant->currency ?: 'NGN',
                'opening_balance' => 2500000,
                'current_balance' => 2500000,
                'is_primary' => true,
                'is_active' => true,
            ]
        );

        $invoice = Invoice::firstOrCreate(
            ['tenant_id' => $tenantId, 'invoice_number' => 'DEMO-INV-001'],
            [
                'customer_id' => $customer->id,
                'invoice_date' => now()->subDays(5)->toDateString(),
                'due_date' => now()->addDays(25)->toDateString(),
                'status' => 'draft',
                'subtotal' => 100000,
                'tax_amount' => 7500,
                'discount_amount' => 0,
                'total' => 107500,
                'amount_paid' => 0,
                'balance_due' => 107500,
                'notes' => 'Demo invoice for the seeded business.',
                'created_by' => $user->id,
            ]
        );

        InvoiceItem::firstOrCreate(
            ['invoice_id' => $invoice->id, 'item_id' => $product->id],
            [
                'description' => $product->name,
                'quantity' => 2,
                'unit_price' => 50000,
                'tax_rate' => 7.5,
                'tax_amount' => 7500,
                'total' => 107500,
            ]
        );

        if ($invoice->status === 'draft') {
            $invoice->update(['status' => 'sent']);
        }

        PaymentReceived::firstOrCreate(
            ['tenant_id' => $tenantId, 'payment_number' => 'DEMO-REC-001'],
            [
                'customer_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'payment_date' => now()->subDays(2)->toDateString(),
                'amount' => 25000,
                'payment_method' => 'bank_transfer',
                'bank_id' => $bank->id,
                'reference' => 'DEMO-BANK-REC-001',
                'created_by' => $user->id,
            ]
        );

        $bill = Bill::firstOrCreate(
            ['tenant_id' => $tenantId, 'bill_number' => 'DEMO-BILL-001'],
            [
                'vendor_id' => $vendor->id,
                'bill_date' => now()->subDays(3)->toDateString(),
                'due_date' => now()->addDays(27)->toDateString(),
                'status' => 'draft',
                'subtotal' => 70000,
                'tax_amount' => 5250,
                'discount_amount' => 0,
                'total' => 75250,
                'amount_paid' => 0,
                'balance_due' => 75250,
                'notes' => 'Demo supplier bill.',
                'created_by' => $user->id,
            ]
        );

        BillItem::firstOrCreate(
            ['bill_id' => $bill->id, 'item_id' => $product->id],
            [
                'account_id' => $expenseAccount->id,
                'description' => 'Initial stock purchase',
                'quantity' => 2,
                'unit_price' => 35000,
                'tax_rate' => 7.5,
                'tax_amount' => 5250,
                'total' => 75250,
            ]
        );

        if ($bill->status === 'draft') {
            $bill->update(['status' => 'unpaid']);
        }

        Expense::firstOrCreate(
            ['tenant_id' => $tenantId, 'expense_number' => 'DEMO-EXP-001'],
            [
                'vendor_id' => $vendor->id,
                'expense_account_id' => $expenseAccount->id,
                'paid_through_id' => $cashAccount->id,
                'bank_id' => $bank->id,
                'name' => 'Office utilities',
                'expense_date' => now()->subDays(1)->toDateString(),
                'amount' => 18000,
                'tax_amount' => 0,
                'total' => 18000,
                'payment_method' => 'bank_transfer',
                'reference' => 'DEMO-EXP-PAY-001',
                'description' => 'Monthly office utilities',
                'created_by' => $user->id,
                'status' => Expense::STATUS_DRAFT,
            ]
        );

        $department = Department::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Operations'],
            ['code' => 'OPS', 'description' => 'Demo operations team', 'is_active' => true]
        );
        $designation = Designation::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Operations Manager'],
            ['department_id' => $department->id, 'code' => 'OPS-MGR', 'level' => 2, 'is_active' => true]
        );
        $employee = Employee::firstOrCreate(
            ['tenant_id' => $tenantId, 'employee_id' => 'DEMO-EMP-001'],
            [
                'department_id' => $department->id,
                'designation_id' => $designation->id,
                'first_name' => 'Ada',
                'last_name' => 'Okafor',
                'email' => 'ada.okafor@democompany.test',
                'phone' => '+234 803 555 0103',
                'gender' => 'female',
                'hire_date' => now()->subYear()->toDateString(),
                'employment_type' => 'full-time',
                'salary' => 450000,
                'salary_type' => 'monthly',
                'status' => 'active',
                'country' => 'Nigeria',
            ]
        );

        $leaveType = LeaveType::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Annual Leave'],
            ['days_per_year' => 20, 'is_paid' => true, 'is_active' => true]
        );
        Leave::firstOrCreate(
            ['tenant_id' => $tenantId, 'employee_id' => $employee->id, 'start_date' => now()->addDays(20)->toDateString()],
            [
                'leave_type_id' => $leaveType->id,
                'end_date' => now()->addDays(22)->toDateString(),
                'days' => 3,
                'reason' => 'Demo leave request',
                'status' => 'pending',
            ]
        );

        Payroll::firstOrCreate(
            ['tenant_id' => $tenantId, 'payroll_number' => 'DEMO-PAY-001'],
            [
                'employee_id' => $employee->id,
                'pay_period_start' => now()->startOfMonth()->toDateString(),
                'pay_period_end' => now()->endOfMonth()->toDateString(),
                'pay_date' => now()->endOfMonth()->toDateString(),
                'basic_salary' => 450000,
                'allowances' => 50000,
                'gross_salary' => 500000,
                'tax_deduction' => 35000,
                'other_deductions' => 5000,
                'total_deductions' => 40000,
                'net_salary' => 460000,
                'status' => Payroll::STATUS_DRAFT,
                'created_by' => $user->id,
            ]
        );

        $budget = Budget::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Demo Operating Budget', 'fiscal_year' => now()->format('Y')],
            [
                'status' => Budget::STATUS_ACTIVE,
                'description' => 'Sample annual operating budget.',
                'created_by' => $user->id,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]
        );
        BudgetLine::firstOrCreate(
            ['budget_id' => $budget->id, 'account_id' => $expenseAccount->id],
            [
                'jan' => 500000,
                'feb' => 500000,
                'mar' => 500000,
                'apr' => 500000,
                'may' => 500000,
                'jun' => 500000,
                'jul' => 500000,
                'aug' => 500000,
                'sep' => 500000,
                'oct' => 500000,
                'nov' => 500000,
                'dec' => 500000,
                'annual_total' => 6000000,
                'notes' => 'Monthly operating expenses',
            ]
        );

        $assetCategory = FixedAssetCategory::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Office Equipment'],
            [
                'code' => 'DEMO-OE',
                'description' => 'Demo office equipment assets',
                'default_useful_life' => 5,
                'default_depreciation_method' => 'straight_line',
                'asset_account_id' => $cashAccount->id,
                'depreciation_expense_account_id' => $expenseAccount->id,
            ]
        );
        FixedAsset::firstOrCreate(
            ['tenant_id' => $tenantId, 'asset_number' => 'DEMO-FA-001'],
            [
                'category_id' => $assetCategory->id,
                'name' => 'Office laptop',
                'description' => 'Demo fixed asset',
                'purchase_date' => now()->subMonths(3)->toDateString(),
                'in_service_date' => now()->subMonths(3)->toDateString(),
                'purchase_cost' => 850000,
                'funding_source' => 'opening_balance',
                'salvage_value' => 50000,
                'depreciable_amount' => 800000,
                'useful_life' => 5,
                'depreciation_method' => 'straight_line',
                'book_value' => 850000,
                'status' => FixedAsset::STATUS_ACTIVE,
                'created_by' => $user->id,
            ]
        );

        $journal = Journal::firstOrCreate(
            ['tenant_id' => $tenantId, 'journal_number' => 'DEMO-JE-001'],
            [
                'journal_date' => now()->subDays(1)->toDateString(),
                'reference' => 'DEMO-OPENING-001',
                'description' => 'Demo opening cash balance',
                'total_debit' => 250000,
                'total_credit' => 250000,
                'status' => 'posted',
                'is_posted' => true,
                'posted_at' => now(),
                'created_by' => $user->id,
            ]
        );
        JournalEntry::firstOrCreate(
            ['journal_id' => $journal->id, 'account_id' => $cashAccount->id],
            ['description' => 'Opening cash balance', 'debit' => 250000, 'credit' => 0]
        );
        JournalEntry::firstOrCreate(
            ['journal_id' => $journal->id, 'account_id' => $incomeAccount->id],
            ['description' => 'Opening balance offset', 'debit' => 0, 'credit' => 250000]
        );

        $plan = Plan::where('slug', 'professional')->firstOrFail();
        Subscription::firstOrCreate(
            ['tenant_id' => $tenantId, 'plan_id' => $plan->id],
            [
                'billing_cycle' => Subscription::CYCLE_MONTHLY,
                'status' => Subscription::STATUS_ACTIVE,
                'amount' => $plan->monthly_price,
                'currency' => $tenant->currency ?: 'NGN',
                'starts_at' => now(),
                'ends_at' => now()->addYear(),
                'metadata' => ['demo' => true],
            ]
        );

        $this->command?->info('Demo records are ready for sales, purchasing, inventory, HR, payroll, accounting, banking, budgets, fixed assets, tax, and subscriptions.');
    }
}
