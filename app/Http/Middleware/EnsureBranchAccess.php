<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchAccess
{
    /**
     * Verify the user has access to the requested branch.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $branchId = $request->route('branchId') ?? $request->route('branch');
        $user = $request->user();

        if (!$branchId || !$user) {
            return $next($request);
        }

        if ($user->isSuperAdmin() || $user->isTenantAdmin()) {
            // Verify branch belongs to tenant
            $branchTenantId = \App\Models\Branch::where('id', $branchId)->value('tenant_id');
            if ($branchTenantId && !$user->isSuperAdmin() && $branchTenantId !== $user->tenant_id) {
                abort(403, 'No tiene acceso a esta sucursal.');
            }
            return $next($request);
        }

        if (!$user->belongsToBranch($branchId)) {
            abort(403, 'No tiene acceso a esta sucursal.');
        }

        return $next($request);
    }
}
