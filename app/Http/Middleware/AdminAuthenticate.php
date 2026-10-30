<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthenticate
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        // Check if admin is active
        if (! Auth::guard('admin')->user()->is_active) {
            Auth::guard('admin')->logout();

            return redirect()->route('admin.login')
                ->with('error', 'Your account has been deactivated.');
        }

        // The platform admin works across every business, on purpose
        // (audit MB-26: tenant-scoped queries need an explicit context).
        return Tenant::acrossAll(fn () => $next($request));
    }
}
