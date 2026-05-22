<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Entitlements;

use App\Models\Billing\Customer;
use App\Models\Billing\Entitlement;
use App\Models\Billing\EntitlementGrant;
use App\Models\Billing\Feature;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanFeature;
use App\Models\Billing\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Behavior tests for EntitlementService::for() and the Entitlement::for()
 * facade (PR-R). Builds on the schema verified in EntitlementSchemaTest.
 *
 * Covers the resolution chain: tenant -> subscription -> base entitlements,
 * grant override (grant wins), the dual-read fallback to the plan catalog
 * while enforcement is disabled, and the deny behavior once enforcement is on.
 */
final class EntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build the structural chain Tenant -> Customer -> active Subscription
     * on a fresh Plan, and return the pieces a test needs to attach
     * features and entitlements.
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

    // ── Base resolution ─────────────────────────────────────────────

    #[Test]
    public function it_resolves_a_boolean_entitlement_from_the_subscription(): void
    {
        [$tenant, $subscription] = $this->makeActiveSubscription();
        $feature = Feature::factory()->boolean()->create(['code' => 'whitelabel.full']);
        Entitlement::factory()->create([
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'value_boolean' => true,
            'value_numeric' => null,
        ]);

        $this->assertTrue(Entitlement::for($tenant)->has('whitelabel.full'));
    }

    #[Test]
    public function it_resolves_a_numeric_quota_from_the_subscription(): void
    {
        [$tenant, $subscription] = $this->makeActiveSubscription();
        $feature = Feature::factory()->quota()->create(['code' => 'branches.max']);
        Entitlement::factory()->create([
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'value_numeric' => 5,
            'value_boolean' => null,
        ]);

        $this->assertSame(5, Entitlement::for($tenant)->quota('branches.max'));
    }

    #[Test]
    public function it_resolves_a_string_entitlement_from_the_subscription(): void
    {
        [$tenant, $subscription] = $this->makeActiveSubscription();
        $feature = Feature::factory()->stringValue()->create(['code' => 'support.tier']);
        Entitlement::factory()->create([
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'value_string' => 'priority',
            'value_numeric' => null,
        ]);

        $this->assertSame('priority', Entitlement::for($tenant)->string('support.tier'));
    }

    #[Test]
    public function it_preserves_an_unlimited_quota_as_minus_one(): void
    {
        [$tenant, $subscription] = $this->makeActiveSubscription();
        $feature = Feature::factory()->quota()->create(['code' => 'branches.max']);
        Entitlement::factory()->create([
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'value_numeric' => -1,
            'value_boolean' => null,
        ]);

        $this->assertSame(-1, Entitlement::for($tenant)->quota('branches.max'));
    }

    // ── Grant override ──────────────────────────────────────────────

    #[Test]
    public function an_active_grant_overrides_the_base_entitlement(): void
    {
        [$tenant, $subscription] = $this->makeActiveSubscription();
        $feature = Feature::factory()->quota()->create(['code' => 'branches.max']);
        Entitlement::factory()->create([
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'value_numeric' => 5,
            'value_boolean' => null,
        ]);
        EntitlementGrant::factory()->create([
            'tenant_id' => $tenant->id,
            'feature_id' => $feature->id,
            'value_numeric' => 50,
            'value_boolean' => null,
        ]);

        $this->assertSame(50, Entitlement::for($tenant)->quota('branches.max'));
    }

    #[Test]
    public function a_revoked_grant_does_not_override_the_base(): void
    {
        [$tenant, $subscription] = $this->makeActiveSubscription();
        $feature = Feature::factory()->quota()->create(['code' => 'branches.max']);
        Entitlement::factory()->create([
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'value_numeric' => 5,
            'value_boolean' => null,
        ]);
        EntitlementGrant::factory()->revoked()->create([
            'tenant_id' => $tenant->id,
            'feature_id' => $feature->id,
            'value_numeric' => 50,
            'value_boolean' => null,
        ]);

        $this->assertSame(5, Entitlement::for($tenant)->quota('branches.max'));
    }

    // ── Dual-read fallback (enforcement off) ────────────────────────

    #[Test]
    public function it_falls_back_to_the_plan_catalog_when_not_materialized(): void
    {
        config(['billing.enforcement.enabled' => false]);
        [$tenant, , $plan] = $this->makeActiveSubscription();
        $feature = Feature::factory()->quota()->create(['code' => 'tickets.monthly']);
        // Catalog row exists, but it was never materialized into billing_entitlements.
        PlanFeature::factory()->forQuota(200)->create([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
        ]);

        $this->assertSame(200, Entitlement::for($tenant)->quota('tickets.monthly'));
    }

    #[Test]
    public function enforcement_on_skips_the_fallback_and_denies_unmaterialized_features(): void
    {
        config(['billing.enforcement.enabled' => true]);
        [$tenant, , $plan] = $this->makeActiveSubscription();
        $feature = Feature::factory()->quota()->create(['code' => 'tickets.monthly']);
        PlanFeature::factory()->forQuota(200)->create([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
        ]);

        // Not materialized + enforcement on => no access (quota 0 default).
        $this->assertSame(0, Entitlement::for($tenant)->quota('tickets.monthly'));
    }

    // ── No access ───────────────────────────────────────────────────

    #[Test]
    public function a_tenant_without_a_subscription_has_no_entitlements(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        $resolved = Entitlement::for($tenant);

        $this->assertFalse($resolved->has('whitelabel.full'));
        $this->assertSame(0, $resolved->quota('branches.max'));
        $this->assertSame('', $resolved->string('support.tier'));
    }

    #[Test]
    public function a_suspended_subscription_grants_no_entitlements(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->suspended()->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
        ]);
        $feature = Feature::factory()->boolean()->create(['code' => 'whitelabel.full']);
        Entitlement::factory()->create([
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'value_boolean' => true,
            'value_numeric' => null,
        ]);

        $this->assertFalse(Entitlement::for($tenant)->has('whitelabel.full'));
    }

    #[Test]
    public function a_past_due_subscription_still_grants_entitlements(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->pastDue()->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
        ]);
        $feature = Feature::factory()->boolean()->create(['code' => 'whitelabel.full']);
        Entitlement::factory()->create([
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'value_boolean' => true,
            'value_numeric' => null,
        ]);

        $this->assertTrue(Entitlement::for($tenant)->has('whitelabel.full'));
    }
}
