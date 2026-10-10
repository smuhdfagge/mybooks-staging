<?php

namespace Tests\Feature\Tables;

use App\Livewire\Bills\BillsTable;
use App\Livewire\Customers\CustomersTable;
use App\Livewire\Invoices\InvoicesTable;
use App\Livewire\PaymentsMade\PaymentsMadeTable;
use App\Livewire\PaymentsReceived\PaymentsReceivedTable;
use App\Livewire\RecurrentBills\RecurrentBillsTable;
use App\Livewire\Vendors\VendorsTable;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentMade;
use App\Models\PaymentReceived;
use App\Models\RecurrentBill;
use App\Models\Vendor;
use App\Models\VendorAdvanceApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    /** Printed documents, PDFs and emails have their own layout. */
    private const NOT_SCREENS = '#(^vendor/|pdf|print|mail|statements/document|waybill|payslip|components/table/)#';

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
            if (! File::exists($path) || ! str_contains(File::get($path), '<table') || $this->usesNewDesign(File::get($path))) {
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
}
