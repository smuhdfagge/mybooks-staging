<?php

namespace Tests\Feature\Public;

use App\Models\User;
use App\Support\PublicSite\SearchFiles;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Google Analytics on the public pages (SEO plan).
 *
 * Three things have to hold: it loads on the public pages when an id is
 * set, it is nowhere near the signed-in app, and the Content-Security-
 * Policy lets it send what it collects. That last one is the trap — the
 * policy blocks it silently, so the page looks perfect and no figures
 * ever arrive.
 */
class AnalyticsTest extends TestCase
{
    private const ID = 'G-TESTID1234';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public static function publicPages(): array
    {
        return array_map(fn ($p) => [$p], SearchFiles::PAGES);
    }

    #[DataProvider('publicPages')]
    public function test_every_public_page_carries_the_tag(string $route): void
    {
        config(['mybooks.google_analytics_id' => self::ID]);

        $this->get(route($route))->assertOk()
            ->assertSee('googletagmanager.com/gtag/js?id='.self::ID, false)
            ->assertSee("gtag('config', '".self::ID."')", false);
    }

    public function test_nothing_is_written_when_no_id_is_set(): void
    {
        config(['mybooks.google_analytics_id' => '']);

        // Not an empty tag or a tag with a blank id: nothing at all, so a
        // copy without analytics sends nowhere and loads nothing.
        $this->get(route('home'))->assertOk()
            ->assertDontSee('googletagmanager', false)
            ->assertDontSee('gtag', false);
    }

    public function test_the_signed_in_app_is_never_measured(): void
    {
        config(['mybooks.google_analytics_id' => self::ID]);
        $user = User::factory()->create();

        // What a business does inside its own books is not ours to send to
        // Google.
        $this->actingAs($user)->get(route('dashboard'))
            ->assertDontSee('googletagmanager', false);
    }

    public function test_the_script_carries_the_nonce_the_policy_demands(): void
    {
        config(['mybooks.google_analytics_id' => self::ID]);

        $html = $this->get(route('home'))->assertOk()->getContent();
        preg_match('/<script async src="https:\/\/www\.googletagmanager\.com[^>]*>/', $html, $m);

        // The policy is nonce-based with 'strict-dynamic', so a script
        // without the nonce is blocked outright.
        $this->assertNotEmpty($m, 'The gtag.js tag was not found.');
        $this->assertStringContainsString('nonce="', $m[0]);
    }

    public function test_the_policy_lets_it_send_what_it_collects(): void
    {
        config(['mybooks.google_analytics_id' => self::ID]);

        $csp = $this->get(route('home'))->assertOk()->headers->get('Content-Security-Policy');
        [$connect] = array_values(array_filter(explode('; ', $csp), fn ($d) => str_starts_with($d, 'connect-src')));
        [$img] = array_values(array_filter(explode('; ', $csp), fn ($d) => str_starts_with($d, 'img-src')));

        $this->assertStringContainsString('https://*.google-analytics.com', $connect);
        $this->assertStringContainsString('https://*.analytics.google.com', $connect);
        $this->assertStringContainsString('https://*.google-analytics.com', $img);
    }

    public function test_the_policy_stays_tight_without_an_id(): void
    {
        config(['mybooks.google_analytics_id' => '']);

        $csp = $this->get(route('home'))->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("connect-src 'self';", $csp);
        $this->assertStringNotContainsString('google-analytics', $csp);
    }
}
