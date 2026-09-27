<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Check if 2FA is enabled for the authenticated user
        $user = Auth::user();

        if ($user->two_factor_confirmed_at) {
            // Store user ID in session and log them out temporarily
            $userId = $user->id;
            $remember = $request->boolean('remember');

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Store 2FA challenge data in the new session
            $request->session()->regenerate();
            session(['two_factor:user_id' => $userId, 'two_factor:remember' => $remember]);

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();
        session(['two_factor_verified' => true]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
