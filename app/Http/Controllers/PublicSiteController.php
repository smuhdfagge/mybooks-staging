<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Search-engine files for the public site (home page plan H5): the sitemap
 * lists the public pages, and robots.txt points to it and keeps crawlers
 * out of the signed-in app.
 */
class PublicSiteController extends Controller
{
    /** The public pages, most important first. */
    public const PAGES = ['home', 'about', 'contact', 'support', 'privacy-policy', 'terms-of-service'];

    public function sitemap(): Response
    {
        $urls = collect(self::PAGES)->map(fn (string $name) => route($name));

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
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
