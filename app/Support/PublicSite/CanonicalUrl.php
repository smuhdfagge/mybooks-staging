<?php

namespace App\Support\PublicSite;

use Illuminate\Http\Request;

/**
 * The one address a public page admits to living at.
 *
 * The canonical tag used to be url()->current(), which echoes back
 * whatever host and scheme the request arrived on. A page fetched at
 * www.mybooks.cloud then declared www.mybooks.cloud canonical, and the
 * same page at mybooks.cloud declared that one — so every page claimed
 * to be the original and nothing was ever pointed at anything. A
 * self-referential canonical cannot resolve a duplicate, which is the
 * whole job of the tag.
 *
 * It is built from APP_URL instead, so every copy of a page names the
 * same address whichever way it was reached, and that address is the one
 * in the sitemap.
 */
class CanonicalUrl
{
    public static function for(?Request $request = null): string
    {
        $request ??= request();

        return self::base().self::path($request->path());
    }

    /** The address as the sitemap and robots.txt give it, without a trailing slash. */
    public static function base(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /**
     * A named route on the canonical address.
     *
     * route() answers with the host the request came in on, which is
     * right for a link someone clicks and wrong for anything a search
     * engine reads as an identity: structured data, the sitemap. Those
     * use this.
     *
     * @param  array<string|int, mixed>|string|int  $parameters
     */
    public static function route(string $name, array|string|int $parameters = []): string
    {
        return self::base().self::path((string) parse_url(route($name, $parameters), PHP_URL_PATH));
    }

    /**
     * The path, leading slash, no trailing one, and no query string.
     *
     * The query string is deliberately dropped. /contact?subject=demo is
     * the contact page, not a second page, and the feature pages link to
     * it that way.
     */
    private static function path(string $path): string
    {
        $path = trim($path, '/');

        return $path === '' ? '/' : '/'.$path;
    }
}
