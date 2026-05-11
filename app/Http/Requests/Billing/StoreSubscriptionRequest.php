<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\Billing\BillingInterval;
use App\Models\Billing\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\Billing\BillingPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Input validation + authorization for POST /api/v1/billing/subscriptions.
 *
 * The endpoint creates a Subscription for the authenticated user's
 * tenant's Customer. The customer is implicit (1:1 with tenant), so
 * the request body does NOT carry customer_id.
 *
 * Per ADR-016 PR-E, the catalog is selected via plan_id + interval.
 * The Price (and thus currency) is derived from customer.default_currency.
 */
final class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        /** @var Tenant|null $tenant */
        $tenant = $user->tenant;
        if ($tenant === null) {
            return false;
        }

        /** @var Customer|null $customer */
        $customer = Customer::query()
            ->where('tenant_id', $tenant->id)
            ->first();
        if ($customer === null) {
            return false;
        }

        return (new BillingPolicy)->createSubscription($user, $customer);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'ulid', 'exists:billing_plans,id'],
            'interval' => ['required', Rule::enum(BillingInterval::class)],
        ];
    }
}
