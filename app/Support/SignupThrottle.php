<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Limits on creating new businesses (finding S8). The registration wizard
 * is a Livewire component, so route throttles never ran on it and a script
 * could create businesses, and send verification emails, in bulk.
 *
 * Per IP address: at most 3 new businesses an hour, and at most 20 attempts
 * at the final step an hour (so the email check can't be hammered either).
 */
class SignupThrottle
{
    public const MAX_SIGNUPS_PER_HOUR = 3;

    public const MAX_ATTEMPTS_PER_HOUR = 20;

    /**
     * Call before trying to register. Throws a validation error on $field
     * when the limit is reached.
     */
    public static function check(string $ip, string $field = 'email'): void
    {
        $signups = 'signup:'.$ip;
        $attempts = 'signup-attempt:'.$ip;

        if (RateLimiter::tooManyAttempts($signups, self::MAX_SIGNUPS_PER_HOUR)
            || RateLimiter::tooManyAttempts($attempts, self::MAX_ATTEMPTS_PER_HOUR)) {
            $seconds = max(RateLimiter::availableIn($signups), RateLimiter::availableIn($attempts));

            throw ValidationException::withMessages([
                $field => 'Too many sign-ups from your network. Please try again in '.max(1, (int) ceil($seconds / 60)).' minute(s).',
            ]);
        }

        RateLimiter::hit($attempts, 3600);
    }

    /** Call once a business has been created. */
    public static function recordSignup(string $ip): void
    {
        RateLimiter::hit('signup:'.$ip, 3600);
    }
}
