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
}
