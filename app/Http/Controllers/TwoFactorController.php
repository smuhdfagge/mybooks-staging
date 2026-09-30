<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactor
    ) {}

    /**
     * Show the 2FA setup page.
     */
    public function setup(Request $request): View
    {
        $user = $request->user();

        // Already on: show the status page only. Switching phones means
        // disabling first (password required), then setting up again.
        if (! is_null($user->two_factor_confirmed_at)) {
            session()->forget('two_factor_secret');

            return view('auth.two-factor.setup', [
                'qrCodeSvg' => null,
                'secret' => null,
                'isEnabled' => true,
            ]);
        }

        $secret = $this->twoFactor->generateSecret();
        $qrCodeSvg = $this->twoFactor->generateQrCodeSvg($user, $secret);

        // Store secret temporarily in session
        session(['two_factor_secret' => $secret]);

        return view('auth.two-factor.setup', [
            'qrCodeSvg' => $qrCodeSvg,
            'secret' => $secret,
            'isEnabled' => false,
        ]);
    }

    /**
     * Confirm and enable 2FA.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        if (! is_null($request->user()->two_factor_confirmed_at)) {
            session()->forget('two_factor_secret');

            return redirect()->route('two-factor.setup')
                ->with('error', 'Two-factor authentication is already on. Disable it first to move it to another device.');
        }

        $secret = session('two_factor_secret');

        if (! $secret) {
            return redirect()->route('two-factor.setup')
                ->with('error', 'Session expired. Please try setting up 2FA again.');
        }

        $step = $this->twoFactor->verify($secret, $request->code);

        if ($step === null) {
            return back()->with('error', 'Invalid verification code. Please try again.');
        }

        $user = $request->user();
        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => $this->twoFactor->hashRecoveryCodes($recoveryCodes),
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_at' => $step,
        ])->save();

        session()->forget('two_factor_secret');

        ActivityLogService::log2FAEnabled($user);

        // Codes are stored hashed, so this is the only time they can be shown (S7).
        return redirect()->route('two-factor.recovery-codes')
            ->with('two_factor_new_recovery_codes', $recoveryCodes)
            ->with('success', 'Two-factor authentication has been enabled. Save your recovery codes.');
    }

    /**
     * Disable 2FA.
     */
    public function disable(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if (! \Hash::check($request->password, $request->user()->password)) {
            return back()->with('error', 'Invalid password.');
        }

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_at' => null,
        ])->save();

        ActivityLogService::log2FADisabled($request->user());

        return redirect()->route('profile.edit')
            ->with('success', 'Two-factor authentication has been disabled.');
    }

    /**
     * Show recovery codes. New codes are shown once, straight after they are
     * made; after that only how many are left (S7).
     */
    public function recoveryCodes(Request $request): View
    {
        return view('auth.two-factor.recovery-codes', [
            'recoveryCodes' => session('two_factor_new_recovery_codes', []),
            'codesLeft' => $this->twoFactor->recoveryCodesLeft($request->user()),
        ]);
    }

    /**
     * Regenerate recovery codes.
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $request->user()->forceFill([
            'two_factor_recovery_codes' => $this->twoFactor->hashRecoveryCodes($recoveryCodes),
        ])->save();

        return redirect()->route('two-factor.recovery-codes')
            ->with('two_factor_new_recovery_codes', $recoveryCodes)
            ->with('success', 'Recovery codes have been regenerated.');
    }

    /**
     * Show the 2FA challenge page (during login).
     */
    public function challenge(): View
    {
        return view('auth.two-factor.challenge');
    }

    /**
     * Verify the 2FA challenge code.
     */
    public function verifyChallengeCode(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        $userId = session('two_factor:user_id');
        $remember = session('two_factor:remember', false);

        if (! $userId) {
            return redirect()->route('login')
                ->with('error', 'Session expired. Please login again.');
        }

        $user = \App\Models\User::findOrFail($userId);

        if (! $request->filled('code') && ! $request->filled('recovery_code')) {
            return back()->with('error', 'Please enter a code.');
        }

        // Per-account limit on top of the per-IP route throttle (S7).
        if ($this->twoFactor->tooManyAttempts($user)) {
            $minutes = (int) ceil($this->twoFactor->secondsUntilUnlocked($user) / 60);

            return back()->with('error', "Too many attempts. Please try again in {$minutes} minute(s).");
        }

        $valid = $request->filled('code')
            ? $this->twoFactor->verifyForUser($user, (string) $request->code)
            : $this->twoFactor->useRecoveryCode($user, (string) $request->recovery_code);

        if (! $valid) {
            $this->twoFactor->recordFailedAttempt($user);

            return back()->with('error', $request->filled('code') ? 'Invalid authentication code.' : 'Invalid recovery code.');
        }

        $this->twoFactor->clearAttempts($user);

        // Complete login
        session()->forget(['two_factor:user_id', 'two_factor:remember']);
        session(['two_factor_verified' => true]);

        \Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
