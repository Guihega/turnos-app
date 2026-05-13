<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Outbox\Handlers;

use App\Billing\Outbox\Handlers\PastDueEnteredHandler;
use App\Enums\Billing\SubscriptionStatus;
use App\Mail\Billing\BillingPastDueNotification;
use App\Models\Billing\BillingOutboxEvent;
use App\Models\Billing\Customer;
use App\Models\Billing\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PastDueEnteredHandlerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_queues_the_past_due_email_to_the_customer_billing_email(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create(['billing_email' => 'tenant@example.com']);
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'status' => SubscriptionStatus::PastDue,
        ]);

        $event = BillingOutboxEvent::create([
            'aggregate_type' => Subscription::class,
            'aggregate_id' => $subscription->id,
            'event_type' => 'subscription.state-changed',
            'payload' => [
                'subscription_id' => $subscription->id,
                'from' => 'active',
                'to' => 'past_due',
            ],
        ]);

        $this->app->make(PastDueEnteredHandler::class)->handle($event);

        Mail::assertQueued(BillingPastDueNotification::class, function (BillingPastDueNotification $mail) use ($subscription): bool {
            return $mail->hasTo('tenant@example.com')
                && $mail->subscription->is($subscription);
        });
    }

    #[Test]
    public function it_ignores_events_whose_to_is_not_past_due(): void
    {
        Mail::fake();

        $subscription = Subscription::factory()->create();

        $event = BillingOutboxEvent::create([
            'aggregate_type' => Subscription::class,
            'aggregate_id' => $subscription->id,
            'event_type' => 'subscription.state-changed',
            'payload' => [
                'from' => 'trialing',
                'to' => 'active',
            ],
        ]);

        $this->app->make(PastDueEnteredHandler::class)->handle($event);

        Mail::assertNothingQueued();
    }

    #[Test]
    public function it_does_nothing_when_subscription_no_longer_exists(): void
    {
        Mail::fake();

        $event = BillingOutboxEvent::create([
            'aggregate_type' => Subscription::class,
            'aggregate_id' => '01J0000000000000000000ZZZZ',
            'event_type' => 'subscription.state-changed',
            'payload' => ['to' => 'past_due'],
        ]);

        $this->app->make(PastDueEnteredHandler::class)->handle($event);

        Mail::assertNothingQueued();
    }

    #[Test]
    public function it_does_nothing_when_customer_has_no_billing_email(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create(['billing_email' => '']);
        $subscription = Subscription::factory()->create(['customer_id' => $customer->id]);

        $event = BillingOutboxEvent::create([
            'aggregate_type' => Subscription::class,
            'aggregate_id' => $subscription->id,
            'event_type' => 'subscription.state-changed',
            'payload' => ['to' => 'past_due'],
        ]);

        $this->app->make(PastDueEnteredHandler::class)->handle($event);

        Mail::assertNothingQueued();
    }
}
