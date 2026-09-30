<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every platform admin must use two-factor authentication (finding S2).
 *
 * - No second factor set up yet: only the setup page is open.
 * - Set up, but not completed in this session (for example an old
 *   remember-me cookie): signed out and sent back to the login page.
 */
class EnsureAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return $next($request);
        }

        if (! $admin->hasTwoFactorEnabled()) {
            return redirect()->route('admin.two-factor.setup')
                ->with('error', 'Please set up two-factor authentication to continue.');
        }

        if (! $request->session()->get('admin_two_factor_verified')) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->with('error', 'Please sign in again.');
        }

        return $next($request);
    }
}
