<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\SubscriptionStatus;
use App\Events\Billing\SubscriptionCreated;
use App\Models\Billing\Customer;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionStateTransition;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates a free pilot Subscription for a Customer, locally, without touching
 * any payment gateway.
 *
 * The pilot is the free 90-day onboarding plan (SPEC §7): no payment method
 * required, status='pilot' (SubscriptionStatus::Pilot grants access). This is
 * deliberately NOT CreateSubscriptionAction, which is the paid path — a Stripe
 * saga that resolves a Price, calls the gateway, and persists a 'trialing'
 * subscription with a stripe_subscription_id. A pilot has no price_id, no
 * gateway id, and its own status; forcing it through that saga would couple a
 * free plan to Stripe and misrepresent the state machine.
 *
 * On creation it dispatches SubscriptionCreated, so the PR-S listener
 * materializes the pilot plan's entitlements automatically; this action does
 * not write entitlements itself.
 *
 * Idempotent against the engine-level invariant
 * (one_active_subscription_per_customer): if the customer already has an
 * access-granting subscription, that one is returned instead of inserting a
 * second and violating the unique partial index. This makes the backfill
 * (PR-S3) safe to re-run.
 *
 * @see docs/billing/SPEC.md §7 (alta, pilot onboarding)
 * @see App\Actions\Billing\CreateSubscriptionAction (paid path, with gateway)
 */
final class CreatePilotSubscriptionAction
{
    private const PILOT_PLAN_CODE = 'pilot';

    private const TRIAL_DAYS = 90;

    public function execute(Customer $customer): Subscription
    {
        // Respect the one-active-subscription-per-customer invariant: if the
        // customer already holds a subscription in an active-slot status
        // (see SubscriptionStatus::activeSlotValues), return it rather than
        // inserting a second and violating the partial unique index.
        $existing = Subscription::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', SubscriptionStatus::activeSlotValues())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $plan = Plan::query()->where('code', self::PILOT_PLAN_CODE)->firstOrFail();

        return DB::transaction(function () use ($customer, $plan): Subscription {
            /** @var Subscription $subscription */
            $subscription = Subscription::create([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'price_id' => null,
                'status' => SubscriptionStatus::Pilot->value,
                'stripe_subscription_id' => null,
                'trial_ends_at' => Carbon::now()->addDays(self::TRIAL_DAYS),
                'current_period_start' => null,
                'current_period_end' => null,
                'metadata' => [
                    'created_via' => 'CreatePilotSubscriptionAction',
                ],
            ]);

            // Initial state row: birth, not transition (from == to). Mirrors
            // CreateSubscriptionAction's convention (ADR-016).
            SubscriptionStateTransition::create([
                'subscription_id' => $subscription->id,
                'from_status' => SubscriptionStatus::Pilot->value,
                'to_status' => SubscriptionStatus::Pilot->value,
                'reason' => 'pilot_created',
                'context' => [
                    'plan_code' => $plan->code,
                    'trial_days' => self::TRIAL_DAYS,
                ],
                'transitioned_at' => new DateTimeImmutable,
            ]);

            // Post-commit: the PR-S listener materializes pilot entitlements.
            SubscriptionCreated::dispatch($subscription);

            return $subscription;
        });
    }
}
