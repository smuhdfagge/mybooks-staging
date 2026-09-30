<?php

namespace Tests\Feature\Regression;

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
        $view = file_get_contents(resource_path('views/livewire/invoices/invoices-table.blade.php'));
        $this->assertMatchesRegularExpression('/wire:click="applyBulkAction" wire:loading\.attr="disabled"/', $view);

        $button = file_get_contents(resource_path('views/components/primary-button.blade.php'));
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
}
