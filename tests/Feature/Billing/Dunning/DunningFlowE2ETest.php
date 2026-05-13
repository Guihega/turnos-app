<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Dunning;

use App\Actions\Billing\TransitionSubscriptionAction;
use App\Enums\Billing\SubscriptionStatus;
use App\Jobs\Billing\PublishOutboxEventsJob;
use App\Mail\Billing\BillingPastDueNotification;
use App\Mail\Billing\BillingSubscriptionSuspendedNotification;
use App\Models\Billing\BillingOutboxEvent;
use App\Models\Billing\Customer;
use App\Models\Billing\Subscription;
use App\Services\Billing\OutboxEventDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * End-to-end test of the dunning notification flow.
 *
 * Exercises the full PR-I pipeline:
 *   1. A subscription transitions active → past_due (typical webhook outcome).
 *   2. The outbox writer persists `subscription.state-changed` in tx.
 *   3. PublishOutboxEventsJob claims and dispatches to registered handlers.
 *   4. PastDueEnteredHandler queues the past-due email.
 *   5. Later, past_due → suspended (Stripe gave up via unpaid status).
 *   6. The publisher dispatches again to SubscriptionSuspendedHandler.
 *   7. The suspension email is queued.
 *
 * Verifies the production wiring: the dispatcher is resolved from the
 * container (not manually instanced) so the BillingServiceProvider
 * binding is exercised.
 */
final class DunningFlowE2ETest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function past_due_then_suspended_transitions_send_their_emails(): void
    {
        Mail::fake();

        $customer = Customer::factory()->create(['billing_email' => 'dunning@example.com']);
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $transition = $this->app->make(TransitionSubscriptionAction::class);

        // Step 1: active → past_due (a payment failed).
        $transition->execute(
            subscription: $subscription,
            to: SubscriptionStatus::PastDue,
            reason: 'invoice_payment_failed',
            actor: 'stripe_webhook',
        );

        $this->assertSame(1, BillingOutboxEvent::count());

        // Step 2: publisher runs.
        $this->runPublisher();

        Mail::assertQueued(BillingPastDueNotification::class, 1);
        Mail::assertNotQueued(BillingSubscriptionSuspendedNotification::class);

        $firstRow = BillingOutboxEvent::query()->orderBy('created_at')->first();
        $this->assertNotNull($firstRow);
        $this->assertNotNull($firstRow->published_at);

        // Step 3: past_due → suspended (Stripe gave up).
        $subscription->refresh();
        $transition->execute(
            subscription: $subscription,
            to: SubscriptionStatus::Suspended,
            reason: 'dunning_exhausted',
            actor: 'stripe_webhook',
        );

        $this->assertSame(2, BillingOutboxEvent::count());

        // Step 4: publisher runs again.
        $this->runPublisher();

        Mail::assertQueued(BillingSubscriptionSuspendedNotification::class, 1);
        // The first email did NOT re-queue.
        Mail::assertQueued(BillingPastDueNotification::class, 1);

        $secondRow = BillingOutboxEvent::query()->orderBy('created_at', 'desc')->first();
        $this->assertNotNull($secondRow);
        $this->assertNotNull($secondRow->published_at);
    }

    private function runPublisher(): void
    {
        $this->app->make(PublishOutboxEventsJob::class)->handle(
            $this->app->make(OutboxEventDispatcher::class),
        );
    }
}
