<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Support\JsLiteral;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 21, MB-29: script-src carried
 * 'unsafe-eval' (and 'unsafe-inline') because Alpine evaluated expressions
 * with new Function(). Alpine and Livewire now run their CSP builds; every
 * expression in the views is checked against the CSP parser in CI
 * (scripts/check-alpine-csp.mjs), and the pages were run in a browser with
 * the policy below and no expression errors or violations.
 */
class Session21CspTest extends TestCase
{
    public function test_the_policy_allows_no_eval_and_no_inline_script(): void
    {
        $this->createAuthenticatedUser(['view dashboard']);
        $policy = $this->get(route('dashboard'))->assertOk()->headers->get('Content-Security-Policy');

        preg_match('/script-src ([^;]+)/', (string) $policy, $m);
        $this->assertStringContainsString("'strict-dynamic'", $m[1]);
        $this->assertMatchesRegularExpression("/'nonce-[^']+'/", $m[1]);
        $this->assertStringNotContainsString('unsafe-eval', $m[1]);
        $this->assertStringNotContainsString('unsafe-inline', $m[1]);
    }

    public function test_the_app_and_the_public_pages_load_the_csp_builds(): void
    {
        $this->assertStringContainsString("livewire/dist/livewire.csp.esm'", file_get_contents(resource_path('js/app.js')));
        $this->assertStringContainsString("from '@alpinejs/csp'", file_get_contents(resource_path('js/public.js')));
        // @livewireScripts (the admin layout) serves the CSP build too.
        $this->assertTrue(config('livewire.csp_safe'));
    }

    public function test_the_admin_layout_runs_one_copy_of_alpine(): void
    {
        // @livewireScripts there started a second Alpine that had none of
        // app.js's helpers or the page components: every admin page lost
        // its theme and drawer state.
        $layout = file_get_contents(resource_path('views/components/layouts/admin.blade.php'));
        $this->assertStringContainsString('@livewireScriptConfig', $layout);
        $this->assertStringNotContainsString('@livewireScripts', $layout);
    }

    public function test_the_public_csp_build_is_the_alpine_livewire_bundles(): void
    {
        // An older @alpinejs/csp read no \uXXXX escapes, so every & or quote
        // @js wrote came out as text ("plan=starteru0026cycle=annual").
        // Read from the built files: Composer's dist of Livewire has no package.json.
        $version = fn (string $file) => preg_match('/version: "(\d+\.\d+\.\d+)"/', (string) file_get_contents(base_path($file)), $m) ? $m[1] : null;
        $bundled = $version('vendor/livewire/livewire/dist/livewire.csp.esm.js');
        $this->assertNotNull($bundled, "Livewire's CSP build no longer says its Alpine version");
        $this->assertSame('^'.$bundled, json_decode(file_get_contents(base_path('package.json')), true)['devDependencies']['@alpinejs/csp']);
    }

    public function test_js_writes_a_literal_the_csp_parser_reads_not_json_parse(): void
    {
        $html = Blade::render('<div x-data="{ items: @js($items), name: @jsText($name) }"></div>', [
            'items' => [['id' => 1, 'name' => 'Bello & Sons "Ltd"', 'rate' => 7.5, 'tiny' => 0.00000012, 'on' => true, 'none' => null], []],
            'name' => "O'Neil </script><img onerror=x>",
        ]);

        $this->assertStringNotContainsString('JSON.parse', $html);
        $this->assertSame(
            "<div x-data=\"{ items: [{'id':1,'name':'Bello \\u0026 Sons \\u0022Ltd\\u0022','rate':7.5,'tiny':0.00000012,'on':true,'none':null},[]], "
            ."name: 'O\\u0027Neil \\u003c/script\\u003e\\u003cimg onerror=x\\u003e' }\"></div>",
            $html
        );
    }

    public function test_the_literal_is_safe_in_an_attribute_and_in_a_script(): void
    {
        $literal = JsLiteral::from(['k"\'<>&`\\' => "line\nbreak\u{2028}", 'n' => -3, 'f' => 1.0e-7, 'big' => 1.5e20]);

        // Nothing that could end an attribute, a string or a <script>.
        $this->assertDoesNotMatchRegularExpression('/["<>&`]/', $literal);
        $this->assertSame("{'k\\u0022\\u0027\\u003c\\u003e\\u0026\\u0060\\u005c':'line\\u000abreak\\u2028','n':-3,'f':0.0000001,'big':150000000000000000000}", $literal);
        // Objects with numeric keys stay objects; lists stay lists.
        $this->assertSame("{'3':'a','7':'b'}", JsLiteral::from([3 => 'a', 7 => 'b']));
        $this->assertSame("['a','b']", JsLiteral::from(['a', 'b']));
        $this->assertSame('{}', JsLiteral::from(new \stdClass));
    }

    public function test_the_literal_matches_js_from_on_the_awkward_values(): void
    {
        // Exponents written out exactly (the CSP parser reads none).
        $this->assertSame('0.00000000012345678901234568', JsLiteral::from(1.2345678901234568e-10));
        $this->assertSame('10000000000000000000000000', JsLiteral::from(1.0e25));
        $this->assertSame('-0.00000025', JsLiteral::from(-2.5e-7));
        // As Js::from(): Htmlable as its HTML, bad bytes as U+FFFD.
        $this->assertSame("'\\u003cb\\u003ex\\u003c/b\\u003e'", JsLiteral::from(new HtmlString('<b>x</b>')));
        $this->assertSame("'bad\u{FFFD}('", JsLiteral::from("bad\xC3\x28"));
        // Laravel's JSON flags don't apply: say so rather than drop them.
        $this->expectException(\InvalidArgumentException::class);
        JsLiteral::from([1], JSON_FORCE_OBJECT);
    }
}
