<?php

namespace App\Http\Controllers;

use App\Support\PublicSite\Guides;
use Illuminate\Http\Response;

/**
 * Search-engine files for the public site (home page plan H5): the sitemap
 * lists the public pages, and robots.txt points to it and keeps crawlers
 * out of the signed-in app. With mybooks.search_indexing off (staging and
 * test copies) there is no sitemap, and every page says "noindex" instead.
 */
class PublicSiteController extends Controller
{
    /** The public pages, most important first. */
    public const PAGES = ['home', 'pricing', 'about', 'contact', 'support', 'guides.index', 'privacy-policy', 'terms-of-service'];

    public function sitemap(Guides $guides): Response
    {
        abort_unless(config('mybooks.search_indexing'), 404);

        // The guides are added here rather than listed by hand, so an
        // article is never written and then left out of the sitemap.
        $urls = collect(self::PAGES)->map(fn (string $name) => route($name))
            ->concat($guides->all()->map(fn (array $guide) => route('guides.show', $guide['slug'])));

        return response()
            ->view('public-site.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
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
        if (config('mybooks.search_indexing')) {
            $lines[] = '';
            $lines[] = 'Sitemap: '.route('sitemap');
        }

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
