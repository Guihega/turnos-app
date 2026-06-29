<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Jobs;

use App\Enums\Billing\SubscriptionStatus;
use App\Enums\UserRole;
use App\Jobs\Billing\NotifyPilotExpirationJob;
use App\Mail\Billing\BillingPilotExpiringNotification;
use App\Models\Billing\Customer;
use App\Models\Billing\Plan;
use App\Models\Billing\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for NotifyPilotExpirationJob (PR-P).
 *
 * Verifies email queueing behavior across the configured offset buckets
 * (30, 15, 7, 1 days before trial_ends_at), bucket filtering by status
 * and date, tenant admin resolution, and the notifications.enabled flag.
 */
final class NotifyPilotExpirationJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Default: notifications enabled. Individual tests can override.
        Config::set('billing.notifications.enabled', true);
        Config::set('billing.notifications.pilot_expiration_offsets', [30, 15, 7, 1]);
    }

    // ─── Flag gating ─────────────────────────────────────────────────

    #[Test]
    public function it_is_a_noop_when_notifications_are_disabled(): void
    {
        Config::set('billing.notifications.enabled', false);
        Mail::fake();
        $this->pilotSubscriptionExpiringIn(30);

        (new NotifyPilotExpirationJob)->handle();

        Mail::assertNothingQueued();
    }

    // ─── Happy path per bucket ──────────────────────────────────────

    #[Test]
    public function it_queues_an_email_when_trial_ends_in_thirty_days(): void
    {
        Mail::fake();
        $sub = $this->pilotSubscriptionExpiringIn(30);

        (new NotifyPilotExpirationJob)->handle();

        Mail::assertQueued(BillingPilotExpiringNotification::class, 1);
        Mail::assertQueued(
            BillingPilotExpiringNotification::class,
            fn (BillingPilotExpiringNotification $mail): bool => $mail->daysRemaining === 30
                && $mail->subscription->id === $sub->id,
        );
    }

    #[Test]
    public function it_queues_an_email_at_each_configured_offset(): void
    {
        Mail::fake();
        $this->pilotSubscriptionExpiringIn(30);
        $this->pilotSubscriptionExpiringIn(15);
        $this->pilotSubscriptionExpiringIn(7);
        $this->pilotSubscriptionExpiringIn(1);

        (new NotifyPilotExpirationJob)->handle();

        Mail::assertQueued(BillingPilotExpiringNotification::class, 4);
        foreach ([30, 15, 7, 1] as $offset) {
            Mail::assertQueued(
                BillingPilotExpiringNotification::class,
                fn (BillingPilotExpiringNotification $mail): bool => $mail->daysRemaining === $offset,
            );
        }
    }

    // ─── Negative filters ───────────────────────────────────────────

    #[Test]
    public function it_does_not_queue_for_non_pilot_subscriptions(): void
    {
        Mail::fake();
        $this->subscriptionExpiringIn(30, SubscriptionStatus::Trialing);
        $this->subscriptionExpiringIn(30, SubscriptionStatus::Active);
        $this->subscriptionExpiringIn(30, SubscriptionStatus::PastDue);

        (new NotifyPilotExpirationJob)->handle();

        Mail::assertNothingQueued();
    }

    #[Test]
    public function it_does_not_queue_when_trial_ends_at_does_not_match_any_offset(): void
    {
        Mail::fake();
        $this->pilotSubscriptionExpiringIn(20); // Not in [30, 15, 7, 1]
        $this->pilotSubscriptionExpiringIn(5);
        $this->pilotSubscriptionExpiringIn(45);

        (new NotifyPilotExpirationJob)->handle();

        Mail::assertNothingQueued();
    }

    #[Test]
    public function it_skips_tenants_without_a_tenant_admin(): void
    {
        Mail::fake();
        $this->pilotSubscriptionExpiringIn(30, adminCount: 0);

        (new NotifyPilotExpirationJob)->handle();

        Mail::assertNothingQueued();
    }

    // ─── Multi-admin behavior ───────────────────────────────────────

    #[Test]
    public function it_queues_one_email_per_tenant_admin_when_there_are_multiple(): void
    {
        Mail::fake();
        $this->pilotSubscriptionExpiringIn(30, adminCount: 3);

        (new NotifyPilotExpirationJob)->handle();

        Mail::assertQueued(BillingPilotExpiringNotification::class, 3);
    }

    #[Test]
    public function it_does_not_email_non_admin_users_of_the_tenant(): void
    {
        Mail::fake();
        $sub = $this->pilotSubscriptionExpiringIn(30, adminCount: 1);

        // Add an operator (not an admin) to the same tenant.
        User::factory()->create([
            'tenant_id' => $sub->customer->tenant_id,
            'role' => UserRole::OPERATOR,
        ]);

        (new NotifyPilotExpirationJob)->handle();

        Mail::assertQueued(BillingPilotExpiringNotification::class, 1);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function pilotSubscriptionExpiringIn(int $days, int $adminCount = 1): Subscription
    {
        return $this->subscriptionExpiringIn($days, SubscriptionStatus::Pilot, $adminCount);
    }

    private function subscriptionExpiringIn(
        int $days,
        SubscriptionStatus $status,
        int $adminCount = 1,
    ): Subscription {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();

        for ($i = 0; $i < $adminCount; $i++) {
            User::factory()->create([
                'tenant_id' => $tenant->id,
                'role' => UserRole::TENANT_ADMIN,
            ]);
        }

        /** @var Customer $customer */
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        /** @var Plan $plan */
        $plan = Plan::factory()->create();

        /** @var Subscription $subscription */
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'trial_ends_at' => now()->addDays($days)->startOfDay(),
        ]);

        return $subscription;
    }
}
