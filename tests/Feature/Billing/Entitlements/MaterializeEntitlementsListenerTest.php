<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Entitlements;

use App\Events\Billing\SubscriptionCreated;
use App\Listeners\Billing\MaterializeEntitlementsOnSubscriptionCreated;
use App\Models\Billing\Customer;
use App\Models\Billing\Entitlement;
use App\Models\Billing\Feature;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanFeature;
use App\Models\Billing\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Wiring tests for MaterializeEntitlementsOnSubscriptionCreated (PR-S).
 *
 * Confirms the listener is registered for SubscriptionCreated and, being
 * synchronous, materializes the subscription's plan features inline when
 * the event fires. The materialization logic itself is covered by
 * MaterializeEntitlementsActionTest; here we only verify the event->listener
 * wiring and its synchronous effect.
 */
final class MaterializeEntitlementsListenerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{Subscription, Plan}
     */
    private function makeSubscriptionOnPlan(): array
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->active()->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
        ]);

        return [$subscription, $plan];
    }

    #[Test]
    public function the_listener_is_registered_for_the_event(): void
    {
        Event::fake();

        Event::assertListening(
            SubscriptionCreated::class,
            MaterializeEntitlementsOnSubscriptionCreated::class,
        );
    }

    #[Test]
    public function dispatching_the_event_materializes_entitlements_synchronously(): void
    {
        [$subscription, $plan] = $this->makeSubscriptionOnPlan();
        $feature = Feature::factory()->quota()->create(['code' => 'branches.max']);
        PlanFeature::factory()->forQuota(5)->create([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
        ]);

        event(new SubscriptionCreated($subscription));

        // Synchronous listener: the row exists immediately, no queue worker.
        $this->assertDatabaseHas('billing_entitlements', [
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'value_numeric' => 5,
            'source' => Entitlement::SOURCE_PLAN,
        ]);
    }
}
