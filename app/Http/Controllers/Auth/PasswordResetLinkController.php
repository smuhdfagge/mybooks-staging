<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // The email itself is queued (ResetPasswordNotification), so this
        // only puts it on the queue. Should it still be sent here — a queue
        // set to 'sync', say — a mail server that will not take it must not
        // become a 500 page for someone who has merely forgotten their
        // password. Logged, so it is not lost, and the same answer either
        // way: telling one person the mail failed and another that it was
        // sent would also say which addresses have an account.
        try {
            Password::sendResetLink(
                $request->only('email')
            );
        } catch (TransportExceptionInterface $e) {
            Log::error('Could not send a password reset email.', ['exception' => $e]);
        }

        // Always return the same response regardless of whether the email exists
        return back()->with('status', __('passwords.sent'));
    }
}
