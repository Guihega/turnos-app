<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\Billing\Subscription;
use App\Models\Tenant;

/**
 * Onboards a Tenant onto the free pilot plan: creates its billing Customer
 * and a pilot Subscription, locally, without any payment gateway.
 *
 * This is the single composed entry point reused by both Fase B drivers:
 *   - the billing:backfill-existing-tenants command (existing tenants), and
 *   - the onboarding hook (tenants created via OnboardingController).
 *
 * It is a thin composition of two idempotent, gateway-free actions:
 *   1. CreatePilotCustomerAction  — local Customer (1:1 tenant).
 *   2. CreatePilotSubscriptionAction — pilot Subscription; on creation it
 *      dispatches SubscriptionCreated, so the PR-S listener materializes the
 *      pilot plan's entitlements.
 *
 * No outer transaction wraps the two: each action transacts internally and
 * is idempotent, so a partial result (customer created, subscription not) is
 * recoverable by re-running — the customer is found and reused, the
 * subscription completed. Wrapping both in one transaction would also fight
 * the post-commit dispatch of SubscriptionCreated (ADR-016): the listener
 * would materialize against rows an outer rollback could still undo. The
 * one_active_subscription_per_customer invariant guards consistency at the
 * engine level regardless.
 */
final class OnboardPilotAction
{
    public function __construct(
        private readonly CreatePilotCustomerAction $createCustomer,
        private readonly CreatePilotSubscriptionAction $createSubscription,
    ) {}

    public function execute(Tenant $tenant): Subscription
    {
        $customer = $this->createCustomer->execute($tenant);

        return $this->createSubscription->execute($customer);
    }
}
