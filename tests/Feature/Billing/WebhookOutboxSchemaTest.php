<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Billing\BillingOutboxEvent;
use App\Models\Billing\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Schema and model tests for the webhook inbox and domain outbox tables.
 *
 * These tests exercise the migrations and Eloquent wiring directly.
 * Application logic (signature validation, publisher loop, retry/backoff,
 * Telegram alerting) is tested in later PRs that introduce those components.
 *
 * @see docs/billing/DECISIONS.md ADR-007, ADR-010, ADR-012, ADR-013
 */
final class WebhookOutboxSchemaTest extends TestCase
{
    use RefreshDatabase;

    // -------------------- Webhook events --------------------

    #[Test]
    public function a_webhook_event_can_be_persisted_with_payload(): void
    {
        $event = WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_test_001',
            'event_type' => 'invoice.paid',
            'payload' => ['object' => 'event', 'data' => ['object' => ['id' => 'in_xxx']]],
            'signature_header' => 't=1700000000,v1=abcd',
        ]);

        $this->assertNotNull($event->id);
        $this->assertSame('stripe', $event->gateway);
        $this->assertSame('invoice.paid', $event->event_type);
        $this->assertIsArray($event->payload);
        $this->assertSame('in_xxx', $event->payload['data']['object']['id']);

        // Defaults
        $this->assertFalse($event->needs_review);
        $this->assertSame(0, $event->attempts);
        $this->assertNull($event->processed_at);
    }

    #[Test]
    public function the_same_gateway_event_id_cannot_be_inserted_twice_for_the_same_gateway(): void
    {
        WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_duplicate',
            'event_type' => 'invoice.paid',
            'payload' => [],
        ]);

        $this->expectException(QueryException::class);

        WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_duplicate',
            'event_type' => 'invoice.paid',
            'payload' => [],
        ]);
    }

    #[Test]
    public function the_same_event_id_can_coexist_across_different_gateways(): void
    {
        // Both Stripe and Mercado Pago can theoretically use 'evt_xxx';
        // the UNIQUE is on the (gateway, gateway_event_id) tuple, not on
        // gateway_event_id alone.
        WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_shared_id',
            'event_type' => 'invoice.paid',
            'payload' => [],
        ]);

        $second = WebhookEvent::create([
            'gateway' => 'mercadopago',
            'gateway_event_id' => 'evt_shared_id',
            'event_type' => 'payment.created',
            'payload' => [],
        ]);

        $this->assertNotNull($second->id);
        $this->assertSame(2, WebhookEvent::query()->count());
    }

    #[Test]
    public function pending_scope_returns_unprocessed_events_not_marked_for_review(): void
    {
        WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_pending',
            'event_type' => 'invoice.paid',
            'payload' => [],
        ]);

        WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_processed',
            'event_type' => 'invoice.paid',
            'payload' => [],
            'processed_at' => now(),
        ]);

        WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_needs_review',
            'event_type' => 'invoice.paid',
            'payload' => [],
            'needs_review' => true,
            'attempts' => 5,
            'last_error' => 'Stripe API timeout after 5 attempts',
        ]);

        $pending = WebhookEvent::query()->pending()->get();

        $this->assertCount(1, $pending);
        $this->assertSame('evt_pending', $pending->first()?->gateway_event_id);
    }

    #[Test]
    public function needs_review_scope_isolates_events_for_the_admin_panel(): void
    {
        WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_a',
            'event_type' => 'invoice.paid',
            'payload' => [],
        ]);

        WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_b',
            'event_type' => 'invoice.paid',
            'payload' => [],
            'needs_review' => true,
        ]);

        $review = WebhookEvent::query()->needsReview()->get();

        $this->assertCount(1, $review);
        $this->assertTrue($review->first()?->needs_review);
    }

    #[Test]
    public function for_gateway_scope_filters_events_by_gateway(): void
    {
        WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_stripe',
            'event_type' => 'invoice.paid',
            'payload' => [],
        ]);

        WebhookEvent::create([
            'gateway' => 'mercadopago',
            'gateway_event_id' => 'evt_mp',
            'event_type' => 'payment.created',
            'payload' => [],
        ]);

        $stripeOnly = WebhookEvent::query()->forGateway('stripe')->get();

        $this->assertCount(1, $stripeOnly);
        $this->assertSame('stripe', $stripeOnly->first()?->gateway);
    }

    // -------------------- Outbox events --------------------

    #[Test]
    public function an_outbox_event_can_be_persisted(): void
    {
        $event = BillingOutboxEvent::create([
            'aggregate_type' => 'Subscription',
            'aggregate_id' => '01jx00000000000000000000ab',
            'event_type' => 'SubscriptionActivated',
            'payload' => ['subscription_id' => '01jx...', 'plan' => 'starter'],
        ]);

        $this->assertNotNull($event->id);
        $this->assertSame('Subscription', $event->aggregate_type);
        $this->assertSame('SubscriptionActivated', $event->event_type);
        $this->assertSame('starter', $event->payload['plan']);

        // Defaults
        $this->assertSame(0, $event->attempts);
        $this->assertNull($event->published_at);
        $this->assertNull($event->failed_at);
    }

    #[Test]
    public function pending_scope_excludes_published_and_failed_events(): void
    {
        BillingOutboxEvent::create([
            'aggregate_type' => 'Subscription',
            'aggregate_id' => '01jx00000000000000000000ab',
            'event_type' => 'SubscriptionActivated',
            'payload' => [],
        ]);

        BillingOutboxEvent::create([
            'aggregate_type' => 'Invoice',
            'aggregate_id' => '01jx00000000000000000000cd',
            'event_type' => 'InvoicePaid',
            'payload' => [],
            'published_at' => now(),
        ]);

        BillingOutboxEvent::create([
            'aggregate_type' => 'Payment',
            'aggregate_id' => '01jx00000000000000000000ef',
            'event_type' => 'PaymentFailed',
            'payload' => [],
            'failed_at' => now(),
            'attempts' => 3,
            'last_error' => 'Consumer raised exception',
        ]);

        $pending = BillingOutboxEvent::query()->pending()->get();

        $this->assertCount(1, $pending);
        $this->assertSame('SubscriptionActivated', $pending->first()?->event_type);
    }

    #[Test]
    public function published_and_failed_scopes_are_disjoint(): void
    {
        BillingOutboxEvent::create([
            'aggregate_type' => 'Subscription',
            'aggregate_id' => '01jx00000000000000000000ab',
            'event_type' => 'SubscriptionActivated',
            'payload' => [],
            'published_at' => now(),
        ]);

        BillingOutboxEvent::create([
            'aggregate_type' => 'Payment',
            'aggregate_id' => '01jx00000000000000000000cd',
            'event_type' => 'PaymentFailed',
            'payload' => [],
            'failed_at' => now(),
        ]);

        $this->assertSame(1, BillingOutboxEvent::query()->published()->count());
        $this->assertSame(1, BillingOutboxEvent::query()->failed()->count());
        $this->assertSame(0, BillingOutboxEvent::query()->pending()->count());
    }

    #[Test]
    public function for_aggregate_scope_filters_events_by_aggregate_type(): void
    {
        BillingOutboxEvent::create([
            'aggregate_type' => 'Subscription',
            'aggregate_id' => '01jx00000000000000000000ab',
            'event_type' => 'SubscriptionActivated',
            'payload' => [],
        ]);

        BillingOutboxEvent::create([
            'aggregate_type' => 'Invoice',
            'aggregate_id' => '01jx00000000000000000000cd',
            'event_type' => 'InvoicePaid',
            'payload' => [],
        ]);

        $subs = BillingOutboxEvent::query()->forAggregate('Subscription')->get();

        $this->assertCount(1, $subs);
        $this->assertSame('Subscription', $subs->first()?->aggregate_type);
    }
}
