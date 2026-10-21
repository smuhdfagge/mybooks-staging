<?php

namespace App\Http\Controllers;

use App\Support\PublicSite\SearchFiles;
use Illuminate\Http\Response;

/**
 * Search-engine files for the public site (home page plan H5): the sitemap
 * lists the public pages, and robots.txt points to it and keeps crawlers
 * out of the signed-in app. With mybooks.search_indexing off (staging and
 * test copies) there is no sitemap, and every page says "noindex" instead.
 *
 * What goes in them is SearchFiles' business, because the deploy writes
 * the same two files into public/ for a web server that will not pass a
 * .xml or .txt address to Laravel at all.
 */
class PublicSiteController extends Controller
{
    public function sitemap(SearchFiles $files): Response
    {
        abort_unless($files->indexing(), 404);

        return response($files->sitemapXml())
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(SearchFiles $files): Response
    {
        return response($files->robotsTxt())
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
