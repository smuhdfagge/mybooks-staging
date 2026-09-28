<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides modules that aren't finished yet (finding N4).
 *
 * Usage: ->middleware('feature:quotations'). The route stays registered, so
 * route() calls keep working, but it answers 404 unless
 * config('mybooks.features.quotations') is true.
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(static::enabled($feature), 404);

        return $next($request);
    }

    public static function enabled(string $feature): bool
    {
        return (bool) config("mybooks.features.{$feature}", false);
    }
}
