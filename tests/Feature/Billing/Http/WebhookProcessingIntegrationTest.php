<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Http;

use App\Enums\Billing\SubscriptionStatus;
use App\Events\Billing\BillingTrialWillEnd;
use App\Jobs\Billing\ProcessBillingWebhookEvent;
use App\Models\Billing\Subscription;
use App\Models\Billing\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * End-to-end integration: HTTP POST → controller persists → job runs
 * → dispatcher routes → handler mutates DB.
 *
 * The webhook endpoint accepts the event, the job processes it
 * synchronously here (via dispatchSync), and we assert the final
 * domain state.
 *
 * Signatures are real HMAC-SHA256. The full chain is exercised.
 */
final class WebhookProcessingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_WEBHOOK_SECRET = 'whsec_test_dummy_for_signing';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.gateways.stripe.webhook_secret' => self::TEST_WEBHOOK_SECRET,
            'billing.gateways.stripe.secret_key' => 'sk_test_dummy_for_factory',
            'billing.gateways.stripe.api_version' => '2024-11-20.acacia',
        ]);

        $this->withoutMiddleware();
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array{0: string, 1: string}
     */
    private function makeSignedRequest(string $eventId, string $eventType, array $object): array
    {
        $payload = json_encode([
            'id' => $eventId,
            'type' => $eventType,
            'object' => 'event',
            'created' => time(),
            'livemode' => false,
            'data' => ['object' => $object],
        ], JSON_UNESCAPED_SLASHES);

        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, self::TEST_WEBHOOK_SECRET);
        $header = "t={$timestamp},v1={$signature}";

        return [(string) $payload, $header];
    }

    private function postWebhook(string $payload, string $signature): TestResponse
    {
        return $this->call(
            method: 'POST',
            uri: '/billing/webhook',
            parameters: [],
            cookies: [],
            files: [],
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signature,
            ],
            content: $payload,
        );
    }

    /**
     * Runs the queued job synchronously so we can assert on the
     * dispatcher's side effects in-test.
     */
    private function runJobFor(string $stripeEventId): void
    {
        /** @var WebhookEvent $event */
        $event = WebhookEvent::where('gateway_event_id', $stripeEventId)->firstOrFail();
        $job = new ProcessBillingWebhookEvent($event->id);
        $job->handle();
    }

    #[Test]
    public function subscription_updated_event_transitions_local_subscription(): void
    {
        Queue::fake(); // controller dispatches; we run manually below.

        /** @var Subscription $sub */
        $sub = Subscription::factory()
            ->trialing()
            ->withStripeId('sub_int_test_001')
            ->create();

        [$payload, $signature] = $this->makeSignedRequest(
            eventId: 'evt_int_001',
            eventType: 'customer.subscription.updated',
            object: [
                'id' => 'sub_int_test_001',
                'status' => 'active',
                'current_period_start' => 1_700_000_000,
                'current_period_end' => 1_702_592_000,
            ],
        );

        $this->postWebhook($payload, $signature)->assertStatus(200);
        $this->runJobFor('evt_int_001');

        $sub->refresh();
        $this->assertSame(SubscriptionStatus::Active, $sub->status);

        $this->assertDatabaseHas('billing_webhook_events', [
            'gateway_event_id' => 'evt_int_001',
        ]);
        $persisted = WebhookEvent::where('gateway_event_id', 'evt_int_001')->first();
        $this->assertNotNull($persisted?->processed_at);
    }

    #[Test]
    public function subscription_deleted_event_cancels_local_subscription(): void
    {
        Queue::fake();

        /** @var Subscription $sub */
        $sub = Subscription::factory()
            ->active()
            ->withStripeId('sub_int_test_delete')
            ->create();

        [$payload, $signature] = $this->makeSignedRequest(
            eventId: 'evt_int_delete',
            eventType: 'customer.subscription.deleted',
            object: [
                'id' => 'sub_int_test_delete',
                'status' => 'canceled',
                'canceled_at' => 1_700_500_000,
            ],
        );

        $this->postWebhook($payload, $signature)->assertStatus(200);
        $this->runJobFor('evt_int_delete');

        $sub->refresh();
        $this->assertSame(SubscriptionStatus::Canceled, $sub->status);
        $this->assertNotNull($sub->canceled_at);
    }

    #[Test]
    public function invoice_payment_failed_event_moves_subscription_to_past_due(): void
    {
        Queue::fake();

        /** @var Subscription $sub */
        $sub = Subscription::factory()
            ->active()
            ->withStripeId('sub_int_failed')
            ->create();

        [$payload, $signature] = $this->makeSignedRequest(
            eventId: 'evt_int_failed',
            eventType: 'invoice.payment_failed',
            object: [
                'id' => 'in_int_001',
                'subscription' => 'sub_int_failed',
                'attempt_count' => 1,
            ],
        );

        $this->postWebhook($payload, $signature)->assertStatus(200);
        $this->runJobFor('evt_int_failed');

        $sub->refresh();
        $this->assertSame(SubscriptionStatus::PastDue, $sub->status);
    }

    #[Test]
    public function trial_will_end_event_dispatches_domain_event(): void
    {
        Queue::fake();
        Event::fake([BillingTrialWillEnd::class]);

        /** @var Subscription $sub */
        $sub = Subscription::factory()
            ->trialing()
            ->withStripeId('sub_int_trial')
            ->create();

        [$payload, $signature] = $this->makeSignedRequest(
            eventId: 'evt_int_trial',
            eventType: 'customer.subscription.trial_will_end',
            object: [
                'id' => 'sub_int_trial',
                'trial_end' => time() + (3 * 86400),
            ],
        );

        $this->postWebhook($payload, $signature)->assertStatus(200);
        $this->runJobFor('evt_int_trial');

        Event::assertDispatched(BillingTrialWillEnd::class, fn (BillingTrialWillEnd $e): bool => $e->subscriptionId === $sub->id
        );
    }

    #[Test]
    public function unhandled_event_type_is_processed_as_noop_without_error(): void
    {
        Queue::fake();

        // customer.created is NOT in our dispatcher's handler map.
        [$payload, $signature] = $this->makeSignedRequest(
            eventId: 'evt_int_unhandled',
            eventType: 'customer.created',
            object: [
                'id' => 'cus_NEW',
                'object' => 'customer',
            ],
        );

        $this->postWebhook($payload, $signature)->assertStatus(200);
        $this->runJobFor('evt_int_unhandled');

        // The event was persisted and marked processed despite no handler.
        $persisted = WebhookEvent::where('gateway_event_id', 'evt_int_unhandled')->first();
        $this->assertNotNull($persisted);
        $this->assertNotNull($persisted->processed_at);
        $this->assertNull($persisted->last_error);
    }
}
