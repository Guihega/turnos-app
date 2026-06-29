<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Subscriptions;

use App\Actions\Billing\CreatePilotSubscriptionAction;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Billing\Customer;
use App\Models\Billing\Feature;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanFeature;
use App\Models\Billing\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Behavior tests for CreatePilotSubscriptionAction (PR-S2).
 *
 * The action creates a free pilot subscription locally, without a gateway:
 * status=Pilot, price_id=null, stripe_subscription_id=null, trial_ends_at
 * 90 days out, period columns null. It records the birth state-transition
 * row and dispatches SubscriptionCreated so the PR-S listener materializes
 * the pilot plan's entitlements. It is idempotent against the engine
 * invariant one_active_subscription_per_customer.
 */
final class CreatePilotSubscriptionActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the pilot plan with one quota feature so materialization has
     * something to copy, and return the customer to onboard.
     */
    private function makeCustomerWithPilotPlan(): Customer
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $pilot = Plan::factory()->create(['code' => 'pilot']);
        $feature = Feature::factory()->quota()->create(['code' => 'branches.max']);
        PlanFeature::factory()->forQuota(3)->create([
            'plan_id' => $pilot->id,
            'feature_id' => $feature->id,
        ]);

        return $customer;
    }

    private function action(): CreatePilotSubscriptionAction
    {
        return app(CreatePilotSubscriptionAction::class);
    }

    // ── Creation ────────────────────────────────────────────────────

    #[Test]
    public function it_creates_a_pilot_subscription_without_a_gateway(): void
    {
        $customer = $this->makeCustomerWithPilotPlan();

        $subscription = $this->action()->execute($customer);

        $this->assertSame(SubscriptionStatus::Pilot, $subscription->status);
        $this->assertNull($subscription->price_id);
        $this->assertNull($subscription->stripe_subscription_id);
        $this->assertNull($subscription->current_period_start);
        $this->assertNull($subscription->current_period_end);
        $this->assertSame($customer->id, $subscription->customer_id);
    }

    #[Test]
    public function it_sets_the_trial_to_end_in_ninety_days(): void
    {
        $customer = $this->makeCustomerWithPilotPlan();

        $subscription = $this->action()->execute($customer);

        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertEqualsWithDelta(
            Carbon::now()->addDays(90)->timestamp,
            $subscription->trial_ends_at->timestamp,
            5,
        );
    }

    #[Test]
    public function it_uses_the_pilot_plan_from_the_catalog(): void
    {
        $customer = $this->makeCustomerWithPilotPlan();

        $subscription = $this->action()->execute($customer);

        $this->assertSame('pilot', $subscription->plan->code);
    }

    // ── Birth state-transition row ──────────────────────────────────

    #[Test]
    public function it_records_a_birth_state_transition_row(): void
    {
        $customer = $this->makeCustomerWithPilotPlan();

        $subscription = $this->action()->execute($customer);

        $this->assertDatabaseHas('billing_subscription_state_transitions', [
            'subscription_id' => $subscription->id,
            'from_status' => SubscriptionStatus::Pilot->value,
            'to_status' => SubscriptionStatus::Pilot->value,
            'reason' => 'pilot_created',
        ]);
    }

    // ── Entitlement materialization via the PR-S listener ───────────

    #[Test]
    public function it_materializes_pilot_entitlements_via_the_event(): void
    {
        $customer = $this->makeCustomerWithPilotPlan();

        $subscription = $this->action()->execute($customer);

        // SubscriptionCreated fires; the PR-S listener materializes the
        // pilot plan's features into billing_entitlements.
        $this->assertDatabaseHas('billing_entitlements', [
            'subscription_id' => $subscription->id,
            'value_numeric' => 3,
            'source' => 'plan',
        ]);
    }

    // ── Idempotency against the active-slot invariant ───────────────

    #[Test]
    public function running_twice_does_not_create_a_second_subscription(): void
    {
        $customer = $this->makeCustomerWithPilotPlan();

        $first = $this->action()->execute($customer);
        $second = $this->action()->execute($customer);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            1,
            Subscription::query()->where('customer_id', $customer->id)->count(),
        );
    }

    #[Test]
    public function it_returns_the_existing_subscription_when_one_already_occupies_the_slot(): void
    {
        $customer = $this->makeCustomerWithPilotPlan();
        // A paused subscription grants no access but occupies the active slot.
        $paused = Subscription::factory()->paused()->create([
            'customer_id' => $customer->id,
        ]);

        $result = $this->action()->execute($customer);

        $this->assertSame($paused->id, $result->id);
        $this->assertSame(
            1,
            Subscription::query()->where('customer_id', $customer->id)->count(),
        );
    }
}
