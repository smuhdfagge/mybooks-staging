<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sign out users whose account, or whose organisation, has been deactivated.
 *
 * Runs on every authenticated web and API request, so deactivating a user
 * or suspending a tenant from the admin panel takes effect immediately
 * (finding H1). API tokens of deactivated users are revoked.
 */
class EnsureAccountActive
{
    public const MESSAGE = 'This account has been deactivated. Please contact your administrator.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || static::isActive($user)) {
            return $next($request);
        }

        // API clients get a JSON 403 and lose their tokens. Everything else,
        // including Livewire updates (which re-run this middleware and act
        // on redirects), is signed out and sent to the login page.
        if ($request->is('api/*')) {
            $user->tokens()->delete();

            return response()->json(['message' => self::MESSAGE], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => self::MESSAGE]);
    }

    public static function isActive($user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return ! $user->tenant || $user->tenant->is_active;
    }
}
