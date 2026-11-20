<?php

namespace App\Actions\Auth;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A user deleting their own account, from the profile page or the mobile
 * app (DELETE /api/v1/auth/account). The App Store and Google Play require
 * an app that has sign-up to let people delete their account from it.
 *
 * The business's books stay: they belong to the business, not the user.
 * What goes is the user, every API token (so every phone is signed out)
 * and their phones for push notifications. The activity log keeps a line
 * with their name.
 */
class DeleteUserAccount
{
    public const LAST_ADMIN = 'You are the only admin of this business. Make another user an admin first, or close the organisation from Settings.';

    /**
     * @throws ValidationException when the user is the business's last
     *                             active admin (O7), on `password`
     */
    public function handle(User $user): void
    {
        // The last admin can't leave the business with nobody to run it (O7).
        if ($user->isLastActiveAdmin()) {
            throw ValidationException::withMessages(['password' => self::LAST_ADMIN]);
        }

        DB::transaction(function () use ($user) {
            ActivityLogService::log(
                ActivityLog::ACTION_DELETED,
                'Deleted their own account',
                User::class,
                $user->id,
                $user->name,
            );
            $user->tokens()->delete();
            $user->devices()->delete();
            $user->delete();
        });
    }
}
