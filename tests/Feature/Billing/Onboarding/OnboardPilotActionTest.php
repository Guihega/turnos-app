<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Onboarding;

use App\Actions\Billing\OnboardPilotAction;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Billing\Customer;
use App\Models\Billing\Feature;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanFeature;
use App\Models\Billing\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Behavior tests for OnboardPilotAction (PR-S3).
 *
 * Composes CreatePilotCustomerAction + CreatePilotSubscriptionAction to put
 * a tenant on the free pilot plan end to end: local Customer, pilot
 * Subscription, and (via the PR-S listener on SubscriptionCreated)
 * materialized entitlements — all without a gateway. Idempotent as a whole
 * because both component actions are.
 */
final class OnboardPilotActionTest extends TestCase
{
    use RefreshDatabase;

    private function seedPilotPlan(): void
    {
        $pilot = Plan::factory()->create(['code' => 'pilot']);
        $feature = Feature::factory()->quota()->create(['code' => 'branches.max']);
        PlanFeature::factory()->forQuota(3)->create([
            'plan_id' => $pilot->id,
            'feature_id' => $feature->id,
        ]);
    }

    private function action(): OnboardPilotAction
    {
        return app(OnboardPilotAction::class);
    }

    #[Test]
    public function it_onboards_a_tenant_with_customer_subscription_and_entitlements(): void
    {
        $this->seedPilotPlan();
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        $subscription = $this->action()->execute($tenant);

        // Customer created locally for the tenant.
        $this->assertDatabaseHas('billing_customers', [
            'tenant_id' => $tenant->id,
        ]);

        // Pilot subscription, no gateway.
        $this->assertSame(SubscriptionStatus::Pilot, $subscription->status);
        $this->assertNull($subscription->stripe_subscription_id);

        // Entitlements materialized via the SubscriptionCreated listener.
        $this->assertDatabaseHas('billing_entitlements', [
            'subscription_id' => $subscription->id,
            'value_numeric' => 3,
            'source' => 'plan',
        ]);

        // No gateway artifacts anywhere.
        $this->assertDatabaseCount('billing_customer_gateway_refs', 0);
    }

    #[Test]
    public function it_links_the_subscription_to_the_tenants_customer(): void
    {
        $this->seedPilotPlan();
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        $subscription = $this->action()->execute($tenant);

        $customer = Customer::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame($customer->id, $subscription->customer_id);
    }

    #[Test]
    public function running_twice_does_not_duplicate_customer_or_subscription(): void
    {
        $this->seedPilotPlan();
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        $first = $this->action()->execute($tenant);
        $second = $this->action()->execute($tenant);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Customer::query()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(
            1,
            Subscription::query()
                ->whereIn('customer_id', Customer::query()->where('tenant_id', $tenant->id)->pluck('id'))
                ->count(),
        );
    }
}
