<?php

namespace Tests\Feature\Public;

use App\Http\Controllers\PublicSiteController;
use App\Models\Plan;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Home page plan, session H5: search and sharing. Every public page has a
 * canonical address and share tags; the home page tells search engines its
 * prices (from the plans table) and its questions (the ones on the page);
 * the sitemap and robots.txt list the public pages.
 */
class SearchAndSharingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public static function pages(): array
    {
        return array_map(fn ($p) => [$p], PublicSiteController::PAGES);
    }

    #[DataProvider('pages')]
    public function test_page_has_share_tags_and_a_canonical_address(string $route): void
    {
        $this->get(route($route))->assertOk()
            ->assertSee('<link rel="canonical" href="'.route($route).'">', false)
            ->assertSee('<meta property="og:image" content="'.asset('images/share/mybooks-share.png').'">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<meta property="og:locale" content="en_NG">', false);
    }

    public function test_share_picture_is_the_size_the_tags_say(): void
    {
        [$w, $h] = getimagesize(public_path('images/share/mybooks-share.png'));

        $this->assertSame([1200, 630], [$w, $h]);
        $this->assertLessThan(300 * 1024, filesize(public_path('images/share/mybooks-share.png')), 'WhatsApp skips share pictures over 300 KB.');
    }

    public function test_home_structured_data_follows_the_plans_table_and_the_page(): void
    {
        Plan::factory()->create(['name' => 'Starter', 'slug' => 'starter', 'monthly_price' => 25000, 'annual_price' => 250000, 'sort_order' => 1]);
        Plan::factory()->create(['name' => 'Professional', 'slug' => 'professional', 'monthly_price' => 50000, 'annual_price' => 500000, 'allow_monthly_billing' => false, 'sort_order' => 2]);

        $html = $this->get(route('home'))->assertOk()->getContent();
        preg_match('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m);
        $graph = collect(json_decode($m[1], true, flags: JSON_THROW_ON_ERROR)['@graph'])->keyBy('@type');

        $offers = collect($graph['SoftwareApplication']['offers']);
        $this->assertSame(['25000.00', '250000.00', '500000.00'], $offers->pluck('price')->all());
        $this->assertSame(['NGN'], $offers->pluck('priceCurrency')->unique()->values()->all());

        // The questions given to search engines are the ones on the page.
        preg_match_all('#<summary>(.*?)</summary>#', $html, $summaries);
        $questions = collect($graph['FAQPage']['mainEntity'])->pluck('name')->all();
        $this->assertSame(array_map(fn ($s) => html_entity_decode($s, ENT_QUOTES), $summaries[1]), $questions);
    }

    public function test_sitemap_lists_the_public_pages(): void
    {
        config(['mybooks.search_indexing' => true]);
        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        $this->assertCount(6, $xml->url);
        foreach (PublicSiteController::PAGES as $page) {
            $this->assertStringContainsString('<loc>'.route($page).'</loc>', $response->getContent());
        }
    }

    public function test_robots_points_to_the_sitemap_and_keeps_out_of_the_app(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'), 'A static robots.txt would hide the generated one.');
        config(['mybooks.search_indexing' => true]);

        $this->get('/robots.txt')->assertOk()
            ->assertSee('Sitemap: '.route('sitemap'))
            ->assertSee('Disallow: /dashboard')
            ->assertSee('Disallow: /admin');
    }

    public function test_public_pages_load_the_light_script_not_the_app_script(): void
    {
        $layout = File::get(resource_path('views/components/public/layout.blade.php'));

        $this->assertStringContainsString("'resources/js/public.js'", $layout);
        $this->assertStringNotContainsString("'resources/js/app.js'", $layout);
    }

    public function test_search_engines_are_kept_out_unless_switched_on(): void
    {
        config(['mybooks.search_indexing' => false]);

        $this->get(route('home'))->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get(route('login'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/sitemap.xml')->assertNotFound();
        $this->get('/robots.txt')->assertOk()->assertDontSee('Sitemap:')->assertSee('Disallow: /dashboard');

        config(['mybooks.search_indexing' => true]);

        $this->get(route('home'))->assertOk()
            ->assertHeaderMissing('X-Robots-Tag')
            ->assertDontSee('<meta name="robots"', false);
        $this->get('/sitemap.xml')->assertOk();
        $this->get('/robots.txt')->assertSee('Sitemap: '.route('sitemap'));
    }
}
