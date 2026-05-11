<?php

declare(strict_types=1);

namespace App\Policies\Billing;

use App\Models\Billing\Customer;
use App\Models\Tenant;
use App\Models\User;

/**
 * Authorization policy for billing write operations.
 *
 * Per PR-E (ADR-016), any user belonging to a Tenant may manage that
 * Tenant's billing. We do NOT distinguish owner vs members here. A
 * future Policy revision may restrict to specific roles via the
 * existing User::$role column without changing controllers/Actions.
 *
 * @see docs/adr/ADR-016-billing-write-contract-and-create-flows.md
 */
final class BillingPolicy
{
    /**
     * Whether $user can create a Customer for $tenant.
     */
    public function createCustomer(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id;
    }

    /**
     * Whether $user can create a Subscription against $customer.
     * Allowed iff the user belongs to the same tenant as the customer.
     */
    public function createSubscription(User $user, Customer $customer): bool
    {
        return $user->tenant_id === $customer->tenant_id;
    }
}
