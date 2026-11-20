<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\SignOutOtherSessions;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\TwoFactorService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseApiController
{
    /** Wrong passwords allowed per account before a 15-minute lockout (audit MB-27). */
    public const MAX_ATTEMPTS = 5;

    public const LOCKOUT_SECONDS = 900;

    /**
     * Login user and create token
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'required|string',
        ]);

        // Per-account lockout on top of the per-IP route throttle (audit
        // MB-27): guessing one account's password from many addresses is
        // stopped too, as on the web and admin sign-ins.
        $lockKey = 'api-login:'.Str::lower(trim((string) $request->email));
        if (RateLimiter::tooManyAttempts($lockKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($lockKey);
            $minutes = (int) ceil($seconds / 60);

            return $this->error("Too many failed sign-in attempts for this account. Please try again in {$minutes} minute(s).", 429)
                ->header('Retry-After', (string) $seconds);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            if (RateLimiter::hit($lockKey, self::LOCKOUT_SECONDS) >= self::MAX_ATTEMPTS) {
                ActivityLogService::logAccountLocked((string) $request->email, 'Too many failed API sign-in attempts');
            } else {
                ActivityLogService::logFailedLogin((string) $request->email);
            }

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        RateLimiter::clear($lockKey);

        if (! $user->is_active) {
            return $this->error('Your account has been deactivated. Please contact support.', 403);
        }

        if (! $user->hasVerifiedEmail()) {
            return $this->error('Please verify your email address before logging in.', 403);
        }

        // Check if tenant is active
        if ($user->tenant && ! $user->tenant->is_active) {
            return $this->error('Your organization account has been deactivated. Please contact support.', 403);
        }

        // Check if 2FA is enabled
        if ($user->two_factor_confirmed_at) {
            // If 2FA code is not provided, return a challenge response
            if (! $request->filled('two_factor_code')) {
                return $this->success([
                    'two_factor_required' => true,
                    'message' => 'Two-factor authentication code required.',
                ], 'Two-factor authentication required', 200);
            }

            // Verify 2FA code: per-account limit, and no reusing a code (S7)
            $twoFactor = app(TwoFactorService::class);

            if ($twoFactor->tooManyAttempts($user)) {
                return $this->error('Too many two-factor attempts. Please try again later.', 429);
            }

            if (! $twoFactor->verifyForUser($user, (string) $request->two_factor_code)) {
                $twoFactor->recordFailedAttempt($user);

                return $this->error('Invalid two-factor authentication code.', 422);
            }

            $twoFactor->clearAttempts($user);
        }

        // Revoke old tokens for this device
        $user->tokens()->where('name', $request->device_name)->delete();

        // No ability list on the token (audit MB-47): nothing checked it,
        // and a copy of the permissions taken at sign-in goes stale when a
        // role changes. What the token may do is the user's permissions,
        // checked live by the permission middleware on every API route.
        $token = $user->createToken($request->device_name)->plainTextToken;

        ActivityLogService::logApiTokenCreated($user, $request->device_name);

        // Load relationships
        $user->load(['tenant', 'roles', 'permissions']);

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful');
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke current token
        $tokenName = $request->user()->currentAccessToken()->name;
        $request->user()->currentAccessToken()->delete();

        ActivityLogService::logApiTokenRevoked($request->user(), $tokenName);

        return $this->success(null, 'Logged out successfully');
    }

    /**
     * Logout from all devices (revoke all tokens)
     */
    public function logoutAll(Request $request): JsonResponse
    {
        // Revoke all tokens
        $request->user()->tokens()->delete();

        ActivityLogService::logApiTokenRevoked($request->user(), 'all devices');

        return $this->success(null, 'Logged out from all devices successfully');
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['tenant', 'roles', 'permissions']);

        return $this->success(new UserResource($user));
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $deviceName = $request->user()->currentAccessToken()->name;

        // Delete current token
        $request->user()->currentAccessToken()->delete();

        // Permissions are checked live, not carried on the token (see login()).
        $token = $user->createToken($deviceName)->plainTextToken;

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Token refreshed successfully');
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $user->update($validated);
        $user->load(['tenant', 'roles', 'permissions']);

        return $this->success(new UserResource($user), 'Profile updated successfully');
    }

    /**
     * Update password
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return $this->validationError([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Other sessions and tokens end; the token making the change is kept (S5).
        app(SignOutOtherSessions::class)->handle($user, $request);

        ActivityLogService::logPasswordChanged($user);

        return $this->success(null, 'Password updated successfully');
    }

    /**
     * Send password reset link
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Send reset link (ignore status to prevent user enumeration)
        Password::sendResetLink(
            $request->only('email')
        );

        // Always return the same response regardless of whether the email exists
        return $this->success(null, 'If an account exists with that email, a password reset link has been sent');
    }

    /**
     * Reset password with token
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke all existing tokens and sessions (S5)
                app(SignOutOtherSessions::class)->handle($user);

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(null, 'Password has been reset successfully');
        }

        return $this->error(__($status), 400);
    }

    /**
     * Verify reset token is valid
     */
    public function verifyResetToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Use the same error message whether user doesn't exist or token is invalid
        if (! $user || ! Password::tokenExists($user, $request->token)) {
            return $this->error('Invalid or expired reset token', 400);
        }

        return $this->success(['valid' => true], 'Token is valid');
    }
}
