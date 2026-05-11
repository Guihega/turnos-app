<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Http;

use App\Jobs\Billing\ProcessBillingWebhookEvent;
use App\Models\Billing\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for POST /billing/webhook.
 *
 * The full controller path is exercised: raw payload + Stripe-Signature
 * header → BillingGateway::verifyWebhookSignature (real, not mocked)
 * → DB persistence → job dispatch.
 *
 * Signatures are generated with the actual Stripe algorithm:
 *   HMAC-SHA256("{timestamp}.{payload}", $webhookSecret)
 *   header format: "t={timestamp},v1={hex_signature}"
 *
 * This exercises HandlesStripeExceptions translation end-to-end.
 */
final class WebhookEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_WEBHOOK_SECRET = 'whsec_test_dummy_for_signing';

    protected function setUp(): void
    {
        parent::setUp();

        // Override the config so the controller's gateway resolves the
        // dummy secret instead of whatever the .env has.
        config([
            'billing.gateways.stripe.webhook_secret' => self::TEST_WEBHOOK_SECRET,
            'billing.gateways.stripe.secret_key' => 'sk_test_dummy_for_factory',
            'billing.gateways.stripe.api_version' => '2024-11-20.acacia',
        ]);

        // The webhook endpoint is public and CSRF-excluded in
        // bootstrap/app.php. Tests bypass middleware to keep them
        // independent of the framework's CSRF stack and to avoid
        // session bootstrap in CI.
        $this->withoutMiddleware();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function makeSignedRequest(string $eventId, string $eventType): array
    {
        $payload = json_encode([
            'id' => $eventId,
            'type' => $eventType,
            'object' => 'event',
            'created' => time(),
            'livemode' => false,
            'data' => [
                'object' => [
                    'id' => 'obj_test_inner',
                    'object' => 'customer',
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);

        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, self::TEST_WEBHOOK_SECRET);
        $header = "t={$timestamp},v1={$signature}";

        return [$payload, $header];
    }

    #[Test]
    public function valid_signature_persists_event_and_dispatches_job(): void
    {
        Queue::fake();

        [$payload, $signature] = $this->makeSignedRequest('evt_test_001', 'customer.subscription.created');

        $response = $this->call(
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

        $response->assertStatus(200);
        $response->assertJson(['received' => true]);

        $this->assertDatabaseHas('billing_webhook_events', [
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_test_001',
            'event_type' => 'customer.subscription.created',
            'processed_at' => null, // job hasn't run yet (faked)
        ]);

        Queue::assertPushed(ProcessBillingWebhookEvent::class, function (ProcessBillingWebhookEvent $job): bool {
            $persisted = WebhookEvent::where('gateway_event_id', 'evt_test_001')->first();

            return $persisted !== null && $job->webhookEventId === $persisted->id;
        });
    }

    #[Test]
    public function duplicate_event_returns_200_without_dispatching_again(): void
    {
        Queue::fake();

        [$payload, $signature] = $this->makeSignedRequest('evt_test_dup', 'invoice.paid');

        // First delivery.
        $this->call(
            'POST', '/billing/webhook', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature],
            $payload,
        )->assertStatus(200);

        // Second delivery: Stripe retrying the same event_id.
        [$payload2, $signature2] = $this->makeSignedRequest('evt_test_dup', 'invoice.paid');
        $this->call(
            'POST', '/billing/webhook', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature2],
            $payload2,
        )->assertStatus(200);

        // Still only one row.
        $this->assertSame(1, WebhookEvent::where('gateway_event_id', 'evt_test_dup')->count());
        // And exactly one job push.
        Queue::assertPushed(ProcessBillingWebhookEvent::class, 1);
    }

    #[Test]
    public function invalid_signature_returns_400_and_persists_nothing(): void
    {
        Queue::fake();

        [$payload, $_realSignature] = $this->makeSignedRequest('evt_invalid', 'customer.created');

        $response = $this->call(
            'POST', '/billing/webhook', [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=1700000000,v1=deadbeef0000000000000000000000000000000000000000000000000000beef',
            ],
            $payload,
        );

        $response->assertStatus(400);
        $response->assertJson(['error' => 'invalid_signature']);

        $this->assertSame(0, WebhookEvent::count());
        Queue::assertNotPushed(ProcessBillingWebhookEvent::class);
    }

    #[Test]
    public function missing_signature_header_returns_400(): void
    {
        Queue::fake();

        [$payload, $_signature] = $this->makeSignedRequest('evt_no_sig', 'customer.created');

        $response = $this->call(
            'POST', '/billing/webhook', [], [], [],
            ['CONTENT_TYPE' => 'application/json'], // NO HTTP_STRIPE_SIGNATURE
            $payload,
        );

        $response->assertStatus(400);
        $response->assertJson(['error' => 'invalid_payload']);

        $this->assertSame(0, WebhookEvent::count());
        Queue::assertNotPushed(ProcessBillingWebhookEvent::class);
    }

    #[Test]
    public function empty_body_returns_400(): void
    {
        Queue::fake();

        $response = $this->call(
            'POST', '/billing/webhook', [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't=1700000000,v1=deadbeef',
            ],
            '',
        );

        $response->assertStatus(400);
        $response->assertJson(['error' => 'invalid_payload']);
    }

    #[Test]
    public function payload_without_id_or_type_returns_400(): void
    {
        Queue::fake();

        // Build a valid-signature payload that's missing id and type.
        $payload = json_encode(['object' => 'event', 'created' => time()]);
        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, self::TEST_WEBHOOK_SECRET);
        $header = "t={$timestamp},v1={$signature}";

        $response = $this->call(
            'POST', '/billing/webhook', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $header],
            (string) $payload,
        );

        $response->assertStatus(400);
        $response->assertJson(['error' => 'invalid_payload']);
        $this->assertSame(0, WebhookEvent::count());
    }
}
