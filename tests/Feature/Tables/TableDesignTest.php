<?php

namespace Tests\Feature\Tables;

use App\Livewire\ActivityLogs\ActivityLogsTable;
use App\Livewire\Banks\BanksTable;
use App\Livewire\Bills\BillsTable;
use App\Livewire\Budgets\BudgetsTable;
use App\Livewire\ChartOfAccounts\ChartOfAccountsTable;
use App\Livewire\Customers\CustomersTable;
use App\Livewire\Employees\EmployeesTable;
use App\Livewire\Invoices\InvoicesTable;
use App\Livewire\Items\ItemsTable;
use App\Livewire\PaymentsMade\PaymentsMadeTable;
use App\Livewire\PaymentsReceived\PaymentsReceivedTable;
use App\Livewire\RecurrentBills\RecurrentBillsTable;
use App\Livewire\TaxGroups\TaxGroupsTable;
use App\Livewire\Vendors\VendorsTable;
use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\Bank;
use App\Models\Bill;
use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\FixedAsset;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Journal;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\Payroll;
use App\Models\PayrollBatch;
use App\Models\RecurrentBill;
use App\Models\TaxGroup;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Models\VendorAdvanceApplication;
use App\Models\Warehouse;
use App\Services\BudgetService;
use App\Services\DepreciationService;
use App\Services\JournalService;
use App\Support\Figure;
use App\Support\JournalSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Tables plan: every table screen uses the shared design (x-table and
 * friends), except those still on the to-do list; and the Invoices list,
 * the pilot, behaves as the plan says.
 */
class TableDesignTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Printed documents (and their shared layout and the invoice template
     * preview), PDFs and emails have their own layout. The home page's tables
     * are drawn examples of the app, not screens (home page plan H3), and
     * the invoice document is the printed invoice (invoice rebuild).
     */
    private const NOT_SCREENS = '#(^vendor/|pdf|print|mail|statements/document|waybill|payslip|components/table/|^documents/|templates/preview|^welcome\.blade\.php$|^invoices/document\.blade\.php$|^expenses/document\.blade\.php$|^bills/document\.blade\.php$)#';

    /** @return list<string> */
    private function todo(): array
    {
        return collect(file(base_path('tests/Feature/Tables/tables-todo.txt'), FILE_IGNORE_NEW_LINES))
            ->map(fn ($l) => trim($l))->filter(fn ($l) => $l !== '' && ! str_starts_with($l, '#'))->values()->all();
    }

    /** @return list<string> view paths (relative) that contain a table */
    private function tableScreens(): array
    {
        $out = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            $rel = str_replace('\\', '/', $file->getRelativePathname());
            if (! str_ends_with($rel, '.blade.php') || preg_match(self::NOT_SCREENS, $rel)) {
                continue;
            }
            if (str_contains(File::get($file->getPathname()), '<table')) {
                $out[] = $rel;
            }
        }
        sort($out);

        return $out;
    }

    private function usesNewDesign(string $html): bool
    {
        return str_contains($html, '<x-table') && ! preg_match('/<th[^>]*\buppercase\b/', $html)
            && ! str_contains($html, '<thead class="bg-gray-50');
    }

    public function test_every_table_screen_uses_the_shared_design_or_is_on_the_todo_list(): void
    {
        $todo = $this->todo();
        $old = [];
        foreach ($this->tableScreens() as $rel) {
            if (! in_array($rel, $todo, true) && ! $this->usesNewDesign(File::get(resource_path("views/{$rel}")))) {
                $old[] = $rel;
            }
        }

        $this->assertSame([], $old, 'These table screens skip the shared table design (x-table). Use it, or add them to tables-todo.txt.');
    }

    public function test_the_todo_list_only_lists_screens_still_to_do(): void
    {
        $stale = [];
        foreach ($this->todo() as $rel) {
            $path = resource_path("views/{$rel}");
            if (! File::exists($path) || preg_match(self::NOT_SCREENS, $rel) || ! str_contains(File::get($path), '<table') || $this->usesNewDesign(File::get($path))) {
                $stale[] = $rel;
            }
        }

        $this->assertSame([], $stale, 'Finished or removed: take these off tables-todo.txt.');
    }

    // ── The pilot: Invoices ─────────────────────────────────────

    private function invoices(): void
    {
        $tid = $this->tenant->id;
        $bala = Customer::factory()->create(['tenant_id' => $tid, 'name' => 'Bala Stores']);
        $hauwa = Customer::factory()->create(['tenant_id' => $tid, 'name' => 'Hauwa Traders']);
        Invoice::withoutEvents(function () use ($tid, $bala, $hauwa) {
            $make = fn ($n, $c, $status, $total, $balance, $date, $due) => Invoice::factory()->create([
                'tenant_id' => $tid, 'customer_id' => $c->id, 'invoice_number' => $n, 'status' => $status,
                'total' => $total, 'balance_due' => $balance, 'invoice_date' => $date, 'due_date' => $due,
            ]);
            $make('INV-1', $bala, 'paid', 1000, 0, '2026-10-01', '2026-10-31');
            $make('INV-2', $bala, 'sent', 2000, 2000, '2026-08-01', '2026-08-31'); // overdue
            $make('INV-3', $hauwa, 'partial', 3000, 1000, '2026-10-05', '2026-11-04');
            $make('INV-4', $hauwa, 'draft', 400, 400, '2026-10-06', '2026-11-05');
        });
    }

    public function test_invoices_list_tabs_totals_and_filters(): void
    {
        $this->travelTo('2026-10-10 09:00');
        $this->createAuthenticatedUser(['view invoices', 'create invoices', 'send invoices', 'edit invoices', 'delete invoices']);
        $this->invoices();

        $t = Livewire::test(InvoicesTable::class);
        $tabs = $t->viewData('tabs');
        $this->assertSame([4, 1, 2, 1, 1], [$tabs['']['count'], $tabs['draft']['count'], $tabs['unpaid']['count'], $tabs['overdue']['count'], $tabs['paid']['count']]);
        $this->assertArrayNotHasKey('cancelled', $tabs, 'Cancelled only shows when there are some');
        $this->assertEquals(6400, $t->viewData('totals')->total);
        $this->assertEquals(3400, $t->viewData('totals')->balance);

        $t->set('tab', 'overdue');
        $this->assertSame(['INV-2'], $t->viewData('invoices')->pluck('invoice_number')->all());
        $this->assertEquals(2000, $t->viewData('totals')->balance);

        $t->set('tab', '')->set('search', 'Hauwa');
        $this->assertEqualsCanonicalizing(['INV-3', 'INV-4'], $t->viewData('invoices')->pluck('invoice_number')->all());
        $t->set('search', '3000');
        $this->assertSame(['INV-3'], $t->viewData('invoices')->pluck('invoice_number')->all(), 'search by amount');

        $t->set('search', '')->set('period', 'last_month');
        $this->assertSame(0, $t->viewData('invoices')->total());
        $t->call('clearFilters');
        $this->assertSame(4, $t->viewData('invoices')->total());
    }

    public function test_sorting_only_accepts_listed_columns_and_defaults_to_25_rows(): void
    {
        $this->createAuthenticatedUser(['view invoices']);
        $this->invoices();

        $t = Livewire::test(InvoicesTable::class)->assertSet('sortField', 'invoice_date')->assertSet('perPage', 25);
        $t->call('sortBy', 'total');
        $this->assertSame(['INV-3', 'INV-2', 'INV-1', 'INV-4'], $t->viewData('invoices')->pluck('invoice_number')->all());
        $t->call('sortBy', 'total');
        $this->assertSame('asc', $t->get('sortDirection'));
        $t->call('sortBy', 'customer_id; drop table invoices')->assertSet('sortField', 'total');
    }

    public function test_page_shows_the_design_and_menu_follows_permissions(): void
    {
        $this->travelTo('2026-10-10 09:00');
        $this->createAuthenticatedUser(['view invoices']);
        $this->invoices();

        $html = $this->get(route('invoices.index'))->assertOk()->getContent();
        $this->assertStringContainsString('class="tbl"', $html);
        $this->assertStringContainsString('Amounts in', $html);
        $this->assertStringContainsString('Balance due', $html);
        $this->assertStringContainsString('Total of 4 invoices', $html);
        $this->assertStringContainsString('aria-sort="descending"', $html);
        $this->assertStringContainsString('Actions for INV-2', $html);
        // A viewer: no ticks, no edit, delete or new.
        $this->assertStringNotContainsString('tbl-checkbox', $html);
        $this->assertStringNotContainsString('>Delete<', $html);
        $this->assertStringNotContainsString('New invoice', $html);
        $this->assertStringNotContainsString('UPPERCASE', $html);
    }

    public function test_row_delete_and_bulk_actions_check_permissions(): void
    {
        $this->createAuthenticatedUser(['view invoices']);
        $this->invoices();
        $draft = Invoice::where('invoice_number', 'INV-4')->first();

        Livewire::test(InvoicesTable::class)->call('deleteOne', $draft->id)->assertForbidden();
        Livewire::test(InvoicesTable::class)->set('selectedItems', [(string) $draft->id])->call('runBulk', 'delete')->assertForbidden();
        $this->assertNotNull($draft->fresh());

        $this->user->givePermissionTo(Permission::findOrCreate('delete invoices', 'web'));
        Livewire::test(InvoicesTable::class)->call('deleteOne', $draft->id)->assertSee('Deleted INV-4.');
        $this->assertNull(Invoice::find($draft->id));
    }

    // ── T2: sales lists ─────────────────────────────────────────

    public function test_customers_list_tabs_and_amount_owed(): void
    {
        $this->createAuthenticatedUser(['view customers', 'edit customers']);
        $this->invoices();
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Old Client', 'is_active' => false]);

        $t = Livewire::test(CustomersTable::class)->assertSet('sortField', 'name')->assertSet('perPage', 25);
        $tabs = $t->viewData('tabs');
        $this->assertSame([3, 2, 2, 1], [$tabs['']['count'], $tabs['active']['count'], $tabs['owing']['count'], $tabs['inactive']['count']]);
        $this->assertEquals(3000, $t->viewData('totals')->owed, 'Draft and paid invoices are not owed');

        $t->set('tab', 'inactive');
        $this->assertSame(['Old Client'], $t->viewData('customers')->pluck('name')->all());
        $t->set('tab', 'owing')->set('search', 'Hauwa');
        $this->assertSame(['Hauwa Traders'], $t->viewData('customers')->pluck('name')->all());
        $this->assertEquals(1000, $t->viewData('totals')->owed);
    }

    public function test_payments_received_tabs_split_invoice_payments_and_deposits(): void
    {
        $this->createAuthenticatedUser(['view payments-received']);
        $this->invoices();
        $tid = $this->tenant->id;
        $inv = Invoice::where('invoice_number', 'INV-3')->first();
        PaymentReceived::withoutEvents(function () use ($tid, $inv) {
            $make = fn ($n, $amount, $deposit, $unused, $invoice = null) => PaymentReceived::create([
                'tenant_id' => $tid, 'customer_id' => $inv->customer_id, 'invoice_id' => $invoice?->id, 'payment_number' => $n,
                'payment_date' => '2026-10-07', 'amount' => $amount, 'payment_method' => 'bank_transfer',
                'is_deposit' => $deposit, 'unused_amount' => $unused,
            ]);
            $make('PAY-1', 2000, false, 0, $inv);
            $make('PAY-2', 500, true, 500);
            $make('PAY-3', 300, true, 0);
        });

        $t = Livewire::test(PaymentsReceivedTable::class);
        $tabs = $t->viewData('tabs');
        $this->assertSame([3, 1, 2, 1], [$tabs['']['count'], $tabs['payments']['count'], $tabs['deposits']['count'], $tabs['unused']['count']]);
        $this->assertEquals(2800, $t->viewData('totals')->amount);
        $t->set('tab', 'unused');
        $this->assertSame(['PAY-2'], $t->viewData('payments')->pluck('payment_number')->all());

        $html = $this->get(route('payments-received.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Total of 3 payments', $html);
        $this->assertStringContainsString('Bank transfer', $html);
    }

    public function test_every_sales_list_page_uses_the_shared_design(): void
    {
        $this->createSuperAdmin();
        $this->invoices();
        foreach (['customers', 'quotations', 'sales-orders', 'sales-receipts', 'delivery-notes', 'credit-notes', 'payments-received'] as $list) {
            $html = $this->get(route($list.'.index'))->assertOk()->getContent();
            $this->assertStringContainsString('tbl-wrap', $html, $list);
            $this->assertStringNotContainsString('uppercase tracking-wider', $html, $list);
        }
    }

    // ── T3: purchases and expenses ──────────────────────────────

    private function bills(): void
    {
        $tid = $this->tenant->id;
        $olam = Vendor::factory()->create(['tenant_id' => $tid, 'name' => 'Olam Nigeria', 'phone' => '08031234567']);
        $pz = Vendor::factory()->create(['tenant_id' => $tid, 'name' => 'PZ Cussons', 'email' => 'accounts@pz.example']);
        Bill::withoutEvents(function () use ($tid, $olam, $pz) {
            $make = fn ($n, $v, $status, $total, $balance, $date, $due) => Bill::factory()->create([
                'tenant_id' => $tid, 'vendor_id' => $v->id, 'bill_number' => $n, 'status' => $status,
                'total' => $total, 'amount_paid' => $total - $balance, 'balance_due' => $balance, 'bill_date' => $date, 'due_date' => $due,
            ]);
            $make('BILL-1', $olam, 'paid', 1000, 0, '2026-10-01', '2026-10-31');
            $make('BILL-2', $olam, 'unpaid', 2000, 2000, '2026-08-01', '2026-08-31'); // overdue
            $make('BILL-3', $pz, 'partial', 3000, 1000, '2026-10-05', '2026-11-04');
            $make('BILL-4', $pz, 'draft', 400, 400, '2026-10-06', '2026-11-05');
        });
    }

    public function test_bills_list_tabs_and_to_pay_total_leave_out_drafts(): void
    {
        $this->travelTo('2026-10-10 09:00');
        $this->createAuthenticatedUser(['view bills']);
        $this->bills();

        $t = Livewire::test(BillsTable::class)->assertSet('sortField', 'bill_date')->assertSet('perPage', 25);
        $tabs = $t->viewData('tabs');
        $this->assertSame([4, 1, 2, 1, 1], [$tabs['']['count'], $tabs['draft']['count'], $tabs['unpaid']['count'], $tabs['overdue']['count'], $tabs['paid']['count']]);
        $this->assertEquals(6400, $t->viewData('totals')->total);
        $this->assertEquals(3000, $t->viewData('totals')->balance, 'The draft is not owed yet');

        $t->set('tab', 'overdue');
        $this->assertSame(['BILL-2'], $t->viewData('bills')->pluck('bill_number')->all());
        $t->call('sortBy', 'vendor_id; drop table bills')->assertSet('sortField', 'bill_date');
    }

    public function test_vendor_search_finds_phone_and_email_and_tabs_count_what_you_owe(): void
    {
        $this->createAuthenticatedUser(['view vendors']);
        $this->bills();

        $t = Livewire::test(VendorsTable::class)->assertSet('sortField', 'name');
        $this->assertSame(2, $t->viewData('tabs')['owed']['count']);
        $this->assertEquals(3000, $t->viewData('totals')->owed);
        $t->set('search', '0803123');
        $this->assertSame(['Olam Nigeria'], $t->viewData('vendors')->pluck('name')->all());
        $t->set('search', 'accounts@pz');
        $this->assertSame(['PZ Cussons'], $t->viewData('vendors')->pluck('name')->all());
    }

    public function test_bulk_delete_of_payments_made_keeps_an_advance_that_has_been_used(): void
    {
        $this->createAuthenticatedUser(['view payments-made', 'delete payments-made']);
        $this->bills();
        $tid = $this->tenant->id;
        $bill = Bill::where('bill_number', 'BILL-3')->first();
        [$used, $fresh] = PaymentMade::withoutEvents(fn () => [
            PaymentMade::create(['tenant_id' => $tid, 'vendor_id' => $bill->vendor_id, 'payment_number' => 'ADV-1', 'payment_date' => '2026-10-01',
                'amount' => 500, 'payment_method' => 'cash', 'is_advance' => true, 'unused_amount' => 0]),
            PaymentMade::create(['tenant_id' => $tid, 'vendor_id' => $bill->vendor_id, 'payment_number' => 'ADV-2', 'payment_date' => '2026-10-02',
                'amount' => 300, 'payment_method' => 'cash', 'is_advance' => true, 'unused_amount' => 300]),
        ]);
        VendorAdvanceApplication::create(['tenant_id' => $tid, 'vendor_id' => $bill->vendor_id, 'advance_payment_id' => $used->id,
            'bill_id' => $bill->id, 'amount' => 500, 'application_date' => '2026-10-03']);

        $t = Livewire::test(PaymentsMadeTable::class);
        $this->assertSame([2, 2, 1], [$t->viewData('tabs')['advances']['count'], $t->viewData('tabs')['']['count'], $t->viewData('tabs')['unused']['count']]);
        $t->set('selectedItems', [(string) $used->id, (string) $fresh->id])->call('runBulk', 'delete');
        $this->assertNotNull(PaymentMade::find($used->id), 'A used advance stays');
        $this->assertNull(PaymentMade::find($fresh->id));
    }

    public function test_recurrent_bills_show_a_monthly_figure_and_pause_needs_permission(): void
    {
        $this->createAuthenticatedUser(['view recurrent-bills']);
        $tid = $this->tenant->id;
        $vendor = Vendor::factory()->create(['tenant_id' => $tid]);
        $make = fn ($name, $freq, $total, $status = 'active') => RecurrentBill::create(['tenant_id' => $tid, 'vendor_id' => $vendor->id,
            'profile_name' => $name, 'frequency' => $freq, 'start_date' => '2026-01-01', 'next_bill_date' => '2026-11-01',
            'subtotal' => $total, 'tax_amount' => 0, 'total' => $total, 'status' => $status]);
        $rent = $make('Rent', 'monthly', 100000);
        $make('Licence', 'yearly', 120000);
        $make('Guards', 'quarterly', 30000, 'paused');

        $t = Livewire::test(RecurrentBillsTable::class);
        $this->assertEquals(110000, $t->viewData('totals')->per_month, 'Paused profiles are left out');

        $t->call('toggleOne', $rent->id)->assertForbidden();
        $this->user->givePermissionTo(Permission::findOrCreate('edit recurrent-bills', 'web'));
        Livewire::test(RecurrentBillsTable::class)->call('toggleOne', $rent->id);
        $this->assertSame('paused', $rent->fresh()->status);
    }

    public function test_every_purchase_list_page_uses_the_shared_design(): void
    {
        $this->createSuperAdmin();
        $this->bills();
        foreach (['vendors', 'purchase-orders', 'bills', 'payments-made', 'expenses', 'vendor-credits', 'supplier-advances', 'recurrent-bills', 'recurrent-expenses'] as $list) {
            $html = $this->get(route($list.'.index'))->assertOk()->getContent();
            $this->assertStringContainsString('tbl-wrap', $html, $list);
            $this->assertStringNotContainsString('uppercase tracking-wider', $html, $list);
        }
    }

    // ── T4: stock, banking and accounting ───────────────────────

    public function test_items_count_stock_across_warehouses_and_keep_items_in_use(): void
    {
        $this->createAuthenticatedUser(['view items', 'delete items']);
        $tid = $this->tenant->id;
        $main = Warehouse::firstOrCreate(['tenant_id' => $tid, 'code' => 'MAIN'], ['name' => 'Main', 'is_default' => true, 'is_active' => true]);
        $shop = Warehouse::create(['tenant_id' => $tid, 'name' => 'Shop', 'code' => 'SHOP', 'is_default' => false, 'is_active' => true]);
        $rice = Item::factory()->create(['tenant_id' => $tid, 'name' => 'Rice', 'type' => 'product', 'track_inventory' => true, 'reorder_level' => 10]);
        $oil = Item::factory()->create(['tenant_id' => $tid, 'name' => 'Oil', 'type' => 'product', 'track_inventory' => true, 'reorder_level' => 10]);
        Item::factory()->create(['tenant_id' => $tid, 'name' => 'Delivery', 'type' => 'service']);
        Inventory::withoutEvents(function () use ($tid, $main, $shop, $rice, $oil) {
            Inventory::create(['tenant_id' => $tid, 'item_id' => $rice->id, 'warehouse_id' => $main->id, 'quantity' => 6, 'unit_cost' => 100]);
            Inventory::create(['tenant_id' => $tid, 'item_id' => $rice->id, 'warehouse_id' => $shop->id, 'quantity' => 9, 'unit_cost' => 100]);
            Inventory::create(['tenant_id' => $tid, 'item_id' => $oil->id, 'warehouse_id' => $main->id, 'quantity' => 4, 'unit_cost' => 100]);
        });

        $t = Livewire::test(ItemsTable::class);
        $tabs = $t->viewData('tabs');
        $this->assertSame([3, 2, 1, 1], [$tabs['']['count'], $tabs['products']['count'], $tabs['services']['count'], $tabs['low']['count']]);
        $this->assertEquals(15, $t->viewData('items')->firstWhere('id', $rice->id)->on_hand, 'Both warehouses count');
        $t->set('tab', 'low');
        $this->assertSame(['Oil'], $t->viewData('items')->pluck('name')->all());
        $t->call('sortBy', 'on_hand');
        $this->assertSame('on_hand', $t->get('sortField'));
    }

    public function test_bank_accounts_with_money_recorded_through_them_are_kept(): void
    {
        $this->createAuthenticatedUser(['view banks', 'delete banks']);
        $this->bills();
        $tid = $this->tenant->id;
        $used = Bank::factory()->create(['tenant_id' => $tid, 'name' => 'Used account']);
        $empty = Bank::factory()->create(['tenant_id' => $tid, 'name' => 'Empty account']);
        PaymentMade::withoutEvents(fn () => PaymentMade::create(['tenant_id' => $tid, 'vendor_id' => Vendor::value('id'), 'payment_number' => 'PM-1',
            'payment_date' => '2026-10-01', 'amount' => 100, 'payment_method' => 'bank_transfer', 'bank_id' => $used->id]));

        Livewire::test(BanksTable::class)->call('deleteOne', $used->id)->assertSee('has money recorded through it');
        $this->assertNotNull($used->fresh());
        Livewire::test(BanksTable::class)->call('deleteOne', $empty->id)->assertSee('Deleted Empty account.');
        $this->assertNull(Bank::find($empty->id));

        $this->delete(route('banks.destroy', $used))->assertSessionHas('error');
        $this->assertNotNull($used->fresh(), 'The bank page follows the same rule');
    }

    public function test_chart_of_accounts_has_a_tab_per_type_and_keeps_built_in_accounts(): void
    {
        $this->createAuthenticatedUser(['view chart-of-accounts', 'delete chart-of-accounts']);
        $tid = $this->tenant->id;
        ChartOfAccount::query()->forceDelete();
        $cash = ChartOfAccount::factory()->system()->create(['tenant_id' => $tid, 'account_code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'current_balance' => 500]);
        ChartOfAccount::factory()->create(['tenant_id' => $tid, 'account_code' => '1100', 'name' => 'Bank', 'type' => 'asset', 'current_balance' => 250]);
        $spare = ChartOfAccount::factory()->create(['tenant_id' => $tid, 'account_code' => '6100', 'name' => 'Rent', 'type' => 'expense']);

        $t = Livewire::test(ChartOfAccountsTable::class)->assertSet('sortField', 'account_code');
        $this->assertSame([3, 2, 1], [$t->viewData('tabs')['']['count'], $t->viewData('tabs')['asset']['count'], $t->viewData('tabs')['expense']['count']]);
        $this->assertNull($t->viewData('total'), 'No total across different types');
        $t->set('tab', 'asset');
        $this->assertEquals(750, $t->viewData('total'));

        $t->call('deleteOne', $cash->id)->assertSee('MyBooks needs');
        $this->assertNotNull($cash->fresh());
        $t->call('deleteOne', $spare->id);
        $this->assertNull(ChartOfAccount::find($spare->id));
    }

    public function test_tax_groups_used_on_items_are_kept(): void
    {
        $this->createAuthenticatedUser(['view tax-rates', 'delete tax-rates']);
        $tid = $this->tenant->id;
        $used = TaxGroup::create(['tenant_id' => $tid, 'name' => 'VAT and WHT', 'code' => 'VW', 'is_active' => true]);
        $free = TaxGroup::create(['tenant_id' => $tid, 'name' => 'Unused', 'code' => 'UN', 'is_active' => true]);
        Item::factory()->create(['tenant_id' => $tid, 'tax_group_id' => $used->id]);

        Livewire::test(TaxGroupsTable::class)->set('selectedItems', [(string) $used->id, (string) $free->id])->call('runBulk', 'delete')
            ->assertSee('Skipped 1 used on items');
        $this->assertNotNull($used->fresh());
        $this->assertNull(TaxGroup::find($free->id));
    }

    public function test_budgets_list_shows_the_years_total(): void
    {
        $this->createAuthenticatedUser(['view budgets']);
        $tid = $this->tenant->id;
        $account = ChartOfAccount::factory()->create(['tenant_id' => $tid, 'type' => 'expense']);
        $budget = Budget::create(['tenant_id' => $tid, 'name' => 'Budget 2026', 'fiscal_year' => 2026, 'status' => 'active', 'created_by' => $this->user->id]);
        $budget->lines()->create(['account_id' => $account->id, 'annual_total' => 1200000] + array_fill_keys(['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'], 100000));

        $t = Livewire::test(BudgetsTable::class)->assertSee('Budget 2026')->assertSee('1,200,000.00')->assertSee('In use');
        $this->assertSame(1, $t->viewData('tabs')['active']['count']);
    }

    public function test_every_stock_banking_and_accounting_page_uses_the_shared_design(): void
    {
        $this->createSuperAdmin();
        foreach (['items', 'item-categories', 'inventory', 'warehouses', 'stock-transfers', 'assembly-orders', 'bill-of-materials', 'banks',
            'chart-of-accounts', 'journals', 'tax-rates', 'tax-groups', 'accrual-schedules', 'fixed-assets', 'fixed-asset-categories', 'accounting-periods'] as $list) {
            $html = $this->get(route($list.'.index'))->assertOk()->getContent();
            $this->assertStringContainsString('tbl-', $html, $list);
            $this->assertStringNotContainsString('uppercase tracking-wider', $html, $list);
        }
    }

    // ---- T5: payroll, HR, settings and admin lists ----

    private function employee(string $first, string $status): Employee
    {
        return Employee::withoutEvents(fn () => Employee::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => 'EMP-'.$first, 'first_name' => $first, 'last_name' => 'Bello',
            'email' => strtolower($first).'@example.com', 'hire_date' => now()->subYear(), 'status' => $status,
        ]));
    }

    public function test_employees_tabs_put_people_who_left_together_and_keep_paid_staff(): void
    {
        $this->createSuperAdmin();
        $amina = $this->employee('Amina', 'active');
        $this->employee('Bayo', 'on-leave');
        $this->employee('Chidi', 'terminated');
        $this->employee('Dauda', 'resigned');
        $batch = PayrollBatch::create(['tenant_id' => $this->tenant->id, 'batch_number' => 'PBN-T5', 'pay_period_start' => now()->startOfMonth(),
            'pay_period_end' => now()->endOfMonth(), 'status' => PayrollBatch::STATUS_PAID, 'created_by' => $this->user->id]);
        Payroll::withoutEvents(fn () => Payroll::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $amina->id, 'payroll_batch_id' => $batch->id, 'payroll_number' => 'PAY-T5',
            'pay_period_start' => now()->startOfMonth(), 'pay_period_end' => now()->endOfMonth(), 'pay_date' => now(),
            'basic_salary' => 1000, 'gross_salary' => 1000, 'total_deductions' => 0, 'net_salary' => 1000, 'status' => 'paid', 'created_by' => $this->user->id,
        ]));

        $list = Livewire::test(EmployeesTable::class)
            ->assertViewHas('tabs', fn ($t) => $t['']['count'] === 4 && $t['active']['count'] === 1 && $t['on-leave']['count'] === 1 && $t['left']['count'] === 2)
            ->set('tab', 'left')->assertSee('Chidi')->assertSee('Dauda')->assertDontSee('Amina');

        // Searching a full name matches first and last name together.
        $list->set('tab', '')->set('search', 'Bayo Bello')->assertSee('Bayo')->assertDontSee('Chidi');

        // Someone paid through payroll is kept; "back at work" only moves people on leave.
        $list->call('deleteOne', $amina->id)->assertSet('errorMessage', fn ($m) => str_contains($m, 'paid through payroll'));
        $this->assertNotNull($amina->fresh());
        $ids = Employee::pluck('id')->map(fn ($id) => (string) $id)->all();
        $list->set('selectedItems', $ids)->set('bulkAction', 'activate')->call('applyBulkAction');
        $this->assertSame(['active', 'active', 'terminated', 'resigned'], Employee::orderBy('first_name')->pluck('status')->all());
    }

    public function test_activity_log_tabs_group_actions_and_filter_by_record(): void
    {
        $this->createSuperAdmin();
        $log = fn (string $action, ?string $type, string $text) => ActivityLog::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'user_name' => $this->user->name,
            'action' => $action, 'model_type' => $type, 'model_name' => null, 'description' => 'T5LOG '.$text,
        ]);
        $log(ActivityLog::ACTION_CREATED, Invoice::class, 'made an invoice');
        $log(ActivityLog::ACTION_UPDATED, Customer::class, 'changed a customer');
        $log(ActivityLog::ACTION_LOGIN, null, 'signed in');
        $log(ActivityLog::ACTION_LOGIN_FAILED, null, 'wrong password');
        $log(ActivityLog::ACTION_PASSWORD_CHANGED, null, 'new password');

        Livewire::test(ActivityLogsTable::class)->set('search', 'T5LOG')
            ->assertViewHas('tabs', fn ($t) => $t['']['count'] === 5 && $t['changes']['count'] === 2 && $t['signins']['count'] === 2
                && $t['signins']['alert'] && $t['security']['count'] === 1 && ! $t['security']['alert'])
            ->assertSee('Failed sign-in')
            ->set('module', 'Invoice')->assertSee('made an invoice')->assertDontSee('changed a customer')
            ->set('module', '')->set('tab', 'signins')->assertSee('wrong password')->assertDontSee('new password');
    }

    public function test_admin_businesses_list_has_status_tabs_with_counts(): void
    {
        $this->createSuperAdmin();
        Tenant::withoutEvents(fn () => Tenant::factory()->create(['name' => 'Zaria Mills']));
        $admin = AdminUser::create(['name' => 'Ops', 'email' => 'ops-t5@example.com', 'password' => 'Secret-123!', 'is_active' => true, 'role' => 'super_admin']);

        $html = $this->actingAsPlatformAdmin($admin)->get(route('admin.tenants.list', ['status' => 'no_subscription']))->assertOk()
            ->assertSee('Zaria Mills')->assertDontSee($this->tenant->name)->getContent();
        $this->assertStringContainsString('tbl-', $html);
        $this->assertMatchesRegularExpression('/No plan\s*<span[^>]*>1</', $html);

        foreach (['admin.users.index', 'admin.data-requests.index', 'admin.messaging.index'] as $page) {
            $html = $this->actingAsPlatformAdmin($admin)->get(route($page))->assertOk()->getContent();
            $this->assertStringContainsString('tbl-', $html, $page);
            $this->assertStringNotContainsString('bg-gray-750', $html, $page);
        }
    }

    public function test_every_payroll_hr_and_settings_page_uses_the_shared_design(): void
    {
        $this->createSuperAdmin();
        foreach (['employees.index', 'departments.index', 'designations.index', 'leaves.index', 'leave-types.index', 'payroll.index',
            'salary-structures.index', 'allowances.index', 'deductions.index', 'payroll.liabilities', 'payroll.tax-templates',
            'settings.users', 'settings.roles', 'activity-logs.index', 'imports.index', 'exports.index'] as $page) {
            $html = $this->get(route($page))->assertOk()->getContent();
            $this->assertStringContainsString('tbl-', $html, $page);
            $this->assertStringNotContainsString('uppercase tracking-wider', $html, $page);
        }
    }

    // ---- T6: reports ----

    /** Post a balanced journal: [[account code, debit, credit], ...]. */
    private function postJournal(string $date, array $lines): void
    {
        $service = app(JournalService::class);
        $journal = Journal::create(['tenant_id' => $this->tenant->id, 'journal_number' => Journal::generateNumber($this->tenant->id),
            'journal_date' => $date, 'description' => 'T6', 'status' => 'posted', 'is_posted' => true, 'posted_at' => now()]);
        foreach ($lines as [$code, $debit, $credit]) {
            $service->createEntry($journal, $code, $debit, $credit, 'T6');
        }
        $journal->updateTotals();
        $journal->save();
        $service->updateAccountBalances($journal);
    }

    private function code(string $type, ?string $sub = null): string
    {
        return ChartOfAccount::where('tenant_id', $this->tenant->id)->where('type', $type)
            ->when($sub, fn ($q) => $q->where('sub_type', $sub))->orderBy('account_code')->value('account_code');
    }

    public function test_report_figures_use_brackets_dashes_and_plain_percentages(): void
    {
        $this->assertSame('1,234.50', Figure::show(1234.5));
        $this->assertSame('(1,234.50)', Figure::show(-1234.5));
        $this->assertSame('-1,234.50', Figure::show(-1234.5, false));
        $this->assertSame('—', Figure::show(0.001));
        $this->assertSame('tbl-zero', Figure::tone(0));
        $this->assertSame('12.5%', Figure::percent(25, 200));
        $this->assertSame('—', Figure::percent(25, 0));
    }

    public function test_trial_balance_shows_each_balance_on_one_side_and_the_pl_lists_accounts(): void
    {
        $this->createSuperAdmin();
        [$cash, $sales, $cost] = [$this->code('asset', 'cash') ?? $this->code('asset'), $this->code('income'), $this->code('expense')];
        $this->postJournal('2026-03-01', [[$cash, 1000, 0], [$sales, 0, 1000]]);
        $this->postJournal('2026-03-02', [[$cost, 400, 0], [$cash, 0, 400]]);

        $tb = $this->get(route('reports.trial-balance', ['as_of' => '2026-03-31']))->assertOk()->assertSee('Trial balance');
        $cashRow = $tb->viewData('accounts')->firstWhere('account_code', $cash);
        $this->assertEqualsWithDelta(600, $cashRow->balance_debit, 0.001, 'cash shows its balance, not 1,000 and 400');
        $this->assertEqualsWithDelta(0, $cashRow->balance_credit, 0.001);
        $this->assertEqualsWithDelta(1000, $tb->viewData('totalDebits'), 0.001);
        $this->assertEqualsWithDelta(1000, $tb->viewData('totalCredits'), 0.001);

        $pl = $this->get(route('reports.profit-loss', ['start_date' => '2026-03-01', 'end_date' => '2026-03-31']))->assertOk();
        $lines = $pl->viewData('lines');
        $this->assertEqualsWithDelta(1000, $lines['income']->sum('balance'), 0.001);
        $this->assertEqualsWithDelta(400, $lines['operating']->concat($lines['payroll'])->concat($lines['cogs'])->sum('balance'), 0.001);
        $pl->assertSee('600.00')->assertSee(e(route('reports.general-ledger', ['account_id' => ChartOfAccount::where('tenant_id', $this->tenant->id)->where('account_code', $sales)->value('id'), 'start_date' => '2026-03-01', 'end_date' => '2026-03-31'])), false);
    }

    public function test_sales_and_tax_reports_count_issued_documents_up_to_the_last_day(): void
    {
        $this->createSuperAdmin();
        $tid = $this->tenant->id;
        $bala = Customer::factory()->create(['tenant_id' => $tid, 'name' => 'Bala Stores']);
        Invoice::withoutEvents(function () use ($tid, $bala) {
            foreach ([['S-1', 'unpaid', '2026-09-30 00:00:00'], ['S-2', 'draft', '2026-09-10'], ['S-3', 'cancelled', '2026-09-11'], ['S-4', 'paid', '2026-09-01']] as [$n, $st, $d]) {
                Invoice::factory()->create(['tenant_id' => $tid, 'customer_id' => $bala->id, 'invoice_number' => $n, 'status' => $st,
                    'subtotal' => 1000, 'tax_amount' => 75, 'total' => 1075, 'amount_paid' => 0, 'balance_due' => 1075, 'invoice_date' => $d, 'due_date' => $d]);
            }
        });
        $period = ['start_date' => '2026-09-01', 'end_date' => '2026-09-30'];

        $r = $this->get(route('reports.sales-by-customer', $period))->assertOk();
        $this->assertEqualsWithDelta(2150, $r->viewData('totalSales'), 0.001, 'paid and unpaid, including the last day; not draft or cancelled');

        $t = $this->get(route('reports.tax-liability', $period))->assertOk();
        $this->assertEqualsWithDelta(150, $t->viewData('totalTaxCollected'), 0.001, 'unpaid invoices were missing');
    }

    public function test_payroll_reports_leave_out_draft_runs(): void
    {
        $this->createSuperAdmin();
        $amina = $this->employee('Amina', 'active');
        foreach ([['PB-1', 'paid', 'paid'], ['PB-2', 'draft', 'draft']] as $i => [$no, $bs, $ps]) {
            $batch = PayrollBatch::create(['tenant_id' => $this->tenant->id, 'batch_number' => $no, 'pay_period_start' => '2026-0'.($i + 7).'-01',
                'pay_period_end' => '2026-0'.($i + 7).'-28', 'status' => $bs, 'created_by' => $this->user->id]);
            Payroll::withoutEvents(fn () => Payroll::create(['tenant_id' => $this->tenant->id, 'employee_id' => $amina->id, 'payroll_batch_id' => $batch->id,
                'payroll_number' => 'PAY-'.$no, 'pay_period_start' => '2026-0'.($i + 7).'-01', 'pay_period_end' => '2026-0'.($i + 7).'-28', 'pay_date' => '2026-0'.($i + 7).'-28',
                'basic_salary' => 1000, 'gross_salary' => 1000, 'total_deductions' => 100, 'net_salary' => 900, 'status' => $ps, 'created_by' => $this->user->id]));
        }

        $r = $this->get(route('reports.payroll-summary', ['start_date' => '2026-07-01', 'end_date' => '2026-08-31']))->assertOk()->assertSee('Payroll summary');
        $this->assertEqualsWithDelta(1000, $r->viewData('totalGross'), 0.001, 'the draft August run is not pay');
    }

    public function test_comparing_months_on_the_31st_uses_the_month_before(): void
    {
        $this->createSuperAdmin();
        $this->travelTo('2026-10-31 10:00');
        $r = $this->get(route('reports.comparative.profit-loss', ['comparison_type' => 'month']))->assertOk();
        $this->assertSame('September 2026', $r->viewData('periodData')['previous']['label']);
        $this->assertSame('2026-09-30', $r->viewData('periodData')['previous']['end']);

        $custom = $this->get(route('reports.comparative.cash-flow', ['comparison_type' => 'custom', 'current_start' => '2026-10-01', 'current_end' => '2026-10-15',
            'previous_start' => '2026-09-01', 'previous_end' => '2026-09-15']))->assertOk();
        $this->assertSame('1 Oct 2026 to 15 Oct 2026', $custom->viewData('periodData')['current']['label']);
    }

    public function test_every_report_page_uses_the_shared_design(): void
    {
        $this->createSuperAdmin();
        foreach (['profit-loss', 'balance-sheet', 'cash-flow', 'trial-balance', 'general-ledger', 'accounts-receivable', 'accounts-payable',
            'sales-by-customer', 'sales-by-item', 'purchase-by-vendor', 'control-reconciliation', 'inventory-summary', 'payroll-summary',
            'payroll-by-department', 'employee-earnings', 'payroll-register', 'ytd-earnings', 'tax-liability-payroll', 'employer-contributions',
            'bank-disbursement', 'salary-revision-history', 'comparative.profit-loss', 'comparative.balance-sheet', 'comparative.cash-flow',
            'vat-gst-return', 'tax-liability', 'vat-return'] as $report) {
            $html = $this->get(route('reports.'.$report))->assertOk()->getContent();
            $this->assertStringContainsString('Amounts in ₦', $html, $report);
            $this->assertStringContainsString('All reports', $html, $report);
            $this->assertStringNotContainsString('uppercase tracking-wider', $html, $report);
        }
    }

    // ---- T7: record pages and forms ----

    public function test_stock_history_adds_up_every_move_not_just_the_page_shown(): void
    {
        $this->createSuperAdmin();
        $item = Item::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rice 50kg', 'track_inventory' => true]);
        foreach (range(1, 30) as $i) {
            InventoryHistory::create(['tenant_id' => $this->tenant->id, 'item_id' => $item->id, 'type' => $i <= 20 ? 'in' : 'out',
                'quantity' => 2, 'notes' => 'Move '.$i, 'created_by' => $this->user->id]);
        }

        $html = $this->get(route('inventory.history', $item))->assertOk()->getContent();
        // 30 moves, 25 to a page; the cards count all of them: 20 x 2 in, 10 x 2 out.
        $this->assertStringContainsString('1–25 of 30', $html);
        $this->assertMatchesRegularExpression('/Taken in.*?>\s*40\s*</s', $html);
        $this->assertMatchesRegularExpression('/Sent out.*?>\s*20\s*</s', $html);
    }

    public function test_bank_money_in_and_out_reads_the_ledger_with_a_running_balance(): void
    {
        $this->createSuperAdmin();
        $tid = $this->tenant->id;
        $code = $this->code('asset');
        $account = ChartOfAccount::where('tenant_id', $tid)->where('account_code', $code)->first();
        $bank = Bank::factory()->create(['tenant_id' => $tid, 'name' => 'Till account', 'chart_of_account_id' => $account->id, 'opening_balance' => 1000]);
        $income = $this->code('income');
        $expense = $this->code('expense');

        $this->postJournal('2026-09-15', [[$code, 500, 0], [$income, 0, 500]]);     // before the period
        $this->postJournal('2026-10-02', [[$code, 2000, 0], [$income, 0, 2000]]);   // money in
        $this->postJournal('2026-10-03', [[$expense, 300, 0], [$code, 0, 300]]);    // money out (a manual journal, no payment record)
        foreach (range(1, 27) as $i) {
            $this->postJournal('2026-10-05', [[$code, 10, 0], [$income, 0, 10]]);
        }
        $this->postJournal('2026-11-01', [[$code, 99, 0], [$income, 0, 99]]);       // after the period

        $html = $this->get(route('banks.transactions', ['bank' => $bank, 'from' => '2026-10-01', 'to' => '2026-10-31']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Balance at the start.*?1,500\.00/s', $html);   // opening 1,000 + 500 before
        $this->assertMatchesRegularExpression('/Money in.*?2,270\.00/s', $html);
        $this->assertMatchesRegularExpression('/Money out.*?300\.00/s', $html);
        $this->assertMatchesRegularExpression('/Balance at the end.*?3,470\.00/s', $html);
        $this->assertStringContainsString('1–25 of 29', $html);

        // Page 2 starts from what the first 25 lines left: 1,500 + 2,000 - 300 + 23 x 10.
        $page2 = $this->get(route('banks.transactions', ['bank' => $bank, 'from' => '2026-10-01', 'to' => '2026-10-31', 'page' => 2]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Brought forward from earlier pages.*?3,430\.00/s', $page2);
    }

    public function test_journal_source_links_to_the_document_or_the_journal(): void
    {
        $this->createSuperAdmin();
        $this->invoices();
        $invoice = Invoice::where('invoice_number', 'INV-1')->first();
        $journal = Journal::create(['tenant_id' => $this->tenant->id, 'journal_number' => Journal::generateNumber($this->tenant->id), 'journal_date' => '2026-10-01',
            'description' => 'Invoice', 'status' => 'posted', 'is_posted' => true, 'reference_type' => Invoice::class, 'reference_id' => $invoice->id]);
        $this->assertSame(['label' => 'Invoice INV-1', 'url' => route('invoices.show', $invoice)], JournalSource::of($journal->load('reference')));

        $manual = Journal::create(['tenant_id' => $this->tenant->id, 'journal_number' => Journal::generateNumber($this->tenant->id), 'journal_date' => '2026-10-01',
            'description' => 'Owner put money in', 'status' => 'posted', 'is_posted' => true]);
        $this->assertSame(route('journals.show', $manual), JournalSource::of($manual)['url']);
    }

    private function asset(string $inService = '2026-01-31'): FixedAsset
    {
        return FixedAsset::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Delivery van', 'purchase_date' => $inService, 'in_service_date' => $inService,
            'purchase_cost' => 60000, 'salvage_value' => 0, 'depreciable_amount' => 60000, 'useful_life' => 5,
            'depreciation_method' => 'straight_line', 'accumulated_depreciation' => 0, 'book_value' => 60000, 'status' => 'active',
        ]);
    }

    public function test_depreciation_goes_month_by_month_and_the_schedule_pages_open(): void
    {
        $this->createSuperAdmin();
        $asset = $this->asset();

        // From 31 Jan the next month is February, not March (dates used to skip short months).
        $dates = collect($asset->generateDepreciationSchedule())->take(4)->map(fn ($r) => $r['date']->toDateString())->all();
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'], $dates);
        app(DepreciationService::class)->recordDepreciation($asset, Carbon::parse('2026-01-31'));
        $this->assertSame('2026-02-28', $asset->fresh()->next_depreciation_date->toDateString());

        // The asset's schedule used to fail (it read a column that doesn't exist).
        $this->get(route('fixed-assets.schedule', $asset))->assertOk()
            ->assertSee('Depreciation charged so far')->assertSee('28 Feb 2026')->assertSee('Charged');

        // The year's charge across assets used to add up a key that doesn't exist (always 0).
        $this->get(route('fixed-assets.depreciation-schedule', ['year' => 2026]))->assertOk()->assertSee('Charge for 2026')->assertSee('12,000.00');
        $this->get(route('fixed-assets.register', ['as_of' => '2026-12-31']))->assertOk()->assertSee('Asset register')->assertSee('Delivery van')->assertSee('As at');
    }

    public function test_budget_and_actual_counts_income_above_budget_as_better(): void
    {
        $this->travelTo('2026-03-15 09:00');
        $this->createSuperAdmin();
        $this->plan->update(['slug' => 'professional']);
        $tid = $this->tenant->id;
        $months = array_fill_keys(['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'], 100);
        $budget = Budget::create(['tenant_id' => $tid, 'name' => 'Plan 2026', 'fiscal_year' => 2026, 'status' => 'active', 'created_by' => $this->user->id]);
        $incomeCode = $this->code('income');
        $expenseCode = $this->code('expense');
        $income = ChartOfAccount::where('tenant_id', $tid)->where('account_code', $incomeCode)->first();
        $expense = ChartOfAccount::where('tenant_id', $tid)->where('account_code', $expenseCode)->first();
        $budget->lines()->create(['account_id' => $income->id, 'annual_total' => 1200] + $months);
        $budget->lines()->create(['account_id' => $expense->id, 'annual_total' => 1200] + $months);
        $bank = $this->code('asset');
        $this->postJournal('2026-02-10', [[$bank, 500, 0], [$incomeCode, 0, 500]]);   // sold 500 against 300 budgeted so far
        $this->postJournal('2026-02-11', [[$expenseCode, 400, 0], [$bank, 0, 400]]);  // spent 400 against 300

        $ytd = app(BudgetService::class)->getYTDUtilization($budget);
        $this->assertSame('mar', $ytd['through']);
        $this->assertEqualsWithDelta(300, $ytd['total_budget_ytd'], 0.001);           // spending only
        $this->assertEqualsWithDelta(400, $ytd['total_actual_ytd'], 0.001);
        $this->assertSame(1, $ytd['over_budget_count']);                              // the spending, not the income

        $this->get(route('budgets.vs-actual', $budget))->assertOk()
            ->assertSee('+200.00')->assertSee('-100.00')->assertSee('On or above')->assertSee('Over budget')
            ->assertDontSee('Below budget')->assertSee('1 account worse than budget');
    }

    public function test_customer_page_shows_each_invoice_total(): void
    {
        $this->createSuperAdmin();
        $this->invoices();
        $bala = Customer::where('name', 'Bala Stores')->first();
        // It read a column invoices don't have, so every total showed as 0.00.
        $this->get(route('customers.show', $bala))->assertOk()->assertSee('2,000.00')->assertSee('Overdue');
    }

    public function test_every_record_report_page_uses_the_shared_design(): void
    {
        $this->createSuperAdmin();
        foreach (['fixed-assets.register', 'fixed-assets.depreciation-schedule', 'assembly-orders.report', 'withholding-tax.setup',
            'withholding-tax.schedule', 'withholding-tax.receivable'] as $page) {
            $html = $this->get(route($page))->assertOk()->getContent();
            $this->assertStringNotContainsString('uppercase tracking-wider', $html, $page);
            $this->assertStringContainsString('tbl-', $html, $page);
        }
        foreach (['fixed-assets.register', 'fixed-assets.depreciation-schedule', 'assembly-orders.report', 'withholding-tax.schedule', 'withholding-tax.receivable'] as $page) {
            $this->assertStringContainsString('Amounts in ₦', $this->get(route($page))->getContent(), $page);
        }
    }

    // ---- T8: buttons and page headers ----

    public function test_screens_use_the_shared_buttons_and_page_header(): void
    {
        $old = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            $rel = str_replace('\\', '/', $file->getRelativePathname());
            if (! str_ends_with($rel, '.blade.php') || preg_match(self::NOT_SCREENS, $rel) || str_starts_with($rel, 'layouts/')) {
                continue;
            }
            $html = File::get($file->getPathname());
            // All-caps hand-made buttons: use btn-primary, btn-secondary or btn-danger.
            if (preg_match('/<(a|button)\b[^>]*class="[^"]*\buppercase\b[^"]*\btracking-widest/', $html)
                || str_contains($html, '<h2 class="font-semibold text-xl')) {
                $old[] = $rel;
            }
        }

        $this->assertSame([], $old, 'Use <x-table.page-header> and the shared btn-* classes (tables plan T8).');
    }

    public function test_record_page_keeps_delete_under_more_and_names_the_record_in_the_breadcrumb(): void
    {
        $this->createSuperAdmin();
        $this->invoices();
        $tid = $this->tenant->id;
        $journal = Journal::create(['tenant_id' => $tid, 'journal_number' => 'JV-T8', 'journal_date' => '2026-10-01',
            'description' => 'Draft journal', 'status' => 'draft', 'is_posted' => false]);

        $html = $this->get(route('journals.show', $journal))->assertOk()->getContent();
        $this->assertStringContainsString('Post journal', $html);
        $this->assertMatchesRegularExpression('/role="menuitem"[^>]*>\s*Delete/s', $html);   // in the More menu
        $this->assertStringNotContainsString('Journal is Posted', $html);

        // Edit page: Dashboard > Journals > JV-T8 (a link back) > Edit.
        $edit = $this->get(route('journals.edit', $journal))->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('journals.show', $journal).'"', $edit);
        $this->assertMatchesRegularExpression('/aria-label="Breadcrumb".*JV-T8.*Edit/s', $edit);
    }
}
