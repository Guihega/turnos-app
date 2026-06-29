<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Entitlements;

use App\Actions\Billing\MaterializeEntitlementsAction;
use App\Models\Billing\Customer;
use App\Models\Billing\Entitlement;
use App\Models\Billing\Feature;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanFeature;
use App\Models\Billing\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Behavior tests for MaterializeEntitlementsAction (PR-S).
 *
 * The action is the write-side counterpart to EntitlementService (PR-R):
 * it snapshots a subscription's plan features into billing_entitlements
 * (source='plan'), copying value_numeric/value_boolean/value_string/
 * reset_period verbatim from the catalog. Covers per-type materialization,
 * unlimited preservation, idempotency on the (subscription_id, feature_id)
 * unique key, grant-row safety, and that the resulting rows feed
 * EntitlementService without the dual-read fallback.
 */
final class MaterializeEntitlementsActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build Tenant -> Customer -> active Subscription on a fresh Plan.
     *
     * @return array{Tenant, Subscription, Plan}
     */
    private function makeActiveSubscription(): array
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->active()->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
        ]);

        return [$tenant, $subscription, $plan];
    }

    private function action(): MaterializeEntitlementsAction
    {
        return app(MaterializeEntitlementsAction::class);
    }

    // ── Per-type materialization ────────────────────────────────────

    #[Test]
    public function it_materializes_a_boolean_feature_from_the_plan(): void
    {
        [, $subscription, $plan] = $this->makeActiveSubscription();
        $feature = Feature::factory()->boolean()->create(['code' => 'whitelabel.full']);
        PlanFeature::factory()->forBoolean(true)->create([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
        ]);

        $this->action()->execute($subscription);

        $this->assertDatabaseHas('billing_entitlements', [
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'value_boolean' => true,
            'source' => Entitlement::SOURCE_PLAN,
        ]);
    }

    #[Test]
    public function it_materializes_a_quota_feature_from_the_plan(): void
    {
        [, $subscription, $plan] = $this->makeActiveSubscription();
        $feature = Feature::factory()->quota()->create(['code' => 'branches.max']);
        PlanFeature::factory()->forQuota(5)->create([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
        ]);

        $this->action()->execute($subscription);

        $this->assertDatabaseHas('billing_entitlements', [
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'value_numeric' => 5,
            'source' => Entitlement::SOURCE_PLAN,
        ]);
    }

    #[Test]
    public function it_materializes_a_string_feature_from_the_plan(): void
    {
        [, $subscription, $plan] = $this->makeActiveSubscription();
        $feature = Feature::factory()->stringValue()->create(['code' => 'support.tier']);
        PlanFeature::factory()->forString('priority')->create([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
        ]);

        $this->action()->execute($subscription);

        $this->assertDatabaseHas('billing_entitlements', [
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'value_string' => 'priority',
            'source' => Entitlement::SOURCE_PLAN,
        ]);
    }

    #[Test]
    public function it_preserves_an_unlimited_quota_as_minus_one(): void
    {
        [, $subscription, $plan] = $this->makeActiveSubscription();
        $feature = Feature::factory()->quota()->create(['code' => 'branches.max']);
        PlanFeature::factory()->unlimited()->create([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
        ]);

        $this->action()->execute($subscription);

        $this->assertDatabaseHas('billing_entitlements', [
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'value_numeric' => -1,
        ]);
    }

    #[Test]
    public function it_copies_the_reset_period_from_the_plan_feature(): void
    {
        [, $subscription, $plan] = $this->makeActiveSubscription();
        $feature = Feature::factory()->quota()->create(['code' => 'tickets.monthly']);
        PlanFeature::factory()->forQuota(200)->monthlyReset()->create([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
        ]);

        $this->action()->execute($subscription);

        $this->assertDatabaseHas('billing_entitlements', [
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'value_numeric' => 200,
            'reset_period' => 'monthly',
        ]);
    }

    // ── Return value ────────────────────────────────────────────────

    #[Test]
    public function it_returns_the_number_of_entitlements_written(): void
    {
        [, $subscription, $plan] = $this->makeActiveSubscription();
        $features = Feature::factory()->count(3)->quota()->create();
        foreach ($features as $feature) {
            PlanFeature::factory()->forQuota(10)->create([
                'plan_id' => $plan->id,
                'feature_id' => $feature->id,
            ]);
        }

        $this->assertSame(3, $this->action()->execute($subscription));
    }

    #[Test]
    public function a_plan_with_no_features_materializes_nothing(): void
    {
        [, $subscription] = $this->makeActiveSubscription();

        $this->assertSame(0, $this->action()->execute($subscription));
        $this->assertDatabaseCount('billing_entitlements', 0);
    }

    // ── Idempotency ─────────────────────────────────────────────────

    #[Test]
    public function running_twice_does_not_duplicate_rows(): void
    {
        [, $subscription, $plan] = $this->makeActiveSubscription();
        $feature = Feature::factory()->quota()->create(['code' => 'branches.max']);
        PlanFeature::factory()->forQuota(5)->create([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
        ]);

        $this->action()->execute($subscription);
        $this->action()->execute($subscription);

        $this->assertDatabaseCount('billing_entitlements', 1);
    }

    #[Test]
    public function re_running_after_a_catalog_change_updates_the_value(): void
    {
        [, $subscription, $plan] = $this->makeActiveSubscription();
        $feature = Feature::factory()->quota()->create(['code' => 'branches.max']);
        $planFeature = PlanFeature::factory()->forQuota(5)->create([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
        ]);

        $this->action()->execute($subscription);

        $planFeature->update(['value_numeric' => 25]);
        $this->action()->execute($subscription);

        $this->assertDatabaseHas('billing_entitlements', [
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'value_numeric' => 25,
        ]);
        $this->assertDatabaseCount('billing_entitlements', 1);
    }

    // ── Grant safety ────────────────────────────────────────────────

    #[Test]
    public function it_never_overwrites_a_grant_sourced_row(): void
    {
        [, $subscription, $plan] = $this->makeActiveSubscription();
        $feature = Feature::factory()->quota()->create(['code' => 'branches.max']);
        // A pre-existing operational override on this subscription/feature.
        Entitlement::factory()->grant()->create([
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'value_numeric' => 99,
        ]);
        // The plan would otherwise materialize a lower value.
        PlanFeature::factory()->forQuota(5)->create([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
        ]);

        $this->action()->execute($subscription);

        $this->assertDatabaseHas('billing_entitlements', [
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'value_numeric' => 99,
            'source' => Entitlement::SOURCE_GRANT,
        ]);
        $this->assertDatabaseCount('billing_entitlements', 1);
    }

    // ── Integration with the read side ──────────────────────────────

    #[Test]
    public function materialized_rows_feed_the_service_without_the_fallback(): void
    {
        config(['billing.enforcement.enabled' => true]); // fallback off
        [$tenant, $subscription, $plan] = $this->makeActiveSubscription();
        $feature = Feature::factory()->quota()->create(['code' => 'branches.max']);
        PlanFeature::factory()->forQuota(7)->create([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
        ]);

        $this->action()->execute($subscription);

        // With enforcement on there is no catalog fallback; the value must
        // come from the materialized billing_entitlements row.
        $this->assertSame(7, Entitlement::for($tenant)->quota('branches.max'));
    }
}
