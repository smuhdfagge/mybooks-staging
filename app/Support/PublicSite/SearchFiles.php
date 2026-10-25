<?php

namespace App\Support\PublicSite;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * What the search engines are told: the list of pages in the sitemap and
 * the lines of robots.txt. One place, because these are served two ways.
 *
 * The routes serve them for anything that asks. But a request for a .xml
 * or .txt address does not always reach Laravel: a web server can decide
 * for itself what to do with those endings, and ours hands back the PHP
 * file as plain text instead of running it. So the deploy also writes
 * them out as real files in public/, which the web server serves itself.
 *
 * Written on every deploy rather than kept in git, so a new guide cannot
 * be published and then left out of the sitemap, which is what happened
 * before this: the file on the server stayed at the six pages it had when
 * it was first written by hand.
 */
class SearchFiles
{
    /** The public pages that are not a guide or a feature page, most important first. */
    public const PAGES = ['home', 'pricing', 'about', 'contact', 'support', 'guides.index', 'privacy-policy', 'terms-of-service'];

    public function __construct(
        private Guides $guides,
        private FeaturePages $features,
    ) {}

    /** Whether this copy of MyBooks wants to be in search results at all. */
    public function indexing(): bool
    {
        return (bool) config('mybooks.search_indexing');
    }

    /**
     * Every public page, most important first.
     *
     * @return Collection<int, string>
     */
    public function urls(): Collection
    {
        // The guides and feature pages are added from their own lists
        // rather than named here, so an article is never written and then
        // left out of the sitemap. Addresses are on the canonical host, so
        // the sitemap cannot name one address while the page it points at
        // claims another.
        return collect(self::PAGES)
            ->map(fn (string $name) => CanonicalUrl::route($name))
            ->concat($this->features->all()->map(fn (array $page) => CanonicalUrl::route('features.show', $page['slug'])))
            ->concat($this->guides->all()->map(fn (array $guide) => CanonicalUrl::route('guides.show', $guide['slug'])));
    }

    public function sitemapXml(): string
    {
        return view('public-site.sitemap', ['urls' => $this->urls()])->render();
    }

    public function robotsTxt(): string
    {
        $lines = [
            'User-agent: *',
            // Everything behind sign-in, and the sign-up and password pages.
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /register',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
        ];

        // Crawlers may still read the public pages, so they see each page's
        // "noindex" and drop it; blocking them here would hide that.
        if ($this->indexing()) {
            $lines[] = '';
            $lines[] = 'Sitemap: '.CanonicalUrl::route('sitemap');
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Write both files into public/, or remove them where this copy is not
     * meant to be indexed, so a staging server cannot be left serving a
     * sitemap of itself.
     *
     * @return array<int, string> what was written or removed, to report
     */
    public function write(): array
    {
        $done = [];

        $sitemap = public_path('sitemap.xml');
        $robots = public_path('robots.txt');

        // robots.txt is written either way: it is what keeps crawlers out
        // of the signed-in app, which matters most where there is no
        // sitemap.
        File::put($robots, $this->robotsTxt());
        $done[] = 'wrote public/robots.txt';

        if ($this->indexing()) {
            File::put($sitemap, $this->sitemapXml());
            $done[] = 'wrote public/sitemap.xml ('.$this->urls()->count().' pages)';
        } elseif (File::exists($sitemap)) {
            File::delete($sitemap);
            $done[] = 'removed public/sitemap.xml (this copy is not indexed)';
        }

        return $done;
    }
}
