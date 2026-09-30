<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Services\AdminAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    /** Wrong passwords allowed per admin account before a 15-minute lockout (S2). */
    public const MAX_ATTEMPTS = 5;

    public const LOCKOUT_SECONDS = 900;

    /**
     * Show admin login form
     */
    public function showLoginForm()
    {
        return view('admin.auth.login');
    }

    /**
     * Handle admin login: password first, then the second factor (S2).
     * There is no remember-me for admins.
     */
    public function login(AdminLoginRequest $request)
    {
        $credentials = $request->validated();
        $email = strtolower(trim($credentials['email']));
        $lockKey = 'admin-login:'.$email;

        // Per-account lockout on top of the per-IP route throttle.
        if (RateLimiter::tooManyAttempts($lockKey, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($lockKey) / 60);

            throw ValidationException::withMessages([
                'email' => "Too many failed sign-in attempts. Please try again in {$minutes} minute(s).",
            ]);
        }

        $admin = AdminUser::where('email', $credentials['email'])->first();

        if (! $admin || ! Hash::check($credentials['password'], $admin->password)) {
            $locked = RateLimiter::hit($lockKey, self::LOCKOUT_SECONDS) >= self::MAX_ATTEMPTS;

            AdminAuditService::log(
                $locked ? ActivityLog::ACTION_ACCOUNT_LOCKED : ActivityLog::ACTION_LOGIN_FAILED,
                $locked ? "admin sign-in locked for '{$email}' after repeated wrong passwords" : "failed admin sign-in for '{$email}'",
                $admin,
                admin: $admin,
                actorLabel: $email,
            );

            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        if (! $admin->is_active) {
            AdminAuditService::log(ActivityLog::ACTION_LOGIN_FAILED, 'sign-in refused: account deactivated', $admin, admin: $admin);

            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated.',
            ]);
        }

        RateLimiter::clear($lockKey);
        $request->session()->regenerate();

        if ($admin->hasTwoFactorEnabled()) {
            // Not signed in yet: the code is asked for next.
            $request->session()->put('admin_two_factor:id', $admin->id);

            return redirect()->route('admin.two-factor.challenge');
        }

        // No second factor yet: sign in, but every admin page sends them to
        // set it up first (EnsureAdminTwoFactor).
        Auth::guard('admin')->login($admin);
        $request->session()->put('admin_password_verified', true);
        AdminAuditService::log(ActivityLog::ACTION_LOGIN, 'signed in with password; must set up two-factor authentication', $admin);

        return redirect()->route('admin.two-factor.setup');
    }

    /**
     * Handle admin logout
     */
    public function logout(Request $request)
    {
        if ($admin = Auth::guard('admin')->user()) {
            AdminAuditService::log(ActivityLog::ACTION_LOGOUT, 'signed out', $admin);
        }

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
