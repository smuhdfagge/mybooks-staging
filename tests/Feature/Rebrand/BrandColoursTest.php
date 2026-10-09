<?php

namespace Tests\Feature\Rebrand;

use App\Models\InvoiceTemplate;
use App\Notifications\TestEmailNotification;
use App\Support\Rebrand\ColourRules;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Rebrand guard (R1). Fails when an old colour (indigo, blue, purple,
 * gradients, old hex codes) is used in a file that is not on the rebrand
 * to-do list, so a finished area can't slip back. Each rebrand session
 * removes its files from the list; at the end (R12) the list is empty.
 *
 * To-do list: tests/Feature/Rebrand/rebrand-todo.txt
 * Plan: docs/REBRAND-PLAN.md
 */
class BrandColoursTest extends TestCase
{
    private const TODO = __DIR__.'/rebrand-todo.txt';

    /** Files that define the old colours on purpose. */
    private const SKIP = [
        'app/Support/Rebrand/ColourRules.php',
    ];

    /** @return list<string> project-relative paths of every file the guard covers */
    public static function scannedFiles(): array
    {
        $root = base_path();
        $files = [];
        foreach (['resources/views', 'resources/css', 'resources/js', 'app'] as $dir) {
            foreach (File::allFiles($root.'/'.$dir) as $f) {
                if (preg_match('/\.(php|css|js)$/', $f->getFilename())) {
                    $files[] = ltrim(str_replace($root, '', $f->getPathname()), '/');
                }
            }
        }
        foreach (['public/manifest.json', 'public/offline.html', 'public/favicon.svg'] as $f) {
            if (is_file($root.'/'.$f)) {
                $files[] = $f;
            }
        }
        $files = array_values(array_diff($files, self::SKIP));
        sort($files);

        return $files;
    }

