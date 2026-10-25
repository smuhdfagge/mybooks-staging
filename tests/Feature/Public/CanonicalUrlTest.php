<?php

namespace Tests\Feature\Public;

use App\Support\PublicSite\SearchFiles;
use Tests\TestCase;

/**
 * Every public page names one address, whichever way it was reached.
 *
 * The canonical tag was url()->current(), which answers with the host the
 * request came in on. So the same page fetched at www said www was the
 * original, and at the bare domain said that was — every copy claiming to
 * be the one true page, which is the opposite of what the tag is for.
 * Search Console reported it as "Duplicate, Google chose different
 * canonical than user".
 */
class CanonicalUrlTest extends TestCase
{
    private const SITE = 'https://mybooks.cloud';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.url' => self::SITE]);
    }

    public function test_a_page_reached_at_another_host_still_names_the_canonical_one(): void
    {
        // The www copy Google found, and the http one.
        foreach (['https://www.mybooks.cloud/pricing', 'http://mybooks.cloud/pricing'] as $reachedAt) {
            $html = $this->get($reachedAt)->assertOk()->getContent();

            $this->assertStringContainsString('<link rel="canonical" href="'.self::SITE.'/pricing">', $html);
            $this->assertStringNotContainsString('canonical" href="https://www.', $html);
        }
    }

    public function test_the_share_address_agrees_with_the_canonical(): void
    {
        $html = $this->get('https://www.mybooks.cloud/about')->assertOk()->getContent();

        // og:url is read the same way; the two disagreeing is its own
        // duplicate signal.
        $this->assertStringContainsString('<link rel="canonical" href="'.self::SITE.'/about">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="'.self::SITE.'/about">', $html);
    }

    public function test_a_query_string_does_not_make_a_second_page(): void
    {
        // The feature pages link to /contact?subject=demo.
        $html = $this->get(self::SITE.'/contact?subject=demo')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="'.self::SITE.'/contact">', $html);
    }

    public function test_the_home_page_canonical_is_the_bare_address(): void
    {
        $html = $this->get('https://www.mybooks.cloud')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="'.self::SITE.'/">', $html);
    }

    public function test_the_structured_data_names_the_canonical_host(): void
    {
        $html = $this->get('https://www.mybooks.cloud/features/invoicing')->assertOk()->getContent();

        preg_match('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m);
        $schema = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);

        // What Google reads as the page's identity, so it must not say www
        // while the canonical says otherwise.
        $this->assertSame(self::SITE.'/features/invoicing', $schema['url']);
        $this->assertSame(self::SITE.'/', $schema['isPartOf']['url']);
    }

    public function test_the_sitemap_lists_canonical_addresses(): void
    {
        config(['mybooks.search_indexing' => true]);

        $urls = app(SearchFiles::class)->urls();

        // A sitemap naming one host while the pages claim another is the
        // same contradiction from the other side.
        foreach ($urls as $url) {
            $this->assertStringStartsWith(self::SITE.'/', $url, "{$url} is not on the canonical address.");
        }
        $this->assertTrue($urls->contains(self::SITE.'/guides/vat-returns-nigeria'));
    }
}
