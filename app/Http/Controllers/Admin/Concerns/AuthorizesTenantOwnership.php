<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Provides tenant ownership validation for Admin controllers.
 *
 * Ensures that route-model-bound entities belong to the authenticated
 * user's tenant before allowing any CRUD operation. This is a defense-in-depth
 * layer on top of EnsureTenantScope's global scopes.
 *
 * Usage:
 *   $this->authorizeTenantOwnership($branch, $request);
 *   $this->authorizeBranchBelongsToTenant($branchId, $request);
 */
trait AuthorizesTenantOwnership
{
    /**
     * Verify a model with tenant_id belongs to the user's tenant.
     * Works for: Branch, Service, DisplayAnnouncement, and any model with tenant_id.
     */
    protected function authorizeTenantOwnership(Model $model, Request $request): void
    {
        if (! isset($model->tenant_id)) {
            abort(500, 'Model does not have tenant_id attribute.');
        }

        if ($model->tenant_id !== $request->user()->tenant_id) {
            abort(403, 'No tiene acceso a este recurso.');
        }
    }

    /**
     * Verify a branch_id belongs to the user's tenant.
     * Used when validating foreign keys in store/update requests.
     */
    protected function authorizeBranchBelongsToTenant(string $branchId, Request $request): Branch
    {
        $branch = Branch::withoutGlobalScopes()
            ->where('id', $branchId)
            ->where('tenant_id', $request->user()->tenant_id)
            ->first();

        if (! $branch) {
            abort(403, 'La sucursal no pertenece a su organización.');
        }

        return $branch;
    }

    /**
     * Verify a model accessed via branch relationship belongs to the user's tenant.
     * Works for: Queue, Counter, and any model linked to a branch.
     */
    protected function authorizeBranchChild(Model $model, Request $request): void
    {
        $model->loadMissing('branch');

        if (! $model->branch || $model->branch->tenant_id !== $request->user()->tenant_id) {
            abort(403, 'No tiene acceso a este recurso.');
        }
    }
}
