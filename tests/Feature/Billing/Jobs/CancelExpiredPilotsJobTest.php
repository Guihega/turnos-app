<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Jobs;

use App\Actions\Billing\TransitionSubscriptionAction;
use App\Enums\Billing\SubscriptionStatus;
use App\Jobs\Billing\CancelExpiredPilotsJob;
use App\Models\Billing\Customer;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for CancelExpiredPilotsJob (PR-T).
 *
 * Verifies the nightly trial-expiry job named in ADR-014 §4: pilots whose
 * trial_ends_at has passed get transitioned to Canceled via the canonical
 * TransitionSubscriptionAction. Covers the config gate, the WHERE filters
 * (status, trial_ends_at, stripe_subscription_id), and the audit trail.
 */
final class CancelExpiredPilotsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Default: enabled. Individual tests can override.
        Config::set('billing.trial_expiration.enabled', true);
    }

    // ─── Flag gating ─────────────────────────────────────────────────

    #[Test]
    public function it_is_a_noop_when_trial_expiration_is_disabled(): void
    {
        Config::set('billing.trial_expiration.enabled', false);
        $sub = $this->pilot(trialEndsInDays: -1);

        $this->runJob();

        $this->assertSame(SubscriptionStatus::Pilot, $sub->refresh()->status);
    }

    // ─── Happy path ──────────────────────────────────────────────────

    #[Test]
    public function it_cancels_a_pilot_whose_trial_has_ended(): void
    {
        $sub = $this->pilot(trialEndsInDays: -1);

        $this->runJob();

        $this->assertSame(SubscriptionStatus::Canceled, $sub->refresh()->status);
    }

    #[Test]
    public function it_leaves_pilots_with_a_valid_trial_alone(): void
    {
        $sub = $this->pilot(trialEndsInDays: 30);

        $this->runJob();

        $this->assertSame(SubscriptionStatus::Pilot, $sub->refresh()->status);
    }

    // ─── Negative filters ───────────────────────────────────────────

    #[Test]
    public function it_does_not_touch_already_canceled_subscriptions(): void
    {
        $sub = $this->subscription(
            status: SubscriptionStatus::Canceled,
            trialEndsInDays: -10,
        );

        $this->runJob();

        $this->assertSame(SubscriptionStatus::Canceled, $sub->refresh()->status);
    }

    #[Test]
    public function it_skips_pilots_with_a_stripe_subscription_id(): void
    {
        // Anomalous: a pilot with a gateway id. Manual inspection territory,
        // not automated cancellation. The job's WHERE clause excludes it.
        $sub = $this->pilot(trialEndsInDays: -1, stripeSubscriptionId: 'sub_anomalous');

        $this->runJob();

        $this->assertSame(SubscriptionStatus::Pilot, $sub->refresh()->status);
    }

    // ─── Audit trail ────────────────────────────────────────────────

    #[Test]
    public function it_creates_a_state_transition_audit_row(): void
    {
        $sub = $this->pilot(trialEndsInDays: -1);

        $this->runJob();

        $this->assertDatabaseHas('billing_subscription_state_transitions', [
            'subscription_id' => $sub->id,
            'from_status' => SubscriptionStatus::Pilot->value,
            'to_status' => SubscriptionStatus::Canceled->value,
            'reason' => 'pilot trial expired',
        ]);
    }

    // ─── Batch behavior ─────────────────────────────────────────────

    #[Test]
    public function it_processes_multiple_expired_pilots_in_one_run(): void
    {
        $a = $this->pilot(trialEndsInDays: -1);
        $b = $this->pilot(trialEndsInDays: -5);
        $c = $this->pilot(trialEndsInDays: -30);
        $stillValid = $this->pilot(trialEndsInDays: 10);

        $this->runJob();

        $this->assertSame(SubscriptionStatus::Canceled, $a->refresh()->status);
        $this->assertSame(SubscriptionStatus::Canceled, $b->refresh()->status);
        $this->assertSame(SubscriptionStatus::Canceled, $c->refresh()->status);
        $this->assertSame(SubscriptionStatus::Pilot, $stillValid->refresh()->status);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function runJob(): void
    {
        (new CancelExpiredPilotsJob)->handle(
            app(TransitionSubscriptionAction::class),
        );
    }

    private function pilot(int $trialEndsInDays, ?string $stripeSubscriptionId = null): Subscription
    {
        return $this->subscription(
            status: SubscriptionStatus::Pilot,
            trialEndsInDays: $trialEndsInDays,
            stripeSubscriptionId: $stripeSubscriptionId,
        );
    }

    private function subscription(
        SubscriptionStatus $status,
        int $trialEndsInDays,
        ?string $stripeSubscriptionId = null,
    ): Subscription {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        /** @var Customer $customer */
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        /** @var Plan $plan */
        $plan = Plan::factory()->create();

        /** @var Subscription $subscription */
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'trial_ends_at' => now()->addDays($trialEndsInDays)->startOfDay(),
            'stripe_subscription_id' => $stripeSubscriptionId,
        ]);

        return $subscription;
    }
}
