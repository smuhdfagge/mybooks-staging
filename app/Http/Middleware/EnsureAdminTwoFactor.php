<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
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
 * - The admin's password changed since this session signed in: signed out,
 *   so a password change ends every other admin session (S5).
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

        $fingerprint = $request->session()->get('admin_password_hash');

        if (! $request->session()->get('admin_two_factor_verified')
            || ! is_string($fingerprint) || ! hash_equals(self::passwordFingerprint($admin), $fingerprint)) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->with('error', 'Please sign in again.');
        }

        return $next($request);
    }

    /**
     * What the session keeps to notice a password change (not the hash itself).
     */
    public static function passwordFingerprint(AdminUser $admin): string
    {
        return hash_hmac('sha256', (string) $admin->password, (string) config('app.key'));
    }

    public static function rememberPassword(Request $request, AdminUser $admin): void
    {
        $request->session()->put('admin_password_hash', self::passwordFingerprint($admin));
    }
}
