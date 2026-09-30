<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\ActivityLog;
use App\Models\User;
use App\Notifications\EmailAddressChangedNotification;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $oldEmail = $user->email;
        $user->fill($request->safe()->only(['name', 'email']));
        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            // Tell the old address, in case this wasn't the owner (S6).
            Notification::route('mail', $oldEmail)
                ->notify(new EmailAddressChangedNotification($user->name, $oldEmail, $user->email));
            if ($user->tenant_id) {
                ActivityLogService::log(ActivityLog::ACTION_UPDATED, "Email changed from {$oldEmail} to {$user->email}", User::class, $user->id, $user->name);
            }
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // The last admin can't leave the business with nobody to run it (O7).
        if ($user->isLastActiveAdmin()) {
            return Redirect::route('profile.edit')->withErrors([
                'password' => 'You are the only admin of this business. Make another user an admin first, or close the organisation from Settings.',
            ], 'userDeletion');
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
