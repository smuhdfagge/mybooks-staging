<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SignOutOtherSessions;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Other devices and API tokens are signed out (S5).
        app(SignOutOtherSessions::class)->handle($request->user(), $request);

        ActivityLogService::logPasswordChanged($request->user());

        return back()->with('status', 'password-updated');
    }
}
