<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Unsaved invoice, bill, receipt and journal drafts are kept in the
 * browser's localStorage ("mybooks_draft_…") until the form is saved. They
 * hold business data, so they are cleared when the user signs out (or
 * deletes their account), and on any page shown to nobody signed in, where
 * a session that timed out or was just ended lands. On a shared computer
 * the next person must not find a half-typed invoice.
 *
 * They are never cleared for a signed-in user: the guest layout also draws
 * the error pages and confirm-password, and a broken link must not cost
 * someone the bill they were typing in another tab.
 */
class FormDraftsClearedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['mybooks.search_indexing' => true]);
    }

    private function signIn(array $permissions = ['view dashboard']): void
    {
        $this->createAuthenticatedUser($permissions);
        $this->subscription->update(['ends_at' => '2030-12-31']);
    }

    /** The data-clear-drafts script on the page, or null if there is none. */
    private function script(string $html, ?string &$mode = null): ?string
    {
        $count = preg_match_all('#<script\b[^>]*\bdata-clear-drafts="([a-z-]+)"[^>]*>(.*?)</script>#s', $html, $matches);
        $this->assertLessThanOrEqual(1, $count, 'At most one clear-drafts script on a page.');
        $mode = $count ? $matches[1][0] : null;

        return $count ? $matches[2][0] : null;
    }

    private function clearsOnLoad(string $html): bool
    {
        $script = $this->script($html, $mode);
        if ($mode === 'on-load') {
            $this->assertStringNotContainsString('addEventListener', $script);
        }

        return $mode === 'on-load';
    }

    /** A <form> posting to $action that carries data-clears-drafts. */
    private function assertFormClearsDrafts(string $html, string $action): void
    {
        $this->assertMatchesRegularExpression(
            '#<form\b(?=[^>]*\baction="'.preg_quote($action, '#').'")(?=[^>]*\bdata-clears-drafts\b)[^>]*>#i',
            $html,
            "The form posting to {$action} clears the drafts."
        );
    }

    public function test_signing_out_clears_the_drafts(): void
    {
        $this->signIn();
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertFormClearsDrafts($html, route('logout'));
        $script = $this->script($html);
        $this->assertNotNull($script);
        $this->assertStringContainsString("hasAttribute('data-clears-drafts')", $script);
        $this->assertStringContainsString("indexOf('mybooks_draft_') === 0", $script);
        $this->assertFalse($this->clearsOnLoad($html), 'Not on load for a signed-in user.');
        // It runs under the CSP: it carries the page's nonce.
        $this->assertMatchesRegularExpression('#<script\b(?=[^>]*\bnonce="[^"]+")[^>]*\bdata-clear-drafts\b#', $html);
    }

    public function test_an_auto_save_still_pending_cannot_write_a_draft_back(): void
    {
        $this->signIn(['view dashboard', 'create invoices']);
        $html = $this->get(route('invoices.create'))->assertOk()->getContent();

        $this->assertStringContainsString('window.mybooksDraftsCleared = true', $this->script($html));
        $this->assertStringContainsString('if (window.mybooksDraftsCleared) return;', $html);
    }

    public function test_deleting_the_account_and_the_verify_email_sign_out_clear_the_drafts(): void
    {
        $this->signIn();
        $this->assertFormClearsDrafts($this->get(route('profile.edit'))->assertOk()->getContent(), route('profile.destroy'));

        $this->user->forceFill(['email_verified_at' => null])->save();
        $this->assertFormClearsDrafts($this->get(route('verification.notice'))->assertOk()->getContent(), route('logout'));
    }

    public function test_pages_shown_to_nobody_signed_in_clear_drafts_left_behind(): void
    {
        // The sign-in page (a session that timed out), the home page (where
        // signing out and deleting an account land), and an error page.
        $this->assertTrue($this->clearsOnLoad($this->get(route('login'))->assertOk()->getContent()), 'login');
        $this->assertTrue($this->clearsOnLoad($this->get(route('home'))->assertOk()->getContent()), 'home');
        $this->assertTrue($this->clearsOnLoad($this->get('/this-page-does-not-exist')->assertNotFound()->getContent()), '404');
    }

    public function test_pages_shown_to_a_signed_in_user_never_clear_drafts_on_load(): void
    {
        $this->signIn();

        // Error pages and confirm-password share the guest layout.
        $this->assertFalse($this->clearsOnLoad($this->get('/this-page-does-not-exist')->assertNotFound()->getContent()), '404');
        $this->assertFalse($this->clearsOnLoad($this->get(route('settings.roles'))->assertForbidden()->getContent()), '403');
        $this->assertFalse($this->clearsOnLoad($this->get(route('password.confirm'))->assertOk()->getContent()), 'confirm-password');
        // Nor do the public pages.
        $this->assertFalse($this->clearsOnLoad($this->get(route('pricing'))->assertOk()->getContent()), 'pricing');
    }
}
