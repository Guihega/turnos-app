<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Actions\Billing\CreateCustomerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreCustomerRequest;
use App\Http\Resources\Billing\CustomerResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/billing/customers — create the Customer record for the
 * authenticated user's tenant.
 *
 * Tenant is implicit: derived from the authenticated user. The
 * 1:1 invariant Customer↔Tenant is enforced at the DB level by the
 * partial unique index on billing_customers.tenant_id (PR-A).
 *
 * Errors map to:
 *   - 401 Unauthorized — no Sanctum session
 *   - 403 Forbidden — user has no tenant
 *   - 422 Unprocessable — validation failed or gateway rejected payload
 *   - 5xx — gateway down (translated by exception handlers)
 */
final class CustomersController extends Controller
{
    public function store(StoreCustomerRequest $request, CreateCustomerAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Tenant $tenant */
        $tenant = $user->tenant;
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $customer = $action->execute($tenant, $validated);

        return CustomerResource::make($customer)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
