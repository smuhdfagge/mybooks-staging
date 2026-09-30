<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Services\AdminAuditService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

/**
 * Two-factor sign-in for platform admins (finding S2). Uses the same
 * TwoFactorService as business users. Every admin must have it on.
 */
class AdminTwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactor
    ) {}

    /**
     * Code page after a correct password.
     */
    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('admin_two_factor:id')) {
            return redirect()->route('admin.login');
        }

        return view('admin.auth.two-factor-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        $admin = AdminUser::find($request->session()->get('admin_two_factor:id'));

        if (! $admin || ! $admin->is_active || ! $admin->hasTwoFactorEnabled()) {
            $request->session()->forget('admin_two_factor:id');

            return redirect()->route('admin.login')->with('error', 'Session expired. Please sign in again.');
        }

        if (! $request->filled('code') && ! $request->filled('recovery_code')) {
            return back()->with('error', 'Please enter a code.');
        }

        if ($this->twoFactor->tooManyAttempts($admin)) {
            $minutes = (int) ceil($this->twoFactor->secondsUntilUnlocked($admin) / 60);

            return back()->with('error', "Too many attempts. Please try again in {$minutes} minute(s).");
        }

        $usedRecovery = ! $request->filled('code');
        $valid = $usedRecovery
            ? $this->twoFactor->useRecoveryCode($admin, (string) $request->recovery_code)
            : $this->twoFactor->verifyForUser($admin, (string) $request->code);

        if (! $valid) {
            $this->twoFactor->recordFailedAttempt($admin);
            AdminAuditService::log(ActivityLog::ACTION_LOGIN_FAILED, 'wrong two-factor code', $admin, admin: $admin);

            return back()->with('error', $usedRecovery ? 'Invalid recovery code.' : 'Invalid authentication code.');
        }

        $this->twoFactor->clearAttempts($admin);
        $request->session()->forget('admin_two_factor:id');

        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();
        $request->session()->put('admin_two_factor_verified', true);
        EnsureAdminTwoFactor::rememberPassword($request, $admin);

        AdminAuditService::log(
            ActivityLog::ACTION_LOGIN,
            $usedRecovery ? 'signed in with a recovery code' : 'signed in with two-factor code',
            $admin
        );

        return redirect()->intended(route('admin.tenants.index'));
    }

    /**
     * First-time setup, required before any other admin page.
     */
    public function setup(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->requirePasswordThisSession($request)) {
            return $redirect;
        }

        /** @var AdminUser $admin */
        $admin = $request->user('admin');

        // Already on: it can't be replaced from here (no password-only takeover).
        if ($admin->hasTwoFactorEnabled()) {
            return redirect()->route('admin.tenants.index');
        }

        $secret = $this->twoFactor->generateSecret();
        $request->session()->put('admin_two_factor_secret', $secret);

        return view('admin.auth.two-factor-setup', [
            'qrCodeSvg' => $this->twoFactor->generateQrCodeSvg($admin, $secret),
            'secret' => $secret,
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|string|size:6']);

        if ($redirect = $this->requirePasswordThisSession($request)) {
            return $redirect;
        }

        /** @var AdminUser $admin */
        $admin = $request->user('admin');

        if ($admin->hasTwoFactorEnabled()) {
            return redirect()->route('admin.tenants.index');
        }

        $secret = $request->session()->get('admin_two_factor_secret');

        if (! $secret) {
            return redirect()->route('admin.two-factor.setup')->with('error', 'Session expired. Please try again.');
        }

        $step = $this->twoFactor->verify($secret, $request->code);

        if ($step === null) {
            return back()->with('error', 'Invalid verification code. Please try again.');
        }

        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $admin->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => $this->twoFactor->hashRecoveryCodes($recoveryCodes),
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_at' => $step,
        ])->save();

        $request->session()->forget('admin_two_factor_secret');
        $request->session()->put('admin_two_factor_verified', true);
        EnsureAdminTwoFactor::rememberPassword($request, $admin);

        AdminAuditService::log(ActivityLog::ACTION_2FA_ENABLED, 'turned on two-factor authentication', $admin);

        return redirect()->route('admin.two-factor.recovery-codes')
            ->with('admin_new_recovery_codes', $recoveryCodes);
    }

    /**
     * Shows new recovery codes once, straight after setup.
     */
    public function recoveryCodes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get('admin_new_recovery_codes');

        if (! $codes) {
            return redirect()->route('admin.tenants.index');
        }

        return view('admin.auth.two-factor-recovery-codes', ['recoveryCodes' => $codes]);
    }

    /**
     * Setting up 2FA needs a password sign-in in this session, not just a
     * leftover cookie.
     */
    private function requirePasswordThisSession(Request $request): ?RedirectResponse
    {
        if ($request->session()->get('admin_password_verified')) {
            return null;
        }

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('error', 'Please sign in again.');
    }
}
