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

        // Inspect all route parameters for Eloquent models.
        // Use getAttributes() instead of isset() to avoid Eloquent's __isset
        // returning false for nullable foreign keys that are actually set.
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model && array_key_exists('tenant_id', $parameter->getAttributes())) {
                $modelTenantId = $parameter->getAttribute('tenant_id');
                // Null tenant_id means a global/shared record (e.g. system roles) — allow access.
                if ($modelTenantId !== null && (int) $modelTenantId !== (int) $userTenantId) {
                    ActivityLogService::logSuspiciousActivity(
                        'Cross-tenant access attempt blocked',
                        [
                            'target_model' => get_class($parameter),
                            'target_id' => $parameter->getKey(),
                            'target_tenant' => $modelTenantId,
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
