<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Services\ActivityLogService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseApiController
{
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

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            return $this->error('Your account has been deactivated. Please contact support.', 403);
        }

        if (!$user->hasVerifiedEmail()) {
            return $this->error('Please verify your email address before logging in.', 403);
        }

        // Check if tenant is active
        if ($user->tenant && !$user->tenant->is_active) {
            return $this->error('Your organization account has been deactivated. Please contact support.', 403);
        }

        // Check if 2FA is enabled
        if ($user->two_factor_confirmed_at) {
            // If 2FA code is not provided, return a challenge response
            if (!$request->filled('two_factor_code')) {
                return $this->success([
                    'two_factor_required' => true,
                    'message' => 'Two-factor authentication code required.',
                ], 'Two-factor authentication required', 200);
            }

            // Verify 2FA code
            $twoFactor = app(\App\Services\TwoFactorService::class);
            $secret = $twoFactor->getDecryptedSecret($user);

            if (!$secret || !$twoFactor->verify($secret, $request->two_factor_code)) {
                return $this->error('Invalid two-factor authentication code.', 422);
            }
        }

        // Revoke old tokens for this device
        $user->tokens()->where('name', $request->device_name)->delete();

        // Create new token with scoped abilities
        $abilities = $this->getTokenAbilities($user);
        $token = $user->createToken($request->device_name, $abilities)->plainTextToken;

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

        // Create new token with scoped abilities
        $abilities = $this->getTokenAbilities($user);
        $token = $user->createToken($deviceName, $abilities)->plainTextToken;

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

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->validationError([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

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

                // Revoke all existing tokens for security
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(null, 'Password has been reset successfully');
        }

        return $this->error(__($status), 400);
    }

    /**
     * Get token abilities based on user permissions.
     */
    protected function getTokenAbilities(User $user): array
    {
        $abilities = ['*:read']; // All authenticated users can read their own profile

        $permissionMap = [
            'view dashboard' => 'dashboard:read',
            'view customers' => 'customers:read',
            'create customers' => 'customers:write',
            'edit customers' => 'customers:write',
            'delete customers' => 'customers:delete',
            'view vendors' => 'vendors:read',
            'create vendors' => 'vendors:write',
            'edit vendors' => 'vendors:write',
            'delete vendors' => 'vendors:delete',
            'view items' => 'items:read',
            'create items' => 'items:write',
            'edit items' => 'items:write',
            'delete items' => 'items:delete',
            'view invoices' => 'invoices:read',
            'create invoices' => 'invoices:write',
            'edit invoices' => 'invoices:write',
            'delete invoices' => 'invoices:delete',
            'send invoices' => 'invoices:send',
            'view bills' => 'bills:read',
            'create bills' => 'bills:write',
            'edit bills' => 'bills:write',
            'delete bills' => 'bills:delete',
            'view expenses' => 'expenses:read',
            'create expenses' => 'expenses:write',
            'edit expenses' => 'expenses:write',
            'delete expenses' => 'expenses:delete',
            'view payments-received' => 'payments-received:read',
            'create payments-received' => 'payments-received:write',
            'delete payments-received' => 'payments-received:delete',
            'view payments-made' => 'payments-made:read',
            'create payments-made' => 'payments-made:write',
            'delete payments-made' => 'payments-made:delete',
            'view chart-of-accounts' => 'accounts:read',
            'create chart-of-accounts' => 'accounts:write',
            'edit chart-of-accounts' => 'accounts:write',
            'delete chart-of-accounts' => 'accounts:delete',
            'view journals' => 'journals:read',
            'create journals' => 'journals:write',
            'edit journals' => 'journals:write',
            'delete journals' => 'journals:delete',
            'view banks' => 'banks:read',
            'create banks' => 'banks:write',
            'edit banks' => 'banks:write',
            'delete banks' => 'banks:delete',
            'view inventory' => 'inventory:read',
            'adjust inventory' => 'inventory:write',
            'view employees' => 'employees:read',
            'create employees' => 'employees:write',
            'edit employees' => 'employees:write',
            'delete employees' => 'employees:delete',
            'view sales-orders' => 'sales-orders:read',
            'create sales-orders' => 'sales-orders:write',
            'edit sales-orders' => 'sales-orders:write',
            'delete sales-orders' => 'sales-orders:delete',
            'view reports' => 'reports:read',
            'export reports' => 'reports:export',
            'view settings' => 'settings:read',
            'edit settings' => 'settings:write',
        ];

        // Super admins get all abilities
        if ($user->isSuperAdmin()) {
            return ['*'];
        }

        foreach ($permissionMap as $permission => $ability) {
            if ($user->can($permission)) {
                $abilities[] = $ability;
            }
        }

        return array_unique($abilities);
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
        if (!$user || !Password::tokenExists($user, $request->token)) {
            return $this->error('Invalid or expired reset token', 400);
        }

        return $this->success(['valid' => true], 'Token is valid');
    }
}
