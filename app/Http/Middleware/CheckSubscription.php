<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Routes that should be accessible without an active subscription
     */
    protected array $except = [
        'profile.edit',
        'profile.update',
        'profile.destroy',
        'settings.subscription',
        'logout',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for guests
        if (! $request->user()) {
            return $next($request);
        }

        // Skip for excepted routes
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $tenant = $request->user()->tenant;

        // Check if tenant exists
        if (! $tenant) {
            return $this->redirectWithError($request, 'No organization associated with your account.');
        }

        // Check if tenant has an active subscription (not trial, not cancelled, not expired)
        if (! $tenant->hasActiveSubscription()) {
            return $this->redirectWithError(
                $request,
                'You need an active subscription to access this feature. Please subscribe to a plan to continue.'
            );
        }

        return $next($request);
    }

    /**
     * Check if the route should be skipped
     */
    protected function shouldSkip(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if (! $routeName) {
            return false;
        }

        foreach ($this->except as $except) {
            if ($routeName === $except || str_starts_with($routeName, $except.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redirect with an error message
     */
    protected function redirectWithError(Request $request, string $message): Response
    {
        // Livewire updates ask for JSON but act on redirects, so send them
        // to the subscription page like a normal page load.
        if ($request->expectsJson() && ! $request->hasHeader('X-Livewire')) {
            return response()->json([
                'message' => $message,
                'subscription_required' => true,
            ], 403);
        }

        return redirect()
            ->route('settings.subscription')
            ->with('error', $message);
    }
}
