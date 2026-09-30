<?php

namespace App\Jobs\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Runs a queued job as the user who started it, so tenant scopes,
 * "created by" and the activity log behave as they did in the web
 * request (P3). Whoever was signed in before is put back afterwards.
 */
trait RunsAsUser
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function runAsUser(?int $userId, callable $callback): mixed
    {
        $previous = Auth::user();
        $user = $userId ? User::find($userId) : null;

        if ($user) {
            Auth::setUser($user);
        }

        try {
            return $callback();
        } finally {
            if ($previous) {
                Auth::setUser($previous);
            } elseif ($user) {
                Auth::forgetUser();
            }
        }
    }
}
