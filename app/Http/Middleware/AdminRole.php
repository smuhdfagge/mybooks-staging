<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate admin routes by required ability.
 *
 * Usage in routes:
 *   ->middleware('admin.role:manage-tenants')
 *   ->middleware('admin.role:manage-admin-users')
 */
class AdminRole
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin || ! $admin->hasAbility($ability)) {
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
