<?php

namespace Tests\Feature\Regression;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Vendor;
use App\Support\Money;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Round 4, Phase D: user interface and accessibility (U3-U14).
 */
class PhaseDUiTest extends TestCase
{
    private function page(string $route, array $permissions = [], array $params = []): string
    {
        if (! isset($this->user)) {
            $this->createAuthenticatedUser($permissions);
        }

        return $this->get(route($route, $params))->assertOk()->getContent();
    }

    // ── U12: dark mode set before paint ─────────────────────────

    public function test_u12_dark_mode_is_set_by_a_head_script_before_the_stylesheet(): void
    {
        $html = $this->page('dashboard', ['view dashboard']);

        $head = substr($html, 0, strpos($html, '</head>'));
        $script = strpos($head, 'data-theme-init');
        $this->assertNotFalse($script, 'No theme script in the <head>.');
        $this->assertLessThan(strpos($head, 'stylesheet'), $script, 'Theme script must run before the stylesheet loads.');
        $this->assertMatchesRegularExpression('/<script nonce="[^"]+" data-theme-init>/', $head);
        // With nothing saved, the device setting is used.
        $this->assertStringContainsString('prefers-color-scheme: dark', $head);
    }

    // ── U14: Chart.js bundled, one Alpine ───────────────────────

    public function test_u14_charts_do_not_load_chart_js_from_a_cdn(): void
    {
        $html = $this->page('dashboard', ['view dashboard', 'revenue-chart dashboard-widgets']);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
        $this->assertStringContainsString('window.loadChart()', $html);

        // The analytics page runs MySQL-only queries, so check its view.
        $analytics = file_get_contents(resource_path('views/analytics/index.blade.php'));
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $analytics);
        $this->assertStringContainsString('window.loadChart()', $analytics);

