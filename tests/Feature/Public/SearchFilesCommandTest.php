<?php

namespace Tests\Feature\Public;

use App\Support\PublicSite\CanonicalUrl;
use App\Support\PublicSite\FeaturePages;
use App\Support\PublicSite\Guides;
use App\Support\PublicSite\SearchFiles;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * 'php artisan site:search-files' writes robots.txt and sitemap.xml as real
 * files in public/, which is what the server serves for those two addresses
 * (it will not pass a .xml or .txt address to Laravel). The deploy runs it,
 * so publishing a guide is enough to get it into the sitemap.
 */
class SearchFilesCommandTest extends TestCase
{
    private string $sitemap;

    private string $robots;

    /** @var array<string, string|false> what was there before, to put back. */
    private array $before = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sitemap = public_path('sitemap.xml');
        $this->robots = public_path('robots.txt');

        // These are real files in a real public/ folder, so leave it as it
        // was found whatever this test does.
        foreach ([$this->sitemap, $this->robots] as $path) {
            $this->before[$path] = File::exists($path) ? File::get($path) : false;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->before as $path => $contents) {
            $contents === false ? File::delete($path) : File::put($path, $contents);
        }

        parent::tearDown();
    }

    public function test_it_writes_both_files_with_every_page_in_them(): void
    {
        config(['mybooks.search_indexing' => true]);
        URL::forceRootUrl('https://mybooks.cloud');
        config(['app.url' => 'https://mybooks.cloud']);
        File::delete([$this->sitemap, $this->robots]);

        $this->artisan('site:search-files')->assertSuccessful();

        $this->assertFileExists($this->sitemap);
        $this->assertFileExists($this->robots);

        $xml = File::get($this->sitemap);
        $guides = app(Guides::class)->all();
        $features = app(FeaturePages::class)->all();

        // Not six pages, which is what the hand-written file on the server
        // was stuck at: every fixed page, feature page and guide.
        $this->assertSame(
            count(SearchFiles::PAGES) + $features->count() + $guides->count(),
            substr_count($xml, '<loc>')
        );
        foreach ($guides as $guide) {
            $this->assertStringContainsString(CanonicalUrl::route('guides.show', $guide['slug']), $xml);
        }
        foreach ($features as $page) {
            $this->assertStringContainsString(CanonicalUrl::route('features.show', $page['slug']), $xml);
        }

        $this->assertStringContainsString('Sitemap: '.CanonicalUrl::route('sitemap'), File::get($this->robots));
    }

    public function test_the_written_files_say_the_same_as_the_application_does(): void
    {
        config(['mybooks.search_indexing' => true]);
        URL::forceRootUrl('https://mybooks.cloud');
        config(['app.url' => 'https://mybooks.cloud']);

        $this->artisan('site:search-files')->assertSuccessful();

        // The command writes what the application itself answers, rather
        // than assembling its own version, so a file on the server cannot
        // disagree with the routes it stands in for.
        $files = app(SearchFiles::class);
        $this->assertSame($files->sitemapXml(), File::get($this->sitemap));
        $this->assertSame($files->robotsTxt(), File::get($this->robots));
    }

    public function test_a_copy_that_is_not_indexed_gets_no_sitemap(): void
    {
        config(['mybooks.search_indexing' => false]);
        File::put($this->sitemap, 'left over from when this copy was indexed');

        $this->artisan('site:search-files')->assertSuccessful();

        // A staging copy serving a sitemap of itself is how a test site
        // ends up in search results.
        $this->assertFileDoesNotExist($this->sitemap);
        $this->assertStringNotContainsString('Sitemap:', File::get($this->robots));
        $this->assertStringContainsString('Disallow: /dashboard', File::get($this->robots));
    }

    public function test_it_refuses_to_write_localhost_addresses(): void
    {
        config(['mybooks.search_indexing' => true]);
        URL::forceRootUrl('http://localhost');
        config(['app.url' => 'http://localhost']);
        File::delete($this->sitemap);

        $this->artisan('site:search-files')->assertFailed();

        // Nothing written is better than a sitemap telling Google that
        // every page of MyBooks lives on localhost.
        $this->assertFileDoesNotExist($this->sitemap);
    }

    public function test_running_it_again_changes_nothing(): void
    {
        config(['mybooks.search_indexing' => true]);
        URL::forceRootUrl('https://mybooks.cloud');
        config(['app.url' => 'https://mybooks.cloud']);

        $this->artisan('site:search-files')->assertSuccessful();
        $once = [File::get($this->sitemap), File::get($this->robots)];

        $this->artisan('site:search-files')->assertSuccessful();

        $this->assertSame($once, [File::get($this->sitemap), File::get($this->robots)]);
    }
}
