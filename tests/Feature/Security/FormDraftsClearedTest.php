<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Unsaved invoice, bill, receipt and journal drafts are kept in the
 * browser's localStorage ("mybooks_draft_…") until the form is saved. They
 * hold business data, so they are cleared when the user signs out, and on
 * the sign-in pages, where a session that timed out lands. On a shared
 * computer the next person must not find a half-typed invoice.
 */
class FormDraftsClearedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_signing_out_clears_the_drafts(): void
    {
        $this->createAuthenticatedUser(['view dashboard']);
        $this->subscription->update(['ends_at' => '2030-12-31']);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        // The sign-out form is marked, and the page listens for it.
        $this->assertMatchesRegularExpression('#<form method="POST" action="'.preg_quote(route('logout'), '#').'" data-clears-drafts>#', $html);
        $script = $this->clearDraftsScript($html);
        $this->assertStringContainsString("hasAttribute('data-clears-drafts')", $script);
        $this->assertStringContainsString("indexOf('mybooks_draft_') === 0", $script);
        // It runs under the CSP: it carries the page's nonce.
        $this->assertMatchesRegularExpression('/<script nonce="[^"]+" data-clear-drafts>/', $html);
    }

    public function test_the_sign_in_page_clears_drafts_left_by_a_session_that_timed_out(): void
    {
        $script = $this->clearDraftsScript($this->get(route('login'))->assertOk()->getContent());

        $this->assertMatchesRegularExpression('/\n\s*clearDrafts\(\);\n/', $script, 'Cleared as the page loads.');
        $this->assertStringNotContainsString('addEventListener', $script);
    }

    private function clearDraftsScript(string $html): string
    {
        $this->assertSame(1, preg_match('#<script[^>]*data-clear-drafts>(.*?)</script>#s', $html, $match), 'The page has the clear-drafts script once.');

        return $match[1];
    }
}
