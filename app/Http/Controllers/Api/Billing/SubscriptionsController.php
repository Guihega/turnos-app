<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Actions\Billing\CreateSubscriptionAction;
use App\Enums\Billing\BillingInterval;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreSubscriptionRequest;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Models\Billing\Customer;
use App\Models\Billing\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/billing/subscriptions — create a Subscription for the
 * authenticated user's tenant's Customer.
 *
 * Customer is implicit (1:1 with tenant). The endpoint receives
 * plan_id + interval; the backend resolves the concrete Price using
 * the customer's default_currency (see PriceResolver).
 *
 * Errors map to:
 *   - 401, 403 — auth / tenant ownership
 *   - 422 — validation, no price for combination, payload rejected
 *   - 409 — customer not yet registered in gateway (run /customers first)
 *   - 5xx — gateway down
 */
final class SubscriptionsController extends Controller
{
    public function store(StoreSubscriptionRequest $request, CreateSubscriptionAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Tenant $tenant */
        $tenant = $user->tenant;
        /** @var array{plan_id: string, interval: string} $validated */
        $validated = $request->validated();

        /** @var Customer $customer */
        $customer = Customer::query()
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        /** @var Plan $plan */
        $plan = Plan::query()->findOrFail($validated['plan_id']);
        $interval = BillingInterval::from($validated['interval']);

        $subscription = $action->execute($customer, $plan, $interval);

        return SubscriptionResource::make($subscription)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
