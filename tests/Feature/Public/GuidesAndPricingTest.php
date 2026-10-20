<?php

namespace Tests\Feature\Public;

use App\Support\PublicSite\Guides;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The guides and the pricing page (SEO plan). What matters for search is
 * that each page has its own title, description, canonical address and
 * structured data, and that an article reaches the sitemap by itself
 * rather than being added to a list by hand.
 */
class GuidesAndPricingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['mybooks.search_indexing' => true]);
    }

    public function test_pricing_is_its_own_page_with_its_own_title(): void
    {
        $html = $this->get(route('pricing'))->assertOk()
            ->assertSee('MyBooks pricing, in naira')
            ->assertSee('Questions about paying')
            ->getContent();

        // Its own title, description and canonical address, not the home page's.
        $this->assertStringContainsString('<title>Pricing in naira · MyBooks</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.route('pricing').'">', $html);
        $this->assertMatchesRegularExpression('/<meta name="description" content="See what MyBooks costs/', $html);
        // The questions are given to search engines as well as to the reader.
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
    }

    public function test_the_guides_listing_shows_each_article(): void
    {
        $this->get(route('guides.index'))->assertOk()
            ->assertSee('Guides')
            ->assertSee('How to file VAT returns in Nigeria (2026)')
            ->assertSee(route('guides.show', 'vat-returns-nigeria'), false);
    }

    public function test_an_article_carries_what_a_search_engine_needs(): void
    {
        $html = $this->get(route('guides.show', 'vat-returns-nigeria'))->assertOk()
            ->assertSee('How to file VAT returns in Nigeria (2026)')
            ->assertSee('The 21st of the following month')
            ->assertSee('Last checked')
            ->getContent();

        // Its own title and description, and one h1.
        $this->assertStringContainsString('<title>How to File VAT Returns in Nigeria · MyBooks</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.route('guides.show', 'vat-returns-nigeria').'">', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'A page has exactly one h1.');

        // Article structured data, with real dates rather than today's.
        $this->assertStringContainsString('"@type":"Article"', $html);
        $this->assertStringContainsString('"datePublished":"2026-10-21"', $html);

        // The markdown is rendered, not shown as markdown.
        $this->assertStringContainsString('<h2>', $html);
        $this->assertStringNotContainsString('## When it is due', $html);
    }

    public function test_an_article_sends_the_reader_on_to_the_product(): void
    {
        $this->get(route('guides.show', 'vat-returns-nigeria'))->assertOk()
            ->assertSee(route('pricing'), false)
            ->assertSee('MyBooks does this for you');
    }

    public function test_the_sitemap_picks_up_articles_without_being_told(): void
    {
        $xml = $this->get(route('sitemap'))->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        foreach ([route('home'), route('pricing'), route('guides.index'), route('guides.show', 'vat-returns-nigeria')] as $url) {
            $this->assertStringContainsString('<loc>'.$url.'</loc>', $xml);
        }
    }

    public function test_a_new_article_needs_no_code_change_to_be_published(): void
    {
        $slug = 'test-only-guide-'.now()->timestamp;
        $path = resource_path('guides/'.$slug.'.md');
        File::put($path, "---\ntitle: A Test Guide\nheading: A test guide\ndescription: Checking a file is all it takes.\npublished: 2026-10-20\n---\n\n## A heading\n\nSome words.\n");

        try {
            $this->get(route('guides.show', $slug))->assertOk()->assertSee('A test guide')->assertSee('Some words.');
            $this->get(route('guides.index'))->assertOk()->assertSee('A test guide');
            $this->assertStringContainsString('<loc>'.route('guides.show', $slug).'</loc>', $this->get(route('sitemap'))->getContent());
        } finally {
            File::delete($path);
        }
    }

    public function test_an_unknown_or_unsafe_address_is_not_found(): void
    {
        $this->get(route('guides.show', 'no-such-guide'))->assertNotFound();
        $this->get('/guides/'.urlencode('../../.env'))->assertNotFound();
        $this->assertNull(app(Guides::class)->find('../../.env'));
        $this->assertNull(app(Guides::class)->find('Not A Slug'));
    }

    public function test_the_public_pages_link_to_the_guides_and_pricing(): void
    {
        // Internal links are how a search engine finds a new page at all.
        $home = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString(e(route('pricing')), $home);
        $this->assertStringContainsString(e(route('guides.index')), $home);
    }

    public function test_guides_stay_hidden_while_indexing_is_off(): void
    {
        config(['mybooks.search_indexing' => false]);

        $this->get(route('sitemap'))->assertNotFound();
        $this->get(route('guides.show', 'vat-returns-nigeria'))->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }
}
