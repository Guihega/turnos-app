<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantScope
{
    /**
     * Automatically scope all queries to the authenticated user's tenant.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            if ($user?->isSuperAdmin()) {
                return $next($request);
            }
            abort(403, 'No tiene un tenant asignado.');
        }

        // Store tenant context for global use
        app()->instance('current_tenant_id', $user->tenant_id);

        return $next($request);
    }
}
