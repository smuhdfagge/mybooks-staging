<?php

namespace Tests\Feature\Regression\Audit2610;

use App\Models\Role;
use Tests\TestCase;

/**
 * October 2026 audit, fix session 18 (layout and accessibility):
 * - MB-23: html, body { overflow-x: hidden } made the body the scroller;
 * - MB-24: the customer/supplier dropdown toggle had no name (16 forms) and
 *   the role permission matrix was about 124 unlabelled checkboxes;
 * - MB-43: other unlabelled controls (journal lines, discount type, flash
 *   dismiss) and misnested definition lists;
 * - MB-57: the mobile drawer didn't close with Escape or say it was open;
 * - MB-58: the install banner came up over every page, its close unnamed.
 *
 * The rendered checks (axe, 390px overflow, Escape) were run in a browser
 * against the app; these hold the markup in place.
 */
class Session18LayoutAccessibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->createAuthenticatedUser([
            'view dashboard', 'view invoices', 'create invoices', 'view bills', 'create bills', 'view journals', 'create journals',
            'view roles', 'create roles', 'edit roles',
        ]);
        $this->subscription->update(['ends_at' => '2030-12-31']);
    }

    public function test_the_page_scrolls_as_a_document_and_the_drawer_closes_by_keyboard(): void
    {
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('overflow-x: hidden', $html);
        $this->assertStringContainsString('@keydown.escape.window="closeSidebar()"', $html);
        $this->assertStringContainsString('aria-controls="app-sidebar" :aria-expanded="sidebarOpen.toString()"', $html);
        $this->assertStringContainsString('<aside id="app-sidebar"', $html);
        $this->assertStringContainsString('x-ref="menuButton"', $html);
    }

    public function test_the_install_banner_waits_for_the_person_and_its_close_is_named(): void
    {
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('@click="dismissPrompt()" aria-label="Dismiss"', $html);
        $this->assertStringContainsString("sessionStorage.getItem('pwa-prompt-shown')", $html);
        $this->assertStringContainsString("['pointerdown', 'keydown']", $html);
    }

    public function test_the_customer_and_supplier_dropdown_toggles_are_named(): void
    {
        foreach (['invoices.create', 'bills.create'] as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertStringContainsString('aria-label="Show options" :aria-expanded="open.toString()" aria-controls="party-options"', $html, $route);
            $this->assertStringContainsString('id="party-options"', $html, $route);
        }
        $this->assertStringContainsString('aria-label="Discount type"', $this->get(route('invoices.create'))->getContent());
    }

    public function test_journal_lines_are_named(): void
    {
        $html = $this->get(route('journals.create'))->assertOk()->getContent();

        $this->assertSame(substr_count($html, '[account_id]" required'), substr_count($html, '[account_id]" required aria-label="Account"'));
        $this->assertStringContainsString('aria-label="Remove line" class="remove-row-btn', $html);
    }

    public function test_every_permission_checkbox_on_the_role_screens_is_named(): void
    {
        $role = Role::create(['name' => 'Clerk', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);

        foreach ([route('settings.roles.create'), route('settings.roles.edit', $role)] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('aria-label="Invoices: Create"', $html, $url);
            $this->assertStringContainsString('aria-label="Toggle all for Invoices"', $html, $url);
            $this->assertStringNotContainsString('aria-label="Not used"', $html, 'aria-label on a plain span is not announced');

            // Each matrix checkbox carries a name, or sits inside a <label>.
            preg_match_all('/<input type="checkbox"[^>]*>/', $html, $boxes);
            foreach ($boxes[0] as $box) {
                if (str_contains($box, 'aria-label=')) {
                    continue;
                }
                $at = strpos($html, $box);
                $this->assertMatchesRegularExpression('/<label[^>]*>\s*$/', substr($html, max(0, $at - 300), 300), "unnamed: {$box}");
            }
        }
    }

    public function test_flash_messages_can_be_dismissed_by_name(): void
    {
        $this->withSession(['success' => 'Saved.'])->get(route('dashboard'))
            ->assertOk()->assertSee('@click="show = false" aria-label="Dismiss"', false);
    }
}
