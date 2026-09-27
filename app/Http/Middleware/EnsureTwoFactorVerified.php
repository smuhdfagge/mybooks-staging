<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the user has completed 2FA verification if enabled.
 * Redirects to the 2FA challenge page if verification is pending.
 */
class EnsureTwoFactorVerified
{
    /**
     * Routes that are exempt from 2FA verification.
     */
    protected array $exempt = [
        'two-factor.*',
        'logout',
        'login',
        'register',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // No user = no 2FA check needed
        if (!$user) {
            return $next($request);
        }

        // 2FA not enabled for this user
        if (is_null($user->two_factor_confirmed_at)) {
            return $next($request);
        }

        // Already verified this session
        if (session('two_factor_verified')) {
            return $next($request);
        }

        // Allow exempt routes
        if ($this->isExempt($request)) {
            return $next($request);
        }

        // Redirect to 2FA challenge
        return redirect()->route('two-factor.challenge');
    }

    protected function isExempt(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if (!$routeName) {
            return false;
        }

        foreach ($this->exempt as $pattern) {
            if (str_is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }
}
