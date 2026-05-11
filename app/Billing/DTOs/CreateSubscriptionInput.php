<?php

declare(strict_types=1);

namespace App\Billing\DTOs;

/**
 * Input DTO for BillingGatewayWriter::createSubscription().
 *
 * Gateway-agnostic shape. Adapters map to their SDK's native
 * subscription-creation payload.
 *
 * Per ADR-016:
 *
 * - $gatewayCustomerId is the gateway's customer ID (e.g. 'cus_XXX'
 *   for Stripe), already resolved by the Action via
 *   billing_customer_gateway_refs.
 *
 * - $gatewayPriceId is the gateway's price ID (e.g. 'price_XXX' for
 *   Stripe), already resolved by the Action from the requested plan_id.
 *
 * - $trialDays is read from config('billing.subscriptions.trial_days')
 *   by the Action; 0 disables trial. Adapters MUST translate this
 *   into the gateway's trial mechanism (trial_period_days for Stripe).
 *
 * - The adapter MUST request a subscription that does NOT require a
 *   payment method upfront (Stripe: payment_behavior=default_incomplete).
 *   See ADR-016 §4.
 */
final readonly class CreateSubscriptionInput
{
    /**
     * @param  string  $gatewayCustomerId  e.g. 'cus_XXX' for Stripe.
     * @param  string  $gatewayPriceId  e.g. 'price_XXX' for Stripe.
     * @param  int  $trialDays  0 to disable trial.
     * @param  array<string, mixed>  $metadata  Forwarded to the gateway. Must include tenant_id, app_customer_id, app_subscription_id (see ADR-016).
     */
    public function __construct(
        public string $gatewayCustomerId,
        public string $gatewayPriceId,
        public int $trialDays,
        public array $metadata,
    ) {}
}