    public static function fileHasOldColour(string $path): bool
    {
        foreach (file(base_path($path)) ?: [] as $line) {
            if (ColourRules::lineHasOldColour($line)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function todo(): array
    {
        $lines = array_map('trim', file(self::TODO) ?: []);

        return array_values(array_filter($lines, fn ($l) => $l !== '' && ! str_starts_with($l, '#')));
    }

    public function test_no_old_colours_outside_the_todo_list(): void
    {
        $todo = array_flip($this->todo());
        $bad = [];
        foreach (self::scannedFiles() as $path) {
            if (! isset($todo[$path]) && self::fileHasOldColour($path)) {
                $bad[] = $path;
            }
        }

        $this->assertSame([], $bad, "These files use old colours (indigo, blue, purple, gradients or old hex codes).\n"
            .'Use the brand colours instead (php artisan rebrand:colours <path>), see docs/REBRAND-PLAN.md.');
    }

    public function test_todo_list_only_holds_files_still_to_do(): void
    {
        $done = [];
        $missing = [];
        foreach ($this->todo() as $path) {
            if (! is_file(base_path($path))) {
                $missing[] = $path;
            } elseif (! self::fileHasOldColour($path)) {
                $done[] = $path;
            }
        }

        $this->assertSame([], $missing, 'These files on the rebrand to-do list no longer exist: take them off the list.');
        $this->assertSame([], $done, 'These files are finished: take them off tests/Feature/Rebrand/rebrand-todo.txt.');
    }

    public function test_rules_swap_indigo_and_blue_for_brand(): void
    {
        $out = ColourRules::apply('<a class="bg-indigo-600 hover:bg-blue-700 focus:ring-indigo-500/50 text-blue-600">')['text'];

        $this->assertSame('<a class="bg-brand-600 hover:bg-brand-700 focus:ring-brand-500/50 text-brand-600">', $out);
    }

    public function test_rules_lift_dark_mode_text_to_a_readable_shade(): void
    {
        $out = ColourRules::apply('<span class="text-blue-600 dark:text-blue-400 dark:hover:text-indigo-500 dark:bg-blue-900">')['text'];

        $this->assertSame('<span class="text-brand-600 dark:text-brand-300 dark:hover:text-brand-300 dark:bg-brand-900">', $out);
    }

    public function test_rules_swap_purple_only_when_the_page_has_no_blue(): void
    {
        $alone = ColourRules::apply('<i class="text-purple-600"></i>');
        $this->assertSame('<i class="text-brand-600 dark:text-brand-300"></i>', $alone['text']);
        $this->assertSame([], $alone['flags']);

        $mixed = ColourRules::apply("<i class=\"text-blue-600\"></i>\n<i class=\"text-purple-600\"></i>\n");
        $this->assertStringContainsString('text-purple-600', $mixed['text']);
        $this->assertSame(2, $mixed['flags'][0]['line']);
    }

    public function test_rules_flag_gradients_and_unknown_colours_without_changing_them(): void
    {
        $text = "<div class=\"bg-gradient-to-r from-indigo-600 to-purple-600\"></div>\n<b class=\"text-pink-500\"></b>\n<i style=\"color:#667eea\"></i>\n";
        $result = ColourRules::apply($text);

        $this->assertSame($text, $result['text']);
        $this->assertSame([1, 2, 3], array_column($result['flags'], 'line'));
    }

    public function test_rules_give_navy_links_a_readable_dark_mode_colour(): void
    {
        $this->assertSame(
            '<a class="text-brand-600 hover:text-brand-900 font-medium dark:text-brand-300 dark:hover:text-brand-200">',
            ColourRules::apply('<a class="text-indigo-600 hover:text-indigo-900 font-medium">')['text']
        );
        // Left alone: already has a dark colour, or sits on its own light background.
        $this->assertSame('<a class="text-brand-600 dark:text-brand-300">', ColourRules::addDarkModeText('<a class="text-brand-600 dark:text-brand-300">'));
        $this->assertSame('<span class="bg-brand-100 text-brand-700">', ColourRules::addDarkModeText('<span class="bg-brand-100 text-brand-700">'));
    }

    public function test_rules_map_known_hex_codes(): void
    {
        $out = ColourRules::apply('<rect fill="#4F46E5"/><rect fill="#dbeafe"/><rect fill="#6b7280"/>')['text'];

        $this->assertSame('<rect fill="#1F4E79"/><rect fill="#D9E4EF"/><rect fill="#6b7280"/>', $out);
    }

    public function test_command_reports_without_changing_files_until_apply(): void
    {
        $rel = 'storage/framework/testing/rebrand-sample.blade.php';
        File::ensureDirectoryExists(dirname(base_path($rel)));
        File::put(base_path($rel), "<a class=\"bg-indigo-600\">x</a>\n<b class=\"text-pink-500\">y</b>\n");

        try {
            $this->artisan('rebrand:colours', ['paths' => [$rel]])
                ->expectsOutputToContain('1 swap(s), 1 to decide')
                ->assertSuccessful();
            $this->assertStringContainsString('bg-indigo-600', File::get(base_path($rel)));

            $this->artisan('rebrand:colours', ['paths' => [$rel], '--apply' => true])->assertSuccessful();
            $this->assertStringContainsString('bg-brand-600', File::get(base_path($rel)));
            $this->assertStringContainsString('text-pink-500', File::get(base_path($rel)));
        } finally {
            File::delete(base_path($rel));
        }
    }

    public function test_command_refuses_paths_outside_the_project(): void
    {
        $this->artisan('rebrand:colours', ['paths' => ['../../etc']])->assertFailed();
    }

    public function test_brand_config_matches_the_tailwind_colours(): void
    {
        $js = File::get(base_path('tailwind.config.js'));
        foreach (config('brand.brand') as $shade => $hex) {
            $this->assertMatchesRegularExpression("/\\b{$shade}: '".preg_quote($hex, '/')."'/", $js, "brand-{$shade} differs between config/brand.php and tailwind.config.js");
        }
        foreach (config('brand.accent') as $shade => $hex) {
            $this->assertStringContainsString("{$shade}: '{$hex}'", $js, "accent-{$shade} differs between config/brand.php and tailwind.config.js");
        }
    }

    public function test_layouts_use_the_brand_font_and_theme_colour(): void
    {
        foreach (['layouts/app.blade.php', 'layouts/guest.blade.php', 'components/layouts/admin.blade.php'] as $view) {
            $src = File::get(resource_path('views/'.$view));
            $this->assertStringContainsString("@include('partials.pwa-head')", $src, $view);
            $this->assertStringNotContainsString('family=inter', $src, $view);
        }
        $this->assertStringContainsString("config('brand.theme_color')", File::get(resource_path('views/partials/pwa-head.blade.php')));
        $this->assertStringContainsString('@fontsource/ibm-plex-sans/latin-ext-400.css', File::get(resource_path('css/app.css')));
        $this->assertStringContainsString('"IBM Plex Sans"', File::get(base_path('tailwind.config.js')));
    }

    // ---- R2: logo, icons, app frame ----------------------------------------

    public function test_manifest_uses_the_brand_colour_and_only_lists_files_that_exist(): void
    {
        $manifest = json_decode(File::get(public_path('manifest.json')), true);

        $this->assertSame(config('brand.theme_color'), $manifest['theme_color']);
        $this->assertArrayNotHasKey('screenshots', $manifest);
        $icons = array_merge($manifest['icons'], ...array_column($manifest['shortcuts'], 'icons'));
        foreach ($icons as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')), $icon['src']);
        }
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
    }

    public function test_icons_are_the_new_logo_and_phones_refresh_their_cache(): void
    {
        foreach (['favicon.svg', 'icons/icon.svg', 'images/brand/mybooks-logo.svg'] as $f) {
            $this->assertStringContainsString('#1F4E79', File::get(public_path($f)), $f);
        }
        $this->assertStringContainsString('#D79E36', File::get(public_path('images/brand/mybooks-logo-white.svg')));
        $this->assertFileExists(public_path('icons/apple-touch-icon.png'));
        $this->assertFileExists(public_path('images/brand/mybooks-logo-email-300.png'));
        $this->assertStringContainsString("'mybooks-cache-v4'", File::get(public_path('sw.js')));
        $this->assertFileDoesNotExist(resource_path('views/components/application-logo.blade.php'));
    }

    public function test_sidebar_shows_the_new_logo_on_navy(): void
    {
        $this->createAuthenticatedUser(['view dashboard']);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('bg-brand-900', false)
            ->assertSee('M10 47H54M10 54H54', false)   // the double underline of the mark
            ->assertSee('nav-active', false)
            ->assertDontSee('M12 6.253v13', false);    // the old stock book icon
    }

    public function test_status_badges_use_the_shared_colours(): void
    {
        $html = (string) $this->blade('<x-status-badge status="pending" /><x-status-badge status="paid_x" /><x-status-badge status="sent" />');

        $this->assertStringContainsString('badge badge-warning', $html);
        $this->assertStringContainsString('badge badge-muted', $html);   // unknown status falls back to draft
        $this->assertStringContainsString('badge badge-info', $html);
    }

    // ---- R10: documents and emails --------------------------------------------

    public function test_emails_show_the_logo_and_navy_buttons(): void
    {
        $html = (string) (new TestEmailNotification('Kano Traders'))
            ->toMail(new AnonymousNotifiable)
            ->action('Open MyBooks', 'https://example.com')
            ->render();

        $this->assertStringContainsString('images/brand/mybooks-logo-email-300.png', $html);
        $this->assertStringContainsString('#1F4E79', $html);
        $this->assertStringNotContainsString('laravel.com/img', $html);
    }

    public function test_saved_invoice_template_colours_are_kept_and_shown_flat(): void
    {
        $settings = array_merge(InvoiceTemplate::getDefaultSettings(), ['primary_color' => '#AA0000', 'accent_color' => '#00AA00']);

        $html = view('invoices.templates.preview', ['settings' => $settings])->render();

        $this->assertStringContainsString('#AA0000', $html);
        $this->assertStringNotContainsString('linear-gradient', $html);
    }

    public function test_new_invoice_templates_start_in_brand_colours(): void
    {
        $defaults = InvoiceTemplate::getDefaultSettings();

        $this->assertSame('#1F4E79', $defaults['primary_color']);
        $this->assertSame(config('brand.status.success'), $defaults['accent_color']);
    }

    public function test_report_pdfs_take_their_colours_from_brand_config(): void
    {
        $layout = File::get(resource_path('views/reports/pdf/layout.blade.php'));

        $this->assertStringContainsString("config('brand.brand.600')", $layout);
        $this->assertStringContainsString("config('brand.status.success')", $layout);
        foreach (glob(resource_path('views/{reports/pdf,payroll/pdf}/*.blade.php'), GLOB_BRACE) as $file) {
            $this->assertStringNotContainsString('linear-gradient', File::get($file), basename($file));
        }
    }

    // ---- R11: public and sign-in pages ------------------------------------------

    public function test_every_file_is_rebranded(): void
    {
        $left = array_values(array_filter(array_map('trim', file(self::TODO) ?: []), fn ($l) => $l !== '' && ! str_starts_with($l, '#')));

        $this->assertSame([], $left, 'The rebrand to-do list should be empty after R11.');
    }

    public function test_sign_in_page_is_flat_navy_with_the_new_logo(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('M10 47H54M10 54H54', $html);   // the Option A mark
        $this->assertStringNotContainsString('floating-shapes', $html);
        $this->assertStringNotContainsString('linear-gradient', $html);
        $this->assertStringNotContainsString('100%</div>', $html);         // the old made-up stats
    }

    public function test_public_pages_use_the_new_logo_and_no_outside_fonts(): void
    {
        foreach (['home', 'about'] as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertStringContainsString('M17 45H47M17 51H47', $html, $route);   // the mark in the nav
            $this->assertStringNotContainsString('fonts.bunny.net', $html, $route);
        }
        $csp = $this->get(route('home'))->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('fonts.bunny.net', (string) $csp);
    }
}
