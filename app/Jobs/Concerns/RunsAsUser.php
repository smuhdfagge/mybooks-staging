<?php

namespace App\Jobs\Concerns;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Runs a queued job as the user who started it, so tenant scopes,
 * "created by" and the activity log behave as they did in the web
 * request (P3), limited to that user's business. Whoever was signed in
 * before is put back afterwards. A job whose user is gone is not run.
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
        $user = $userId ? User::find($userId) : null;
        if (! $user || ! $user->tenant_id) {
            // Running on regardless would do the work with nobody's
            // permissions and no business to keep it to (audit MB-26).
            throw new RuntimeException($userId
                ? "The user (#{$userId}) who started this job no longer exists or has no business, so it was not run."
                : 'This job was queued without the user who started it, so it was not run.');
        }

        $previous = Auth::user();
        Auth::setUser($user);

        try {
            return Tenant::run($user->tenant_id, $callback);
        } finally {
            if ($previous) {
                Auth::setUser($previous);
            } else {
                Auth::forgetUser();
            }
        }
    }
}
