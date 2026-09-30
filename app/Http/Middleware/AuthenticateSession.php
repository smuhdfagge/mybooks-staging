<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Session\Middleware\AuthenticateSession as BaseAuthenticateSession;

/**
 * Laravel's AuthenticateSession: a session holding an old password hash is
 * signed out, so changing or resetting a password ends every other sign-in
 * (finding S5).
 *
 * It only works with a session guard. When the current guard is a token
 * guard (Sanctum), there is nothing to check and the request goes on.
 */
class AuthenticateSession extends BaseAuthenticateSession
{
    public function handle($request, Closure $next)
    {
        if (! $this->auth->guard() instanceof SessionGuard) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
