<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Service;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantScope
{
    /**
     * Automatically scope all queries to the authenticated user's tenant.
     *
     * This middleware:
     * 1. Stores tenant_id in the container for global access
     * 2. Applies global query scopes to tenant-owned models
     *    so ALL queries are automatically filtered by tenant
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->tenant_id) {
            if ($user?->isSuperAdmin()) {
                return $next($request);
            }
            abort(403, 'No tiene un tenant asignado.');
        }

        $tenantId = $user->tenant_id;

        // Store tenant context for global use
        app()->instance('current_tenant_id', $tenantId);

        // Apply global scopes to prevent cross-tenant data leaks
        // These scopes ensure that even if a developer forgets to filter
        // by tenant_id, the query will still be scoped correctly.
        Branch::addGlobalScope('tenant', function (Builder $query) use ($tenantId) {
            $query->where('branches.tenant_id', $tenantId);
        });

        Service::addGlobalScope('tenant', function (Builder $query) use ($tenantId) {
            $query->where('services.tenant_id', $tenantId);
        });

        User::addGlobalScope('tenant', function (Builder $query) use ($tenantId) {
            // Allow null tenant_id for super_admin users
            $query->where(function ($q) use ($tenantId) {
                $q->where('users.tenant_id', $tenantId)
                    ->orWhereNull('users.tenant_id');
            });
        });

        return $next($request);
    }
}
