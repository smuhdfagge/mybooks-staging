<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * After a password change or reset, every other sign-in of that user ends
 * (finding S5): other browser sessions, remember-me cookies and API tokens.
 *
 * Other sessions are ended by the AuthenticateSession middleware (web
 * group), which signs out any session holding the old password hash. On
 * top of that, database-stored sessions are deleted straight away and the
 * remember-me token is replaced.
 *
 * The session making the change stays signed in, when it belongs to the
 * same user; the API token making the change is kept too.
 */
class SignOutOtherSessions
{
    public function handle(User $user, ?Request $request = null): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        $currentToken = $request?->user() instanceof User && $request->user()->is($user)
            ? $request->user()->currentAccessToken()
            : null;
        $keepTokenId = is_object($currentToken) && isset($currentToken->id) ? $currentToken->id : null;

        $user->tokens()
            ->when($keepTokenId, fn ($q) => $q->whereKeyNot($keepTokenId))
            ->delete();

        $keepSession = $request && $request->hasSession() && Auth::guard('web')->id() === $user->id;

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->when($keepSession, fn ($q) => $q->where('id', '!=', $request->session()->getId()))
                ->delete();
        }

        if ($keepSession) {
            // Keep this session valid under the new password.
            /** @var SessionGuard $guard */
            $guard = Auth::guard('web');
            $request->session()->put('password_hash_web', $guard->hashPasswordForCookie($user->getAuthPassword()));
        }
    }
}
