<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  string  ...$allowedPlans  Comma-separated list of allowed plan slugs
     */
    public function handle(Request $request, Closure $next, string ...$allowedPlans): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $tenant = $user->tenant;

        if (! $tenant) {
            return $this->denyAccess($request, 'No organization associated with your account.');
        }

        $subscription = $tenant->activeSubscription;

        if (! $subscription || ! $subscription->plan) {
            return $this->denyAccess($request, 'You need an active subscription to access this feature.');
        }

        $currentPlanSlug = $subscription->plan->slug;

        // Check if the current plan is in the allowed plans list
        if (! in_array($currentPlanSlug, $allowedPlans)) {
            $allowedPlanNames = array_map(function ($slug) {
                return ucfirst($slug);
            }, $allowedPlans);

            $planList = implode(' or ', $allowedPlanNames);

            return $this->denyAccess(
                $request,
                "This feature is only available on {$planList} plans. Please upgrade your subscription to access budgeting features."
            );
        }

        return $next($request);
    }

    /**
     * Deny access with an appropriate response
     */
    protected function denyAccess(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], 403);
        }

        return redirect()
            ->route('settings.subscription')
            ->with('error', $message);
    }
}
