<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\GeneratesIdempotencyKeys;
use App\Billing\Contracts\BillingGatewayWriter;
use App\Billing\DTOs\CreateSubscriptionInput;
use App\Enums\Billing\BillingInterval;
use App\Enums\Billing\Gateway;
use App\Enums\Billing\SubscriptionStatus;
use App\Events\Billing\SubscriptionCreated;
use App\Exceptions\Billing\CustomerNotRegisteredInGatewayException;
use App\Exceptions\Billing\PriceMissingGatewayMappingException;
use App\Models\Billing\Customer;
use App\Models\Billing\CustomerGatewayRef;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionStateTransition;
use App\Services\Billing\PriceResolver;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Subscription in both the gateway and the local DB.
 *
 * Per ADR-016, this is a saga:
 *
 *   Step 1. Resolve the Price for (plan, customer.currency, interval).
 *   Step 2. Resolve gateway IDs:
 *           - customer's Stripe ID from CustomerGatewayRef
 *           - price's Stripe ID from Price::gatewayId('stripe')
 *   Step 3. Lookup or mint an idempotency key for this request.
 *   Step 4. Short-circuit on cached response.
 *   Step 5. Call the gateway. Failures propagate; nothing in DB.
 *   Step 6. In a single DB::transaction:
 *             - INSERT Subscription with status='trialing',
 *               trial_ends_at=now()+14d
 *             - INSERT initial state transition row
 *               (from_status=to_status=trialing, reason='created')
 *             - snapshot the gateway response
 *   Step 7. Dispatch SubscriptionCreated post-commit.
 *
 * Why the initial state row is NOT created via
 * TransitionSubscriptionAction: that action models real transitions
 * (from one status to another). The first row is a birth event, not
 * a transition. We insert it manually with from_status = to_status
 * and reason='created' as the convention. Analytics can filter on
 * reason to separate births from transitions.
 *
 * @see docs/adr/ADR-016-billing-write-contract-and-create-flows.md
 */
final class CreateSubscriptionAction
{
    use GeneratesIdempotencyKeys;

    public function __construct(
        private readonly BillingGatewayWriter $writer,
        private readonly PriceResolver $priceResolver,
    ) {}

    /**
     * @throws CustomerNotRegisteredInGatewayException
     * @throws PriceMissingGatewayMappingException
     */
    public function execute(Customer $customer, Plan $plan, BillingInterval $interval): Subscription
    {
        $gateway = Gateway::Stripe;
        $trialDays = (int) config('billing.subscriptions.trial_days', 14);

        // Step 1: Resolve the Price. Throws PriceNotFoundException
        // if there's no active Price matching the combination.
        $price = $this->priceResolver->resolve($plan, $customer, $interval);

        // Step 2: Resolve gateway-specific IDs.
        $gatewayRef = CustomerGatewayRef::query()
            ->where('customer_id', $customer->id)
            ->where('gateway', $gateway->value)
            ->first();
        if ($gatewayRef === null) {
            throw new CustomerNotRegisteredInGatewayException(sprintf(
                'Customer %s has no %s gateway ref. Run CreateCustomerAction first.',
                $customer->id,
                $gateway->value,
            ));
        }

        $gatewayPriceId = $price->gatewayId($gateway->value);
        if ($gatewayPriceId === null) {
            throw new PriceMissingGatewayMappingException(sprintf(
                'Price %s has no gateway_refs mapping for %s.',
                $price->id,
                $gateway->value,
            ));
        }

        // Step 3: Idempotency key.
        $hashablePayload = [
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'price_id' => $price->id,
            'interval' => $interval->value,
            'trial_days' => $trialDays,
        ];
        $key = $this->findOrCreateIdempotencyKey(
            operation: 'create_subscription',
            gateway: $gateway,
            payload: $hashablePayload,
            customerId: $customer->id,
        );

        // Step 4: Short-circuit on cached response.
        if ($key->response_snapshot !== null) {
            $snapshot = $key->response_snapshot;
            $subId = is_array($snapshot) && isset($snapshot['subscription_id'])
                ? (string) $snapshot['subscription_id']
                : null;
            if ($subId !== null) {
                /** @var Subscription $existing */
                $existing = Subscription::findOrFail($subId);

                return $existing;
            }
        }

        // Step 5: Call the gateway.
        $input = new CreateSubscriptionInput(
            gatewayCustomerId: $gatewayRef->gateway_customer_id,
            gatewayPriceId: $gatewayPriceId,
            trialDays: $trialDays,
            metadata: [
                'tenant_id' => $customer->tenant_id,
                'app_customer_id' => $customer->id,
            ],
        );
        $gatewaySub = $this->writer->createSubscription($input, $key->idempotency_key);

        // Step 6: Persist locally.
        $subscription = DB::transaction(function () use (
            $customer,
            $plan,
            $price,
            $gatewaySub,
            $key,
            $trialDays,
        ): Subscription {
            $trialEndsAt = Carbon::now()->addDays($trialDays);

            /** @var Subscription $subscription */
            $subscription = Subscription::create([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'price_id' => $price->id,
                'status' => SubscriptionStatus::Trialing->value,
                'stripe_subscription_id' => $gatewaySub->gatewayId,
                'trial_ends_at' => $trialEndsAt,
                'current_period_start' => $gatewaySub->currentPeriodStart,
                'current_period_end' => $gatewaySub->currentPeriodEnd,
                'metadata' => [
                    'created_via' => 'CreateSubscriptionAction',
                ],
            ]);

            // Initial state row: birth, not transition. See class docblock.
            SubscriptionStateTransition::create([
                'subscription_id' => $subscription->id,
                'from_status' => SubscriptionStatus::Trialing->value,
                'to_status' => SubscriptionStatus::Trialing->value,
                'reason' => 'created',
                'context' => [
                    'plan_code' => $plan->code,
                    'trial_days' => $trialDays,
                ],
                'transitioned_at' => new DateTimeImmutable,
            ]);

            $this->snapshotResponse($key, [
                'subscription_id' => $subscription->id,
                'gateway_subscription_id' => $gatewaySub->gatewayId,
            ]);

            return $subscription;
        });

        // Step 7: Dispatch post-commit.
        DB::afterCommit(fn () => event(new SubscriptionCreated($subscription)));

        return $subscription;
    }
}