        $this->assertStringContainsString("import('chart.js/auto')", file_get_contents(resource_path('js/app.js')));
    }

    public function test_u14_home_page_loads_alpine_once(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Alpine comes with Livewire in the app bundle; a second copy from a
        // CDN made both start on the same page.
        $this->assertDoesNotMatchRegularExpression('/<script[^>]+alpinejs/i', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
    }

    // ── U9: messages shown once ─────────────────────────────────

    public function test_u9_success_and_error_messages_are_shown_once(): void
    {
        $this->createAuthenticatedUser([
            'view purchase-orders', 'view sales-receipts', 'create journals', 'view journals',
        ]);

        foreach (['purchase-orders.index', 'sales-receipts.index', 'journals.create'] as $route) {
            $html = $this->withSession(['success' => 'Saved-once-ok', 'error' => 'Failed-once-ok'])
                ->get(route($route))->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'Saved-once-ok'), $route);
            $this->assertSame(1, substr_count($html, 'Failed-once-ok'), $route);
        }
    }

    public function test_u9_only_the_layout_renders_session_messages(): void
    {
        $offenders = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($it as $file) {
            $path = $file->getPathname();
            if (! str_ends_with($path, '.blade.php') || preg_match('#/(layouts|auth|admin|pages|livewire)/#', $path)) {
                continue;
            }
            if (preg_match('/\{\{\s*session\(\'(success|error)\'\)/', file_get_contents($path))) {
                $offenders[] = str_replace(resource_path('views').'/', '', $path);
            }
        }

        $this->assertSame([], $offenders);
    }

    // ── U13: no double posting ──────────────────────────────────

    public function test_u13_posting_forms_disable_their_buttons_while_sending(): void
    {
        $html = $this->page('items.create', ['create items']);

        // The shared page script handles every posting form.
        $this->assertStringContainsString("form.setAttribute('data-submitting', '')", $html);
        $this->assertStringContainsString('button.disabled = true', $html);
        $this->assertMatchesRegularExpression('/<form[^>]+method="POST"/i', $html);
    }

    public function test_u13_livewire_bulk_actions_are_disabled_while_running(): void
    {
        // Tables plan T1: bulk actions are the shared bulk-bar buttons.
        $view = file_get_contents(resource_path('views/components/table/bulk-button.blade.php'));
        $this->assertMatchesRegularExpression('/wire:click="runBulk\(.*wire:loading\.attr="disabled"/s', $view);

        // The primary button's disabled look (moved into .btn-primary by U4).
        $button = file_get_contents(resource_path('views/components/primary-button.blade.php'))
            .file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('disabled:opacity-50', $button);
    }

    // ── U10: text contrast ──────────────────────────────────────

    public function test_u10_muted_text_uses_the_shade_that_passes_contrast(): void
    {
        // gray-400 on white (2.5:1) and gray-500 on dark grey (3.0:1) fail
        // WCAG AA; text-gray-500 dark:text-gray-400 passes on both (4.8:1, 5.9:1).
        // Sidebars, sign-in and public pages sit on dark backgrounds, and icons
        // are not text, so they are skipped.
        $offenders = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($it as $file) {
            $path = str_replace(resource_path('views').'/', '', $file->getPathname());
            if (! str_ends_with($path, '.blade.php') || preg_match('#sidebar|public-footer|(^|/)auth/|welcome|^pages/|^errors/#', $path)) {
                continue;
            }
            foreach (file($file->getPathname()) as $n => $line) {
                if (str_contains($line, '<svg') || str_contains($line, '<path')) {
                    continue;
                }
                $bare = preg_match('/(?<![\w:-])text-gray-400(?=[\s"\'])/', $line) && ! preg_match('/dark:text-gray-\d/', $line);
                if ($bare || preg_match('/(?<![\w:-])text-gray-400 dark:text-gray-500/', $line)) {
                    $offenders[] = $path.':'.($n + 1);
                }
            }
        }

        $this->assertSame([], $offenders);
    }

    // ── U11: keyboard sortable headers ──────────────────────────

    public function test_u11_sort_headers_are_buttons_with_aria_sort(): void
    {
        // Tables plan T1: an empty list shows a message instead of the table, so add an invoice.
        $this->createAuthenticatedUser(['view invoices']);
        Invoice::withoutEvents(fn () => Invoice::factory()->create(['tenant_id' => $this->tenant->id]));
        $html = $this->page('invoices.index', ['view invoices']);

        // Default sort is newest invoice date first.
        $this->assertMatchesRegularExpression('/<th scope="col" aria-sort="descending"[^>]*>\s*<button type="button" wire:click="sortBy\(\'invoice_date\'\)"/', $html);
        $this->assertMatchesRegularExpression('/<th scope="col" aria-sort="none"[^>]*>\s*<button type="button" wire:click="sortBy\(\'total\'\)"/', $html);
    }

    public function test_u11_no_header_or_row_is_clickable_without_a_keyboard_route(): void
    {
        $offenders = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($it as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $html = file_get_contents($file->getPathname());
            // A click handler on a header cell, or on a div that toggles
            // something, with no button role to reach it by keyboard.
            if (preg_match('/<th\b[^>]*wire:click=/', $html)
                || preg_match('/<div\b(?![^>]*role="button")[^>]*cursor-pointer[^>]*@click="open = !open"/', $html)) {
                $offenders[] = str_replace(resource_path('views').'/', '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders);
    }

    // ── U7: dialogs trap focus and close with Escape ────────────

    public function test_u7_generate_periods_uses_the_shared_modal(): void
    {
        $html = $this->page('accounting-periods.index', ['view chart-of-accounts', 'edit chart-of-accounts']);

        $this->assertStringContainsString('data-modal="generate-periods"', $html);
        $this->assertMatchesRegularExpression('/data-modal="generate-periods"\s+role="dialog"\s+aria-modal="true"\s+aria-labelledby="modal-generate-periods-title"/', $html);
        $this->assertStringContainsString('id="modal-generate-periods-title"', $html);
        $this->assertStringContainsString('x-trap.inert.noscroll="show"', $html);
        $this->assertStringContainsString('x-on:keydown.escape.window', $html);
        $this->assertStringContainsString('data-open-modal="generate-periods"', $html);
        $this->assertStringNotContainsString('id="generateModal"', $html);
    }

    public function test_u7_every_dialog_traps_focus_and_closes_with_escape(): void
    {
        $offenders = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($it as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $path = str_replace(resource_path('views').'/', '', $file->getPathname());
            $html = file_get_contents($file->getPathname());
            // Hand-built pop-ups shown by toggling "hidden" on an id.
            if (preg_match('/id="\w*Modal"[^>]*class="[^"]*hidden[^"]*fixed inset-0/', $html)) {
                $offenders[] = $path.' (hidden-class modal)';
            }
            // Any dialog must trap focus and listen for Escape.
            if (preg_match('/aria-modal="true"/', $html) && ! (str_contains($html, 'x-trap') && str_contains($html, 'escape'))) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders);
    }

    // ── U8: one way to show money ───────────────────────────────

    public function test_u8_money_format_uses_symbol_separators_and_rounding(): void
    {
        $this->assertSame('₦1,234,567.89', Money::format(1234567.891, 'NGN'));
        $this->assertSame('$1.01', Money::format(1.005, 'USD'));
        $this->assertSame('-€20.00', Money::format(-20, 'EUR'));
        $this->assertSame('XYZ 5.00', Money::format(5, 'XYZ'));
    }

    public function test_u8_lists_and_forms_use_the_business_currency(): void
    {
        $this->createAuthenticatedUser(['view invoices', 'create bills']);
        $this->tenant->update(['currency' => 'USD']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Invoice::factory()->create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'total' => 1234.5,
        ]);

        $list = $this->get(route('invoices.index'))->assertOk()->getContent();
        $this->assertStringContainsString('$1,234.50', $list);

        $form = $this->get(route('bills.create'))->assertOk()->getContent();
        $this->assertStringContainsString('<meta name="currency-symbol" content="$">', $form);
        $this->assertStringContainsString('x-text="formatMoney(total)"', $form);
        $this->assertStringNotContainsString('₦', $form);
        $this->assertDoesNotMatchRegularExpression('/x-text="[^"]*toFixed\(2\)/', $form);
    }

    // ── U5: accessible searchable dropdown ──────────────────────

    public function test_u5_searchable_select_is_an_aria_combobox(): void
    {
        $html = $this->page('customers.create', ['create customers']);

        $this->assertStringContainsString('<label for="country"', $html);
        $this->assertMatchesRegularExpression('/<input type="text" id="country" role="combobox"[^>]*aria-controls="country-listbox"/s', $html);
        $this->assertStringContainsString(':aria-expanded="open.toString()"', $html);
        $this->assertStringContainsString(':aria-activedescendant=', $html);
        $this->assertStringContainsString('@keydown.arrow-down.prevent="move(1)"', $html);
        $this->assertStringContainsString('@keydown.escape=', $html);
        $this->assertMatchesRegularExpression('/<ul id="country-listbox" role="listbox"/', $html);
        $this->assertStringContainsString('role="option"', $html);
        $this->assertStringContainsString('<input type="hidden" name="country"', $html);
    }

    // ── U4: shared form components ──────────────────────────────

    /** Permissions a named GET route needs, read from its middleware. */
    private function permissionsFor(string $route): array
    {
        $perms = [];
        foreach (app('router')->getRoutes()->getByName($route)->gatherMiddleware() as $mw) {
            if (is_string($mw) && str_starts_with($mw, 'permission:')) {
                $perms = array_merge($perms, explode('|', substr($mw, 11)));
            }
        }

        return $perms;
    }

    public function test_u4_converted_forms_use_the_shared_field_card_and_button(): void
    {
        $html = $this->page('customers.create', $this->permissionsFor('customers.create'));

        $this->assertStringContainsString('<label for="name" class="form-label">Customer Name <span class="text-red-600 dark:text-red-300">*</span></label>', $html);
        $this->assertMatchesRegularExpression('/<input type="text" name="name" id="name" value="" required(="required")? class="form-control">/', $html);
        $this->assertStringContainsString('class="card"', $html);
        $this->assertStringContainsString('class="btn-primary', $html);
        // The long copied label/input class strings are gone from this form.
        $this->assertStringNotContainsString('class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"', $html);
    }

    public function test_u4_every_converted_page_still_renders(): void
    {
        $routes = [
            'customers.create', 'vendors.create', 'items.create', 'employees.create', 'expenses.create',
            'departments.create', 'settings.company', 'salary-structures.create',
            'recurrent-expenses.create', 'recurrent-bills.create', 'sales-orders.create', 'sales-receipts.create',
            'purchase-orders.create', 'payments-made.create', 'payments-received.create', 'leaves.create',
            'settings.users.create', 'journals.create', 'accounting-periods.create', 'payroll.create',
        ];
        $perms = [];
        foreach ($routes as $route) {
            $perms = array_merge($perms, $this->permissionsFor($route));
        }
        $this->createAuthenticatedUser(array_values(array_unique($perms)));

        foreach ($routes as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertStringContainsString('class="form-control', $html, $route);
        }
    }

    // ── U6: labels and error links ──────────────────────────────

    /** Visible form controls in $html that no label names. */
    private function unlabelledControls(string $html): array
    {
        preg_match_all('/<label[^>]*\bfor="([^"]+)"/', $html, $m);
        $labelled = array_flip($m[1]);
        $missing = [];
        preg_match_all('/<(input|select|textarea)\b((?:[^>"]|"[^"]*")*)>/', $html, $controls, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($controls as [[$tag, $offset], , [$attrs]]) {
            if (preg_match('/type="(hidden|submit|button)"/', $attrs) || preg_match('/aria-label(ledby)?="[^"]+"/', $attrs)) {
                continue;
            }
            // Wrapped in its label.
            $before = substr($html, 0, $offset);
            if (strrpos($before, '<label') > (int) strrpos($before, '</label>')) {
                continue;
            }
            if (preg_match('/\sid="([^"]+)"/', $attrs, $id) && isset($labelled[$id[1]])) {
                continue;
            }
            $missing[] = substr($tag, 0, 120);
        }

        return $missing;
    }

    public function test_u6_form_and_list_controls_all_have_labels(): void
    {
        $routes = ['customers.create', 'vendors.create', 'items.create', 'expenses.create', 'employees.create', 'invoices.index', 'bills.index'];
        $perms = [];
        foreach ($routes as $route) {
            $perms = array_merge($perms, $this->permissionsFor($route));
        }
        $this->createAuthenticatedUser(array_values(array_unique($perms)));

        foreach ($routes as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertSame([], $this->unlabelledControls($html), $route);
        }
    }

    public function test_u6_errors_are_tied_to_their_fields_and_summarised(): void
    {
        $this->createAuthenticatedUser($this->permissionsFor('customers.create'));

        $html = $this->withSession(['errors' => (new ViewErrorBag)->put('default', new MessageBag([
            'name' => ['The customer name field is required.'],
            'country' => ['Pick a country.'],
        ]))])->get(route('customers.create'))->assertOk()->getContent();

        // The field says it is invalid and points at its message.
        $this->assertMatchesRegularExpression('/<input type="text" name="name" id="name"[^>]*aria-invalid="true"[^>]*aria-describedby="name-error"/', $html);
        $this->assertStringContainsString('<p id="name-error" class="form-error">The customer name field is required.</p>', $html);
        $this->assertMatchesRegularExpression('/id="country" role="combobox"(?:[^>"]|"[^"]*")*aria-invalid="true" aria-describedby="country-error"/', $html);
        $this->assertStringContainsString('<p id="country-error"', $html);

        // One summary at the top, linking to the fields.
        $this->assertSame(1, substr_count($html, 'data-error-summary'));
        $this->assertStringContainsString('<a href="#name" class="underline hover:no-underline">The customer name field is required.</a>', $html);
    }

    // ── U3: line items on a phone ───────────────────────────────

    public function test_u3_line_item_rows_stack_on_phones_and_every_box_is_named(): void
    {
        $routes = ['invoices.create', 'bills.create', 'sales-orders.create', 'purchase-orders.create', 'sales-receipts.create', 'recurrent-bills.create'];
        $perms = [];
        foreach ($routes as $route) {
            $perms = array_merge($perms, $this->permissionsFor($route));
        }
        $this->createAuthenticatedUser(array_values(array_unique($perms)));

        foreach ($routes as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<table class="[^"]*\bline-items\b/', $html, $route);

            // The row template: each cell carries its column name for the phone layout ...
            $start = strpos($html, 'x-for="(item, index) in items"');
            $row = substr($html, $start, strpos($html, '</template>', strpos($html, '</tr>', $start)) - $start);
            $this->assertStringContainsString('data-label="Qty"', $row, $route);
            $this->assertStringContainsString('data-cell="actions"', $row, $route);
            // ... and every box and the remove button have a name for screen readers.
            $this->assertStringContainsString('aria-label="Quantity"', $row, $route);
            $this->assertStringContainsString('aria-label="Unit price"', $row, $route);
            $this->assertStringContainsString('aria-label="Remove line"', $row, $route);
            $this->assertSame([], $this->unlabelledControls($row), $route);
        }

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertMatchesRegularExpression('/@media \(max-width: 767\.98px\)\s*\{\s*\.line-items/', $css);
    }

    public function test_the_expense_edit_page_opens(): void
    {
        // Its vendor list escaped quotes inside {{ }}, a PHP syntax error,
        // so the page never opened.
        $this->createAuthenticatedUser(['view expenses', 'create expenses', 'edit expenses']);
        Vendor::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Dangote', 'company_name' => 'Dangote Ltd']);
        $account = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('type', 'expense')->firstOrFail();
        $expense = Expense::factory()->create(['tenant_id' => $this->tenant->id, 'expense_account_id' => $account->id, 'status' => 'draft']);

        $this->get(route('expenses.edit', $expense))->assertOk()->assertSee('Dangote (Dangote Ltd)', false);
    }
}
