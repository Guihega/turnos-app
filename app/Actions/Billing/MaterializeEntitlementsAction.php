<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\Billing\Entitlement;
use App\Models\Billing\PlanFeature;
use App\Models\Billing\Subscription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * MaterializeEntitlementsAction: snapshots a subscription's plan features
 * into billing_entitlements (one row per feature, source='plan').
 *
 * This is the write-side counterpart to EntitlementService (PR-R): the
 * service reads billing_entitlements at runtime, and — while
 * billing.enforcement.enabled is false — falls back to billing_plan_features
 * for anything not yet materialized. This action eliminates the need for
 * that fallback by copying the catalog values verbatim at activation time,
 * decoupling the active subscription from later catalog changes.
 *
 * Invoked by:
 *   - MaterializeEntitlementsOnSubscriptionCreated listener (new subs).
 *   - billing:backfill-existing-tenants command (existing subs, PR-S2).
 *
 * Idempotent: re-running upserts on the (subscription_id, feature_id)
 * unique key, so a replayed event or a re-run backfill produces the same
 * state without duplicate rows.
 *
 * Grant safety: operational overrides (source='grant') are NEVER touched.
 * Grants are merged at read time by EntitlementService from the separate
 * billing_entitlement_grants table; should a 'grant'-sourced row ever exist
 * in billing_entitlements, this action leaves it intact.
 */
final class MaterializeEntitlementsAction
{
    /**
     * Materialize the plan-derived entitlements for a subscription.
     *
     * @return int Number of entitlements written (created or updated).
     */
    public function execute(Subscription $subscription): int
    {
        /** @var Collection<int, PlanFeature> $planFeatures */
        $planFeatures = $subscription->plan->planFeatures()->get();

        if ($planFeatures->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($subscription, $planFeatures): int {
            $written = 0;

            foreach ($planFeatures as $planFeature) {
                /** @var Entitlement|null $existing */
                $existing = Entitlement::query()
                    ->where('subscription_id', $subscription->id)
                    ->where('feature_id', $planFeature->feature_id)
                    ->first();

                // Never overwrite an operational grant override.
                if ($existing !== null && $existing->source === Entitlement::SOURCE_GRANT) {
                    continue;
                }

                Entitlement::query()->updateOrCreate(
                    [
                        'subscription_id' => $subscription->id,
                        'feature_id' => $planFeature->feature_id,
                    ],
                    [
                        'value_numeric' => $planFeature->value_numeric,
                        'value_boolean' => $planFeature->value_boolean,
                        'value_string' => $planFeature->value_string,
                        'reset_period' => $planFeature->reset_period,
                        'source' => Entitlement::SOURCE_PLAN,
                    ],
                );

                $written++;
            }

            return $written;
        });
    }
}
