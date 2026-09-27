<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense-in-depth: Verify that any route-bound Eloquent model
 * with a tenant_id column belongs to the authenticated user's tenant.
 *
 * This guards against bypassed global scopes or misconfigured queries.
 */
class VerifyTenantOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip for unauthenticated requests or super admins
        if (!$user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $userTenantId = $user->tenant_id;

        if (!$userTenantId) {
            return $next($request);
        }

        // Inspect all route parameters for Eloquent models
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model && isset($parameter->tenant_id)) {
                if ((int) $parameter->tenant_id !== (int) $userTenantId) {
                    ActivityLogService::logSuspiciousActivity(
                        'Cross-tenant access attempt blocked',
                        [
                            'target_model' => get_class($parameter),
                            'target_id' => $parameter->getKey(),
                            'target_tenant' => $parameter->tenant_id,
                            'user_tenant' => $userTenantId,
                            'url' => $request->fullUrl(),
                        ]
                    );
                    abort(403, 'Access denied.');
                }
            }
        }

        return $next($request);
    }
}
