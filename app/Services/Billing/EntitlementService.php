<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Billing\DTOs\ResolvedEntitlements;
use App\Models\Billing\Entitlement;
use App\Models\Billing\EntitlementGrant;
use App\Models\Billing\Feature;
use App\Models\Billing\PlanFeature;
use App\Models\Billing\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a tenant's effective entitlements into a ResolvedEntitlements
 * snapshot (keyed by feature code), the single dependency the product has
 * on the billing module (per DECISIONS.md).
 *
 * Resolution chain:
 *   1. Tenant -> Subscription (structural 1:1:1 via Customer).
 *   2. If there is no subscription, or its status does not grant access
 *      (SubscriptionStatus::grantsAccess()), the tenant has no entitlements.
 *   3. Plan-derived entitlements (billing_entitlements) form the base.
 *   4. Active operational grants (billing_entitlement_grants, scope active())
 *      override the base per feature code. Grant wins.
 *   5. Dual-read fallback (MIGRATION_PLAN Fase C): while
 *      billing.enforcement.enabled is false, any plan feature not yet
 *      materialized into billing_entitlements is read straight from the
 *      catalog (billing_plan_features). Once enforcement is on, a missing
 *      entitlement means no access — no fallback.
 *
 * The merge precedence is grant > base > catalog-fallback.
 */
final class EntitlementService
{
    /**
     * Resolve the effective entitlements for a tenant.
     */
    public function for(Tenant $tenant): ResolvedEntitlements
    {
        /** @var Subscription|null $subscription */
        $subscription = $tenant->subscription()->with([
            'entitlements.feature:id,code',
        ])->first();

        if ($subscription === null || ! $subscription->status->grantsAccess()) {
            return new ResolvedEntitlements([]);
        }

        // Base layer: plan-derived entitlements, keyed by feature code.
        $values = [];

        /** @var Collection<int, Entitlement> $entitlements */
        $entitlements = $subscription->entitlements;
        foreach ($entitlements as $entitlement) {
            /** @var Feature $feature */
            $feature = $entitlement->feature;
            $values[$feature->code] = $this->extractEntitlement($entitlement);
        }

        // Override layer: active grants for this tenant. Grant wins.
        /** @var Collection<int, EntitlementGrant> $grants */
        $grants = EntitlementGrant::query()
            ->where('tenant_id', $tenant->id)
            ->active()
            ->with('feature:id,code')
            ->get();

        foreach ($grants as $grant) {
            /** @var Feature $feature */
            $feature = $grant->feature;
            $values[$feature->code] = $this->extractGrant($grant);
        }

        // Plan catalog: read once, used either to fill the snapshot (when
        // enforcement is disabled, Fase C dual-read) or to detect denials
        // for observability (when enforcement is enabled, Fase F).
        /** @var Collection<int, PlanFeature> $planFeatures */
        $planFeatures = PlanFeature::query()
            ->where('plan_id', $subscription->plan_id)
            ->with('feature:id,code')
            ->get();

        $enforcementEnabled = (bool) config('billing.enforcement.enabled');

        foreach ($planFeatures as $planFeature) {
            /** @var Feature $feature */
            $feature = $planFeature->feature;
            if (array_key_exists($feature->code, $values)) {
                continue;
            }

            if ($enforcementEnabled) {
                // Fase F: a plan feature not materialized into
                // billing_entitlements is denied. Log structured so the
                // metric is aggregable in dashboards (MIGRATION_PLAN Fase F
                // verification: 'metricas de bloqueos por entitlement').
                Log::info('billing.entitlement.denied', [
                    'tenant_id' => $tenant->id,
                    'feature_code' => $feature->code,
                    'subscription_id' => $subscription->id,
                    'reason' => 'not_materialized',
                ]);

                continue;
            }

            // Fase C dual-read fallback: fill the snapshot from the catalog.
            $values[$feature->code] = $this->extractPlanFeature($planFeature);
        }

        return new ResolvedEntitlements($values);
    }

    /**
     * @return array{numeric: int|null, boolean: bool|null, string: string|null, reset_period: string|null}
     */
    private function extractEntitlement(Entitlement $entitlement): array
    {
        return [
            'numeric' => $entitlement->value_numeric,
            'boolean' => $entitlement->value_boolean,
            'string' => $entitlement->value_string,
            'reset_period' => $entitlement->reset_period,
        ];
    }

    /**
     * Grants carry no reset_period column; periodic resets live on the
     * plan-derived entitlement only.
     *
     * @return array{numeric: int|null, boolean: bool|null, string: string|null, reset_period: string|null}
     */
    private function extractGrant(EntitlementGrant $grant): array
    {
        return [
            'numeric' => $grant->value_numeric,
            'boolean' => $grant->value_boolean,
            'string' => $grant->value_string,
            'reset_period' => null,
        ];
    }

    /**
     * @return array{numeric: int|null, boolean: bool|null, string: string|null, reset_period: string|null}
     */
    private function extractPlanFeature(PlanFeature $planFeature): array
    {
        return [
            'numeric' => $planFeature->value_numeric,
            'boolean' => $planFeature->value_boolean,
            'string' => $planFeature->value_string,
            'reset_period' => $planFeature->reset_period,
        ];
    }
}
