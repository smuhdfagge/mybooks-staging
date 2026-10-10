<?php

namespace Tests\Feature\Tables;

use App\Livewire\Invoices\InvoicesTable;
use App\Models\Customer;
use App\Models\Invoice;
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
    private const NOT_SCREENS = '#(pdf|print|mail|vendor|statement|waybill|payslip|components/table/)#';

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
}
