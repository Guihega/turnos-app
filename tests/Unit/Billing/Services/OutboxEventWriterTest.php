<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Services;

use App\Enums\Billing\SubscriptionStatus;
use App\Events\Billing\SubscriptionStateChanged;
use App\Models\Billing\BillingOutboxEvent;
use App\Models\Billing\Subscription;
use App\Services\Billing\OutboxEventWriter;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OutboxEventWriterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_a_subscription_domain_event_to_the_outbox(): void
    {
        $writer = new OutboxEventWriter;
        $occurredAt = new DateTimeImmutable('2026-05-12T12:00:00+00:00');
        $event = new SubscriptionStateChanged(
            subscriptionId: '01J0000000000000000000ABCD',
            from: SubscriptionStatus::Trialing,
            to: SubscriptionStatus::Active,
            reason: 'payment_method_attached',
            actor: 'system',
            occurredAt: $occurredAt,
            metadata: ['source' => 'stripe'],
        );

        $row = $writer->write($event);

        $this->assertInstanceOf(BillingOutboxEvent::class, $row);
        $this->assertSame(Subscription::class, $row->aggregate_type);
        $this->assertSame('01J0000000000000000000ABCD', $row->aggregate_id);
        $this->assertSame('subscription.state-changed', $row->event_type);
        $this->assertNull($row->published_at);
        $this->assertNull($row->failed_at);
        $this->assertSame(0, $row->attempts);

        $payload = $row->payload;
        $this->assertSame('trialing', $payload['from']);
        $this->assertSame('active', $payload['to']);
        $this->assertSame('payment_method_attached', $payload['reason']);
        $this->assertSame('system', $payload['actor']);
        $this->assertSame(['source' => 'stripe'], $payload['metadata']);
    }

    #[Test]
    public function it_persists_distinct_rows_for_repeated_writes(): void
    {
        $writer = new OutboxEventWriter;
        $event = new SubscriptionStateChanged(
            subscriptionId: '01J0000000000000000000ABCD',
            from: SubscriptionStatus::Active,
            to: SubscriptionStatus::Canceled,
            reason: 'user_request',
            actor: null,
            occurredAt: new DateTimeImmutable,
        );

        $row1 = $writer->write($event);
        $row2 = $writer->write($event);

        $this->assertNotSame($row1->id, $row2->id);
        $this->assertSame(2, BillingOutboxEvent::count());
    }
}
