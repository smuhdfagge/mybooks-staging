<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

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

        $secret = $this->twoFactor->generateSecret();
        $qrCodeSvg = $this->twoFactor->generateQrCodeSvg($user, $secret);

        // Store secret temporarily in session
        session(['two_factor_secret' => $secret]);

        return view('auth.two-factor.setup', [
            'qrCodeSvg' => $qrCodeSvg,
            'secret' => $secret,
            'isEnabled' => !is_null($user->two_factor_confirmed_at),
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

        $secret = session('two_factor_secret');

        if (!$secret) {
            return redirect()->route('two-factor.setup')
                ->with('error', 'Session expired. Please try setting up 2FA again.');
        }

        if (!$this->twoFactor->verify($secret, $request->code)) {
            return back()->with('error', 'Invalid verification code. Please try again.');
        }

        $user = $request->user();
        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        session()->forget('two_factor_secret');

        ActivityLogService::log2FAEnabled($user);

        return redirect()->route('two-factor.recovery-codes')
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

        if (!\Hash::check($request->password, $request->user()->password)) {
            return back()->with('error', 'Invalid password.');
        }

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        ActivityLogService::log2FADisabled($request->user());

        return redirect()->route('profile.edit')
            ->with('success', 'Two-factor authentication has been disabled.');
    }

    /**
     * Show recovery codes.
     */
    public function recoveryCodes(Request $request): View
    {
        $codes = $this->twoFactor->getRecoveryCodes($request->user());

        return view('auth.two-factor.recovery-codes', [
            'recoveryCodes' => $codes,
        ]);
    }

    /**
     * Regenerate recovery codes.
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $request->user()->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes)),
        ])->save();

        return redirect()->route('two-factor.recovery-codes')
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

        if (!$userId) {
            return redirect()->route('login')
                ->with('error', 'Session expired. Please login again.');
        }

        $user = \App\Models\User::findOrFail($userId);

        // Try TOTP code
        if ($request->filled('code')) {
            $secret = $this->twoFactor->getDecryptedSecret($user);

            if (!$secret || !$this->twoFactor->verify($secret, $request->code)) {
                return back()->with('error', 'Invalid authentication code.');
            }
        }
        // Try recovery code
        elseif ($request->filled('recovery_code')) {
            $recoveryCodes = $this->twoFactor->getRecoveryCodes($user);

            if (!in_array($request->recovery_code, $recoveryCodes)) {
                return back()->with('error', 'Invalid recovery code.');
            }

            // Remove used recovery code
            $remainingCodes = array_values(array_diff($recoveryCodes, [$request->recovery_code]));
            $user->forceFill([
                'two_factor_recovery_codes' => Crypt::encryptString(json_encode($remainingCodes)),
            ])->save();
        } else {
            return back()->with('error', 'Please enter a code.');
        }

        // Complete login
        session()->forget(['two_factor:user_id', 'two_factor:remember']);
        session(['two_factor_verified' => true]);

        \Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
