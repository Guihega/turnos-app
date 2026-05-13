<?php

declare(strict_types=1);

namespace Tests\Feature\Billing\Outbox;

use App\Actions\Billing\TransitionSubscriptionAction;
use App\Contracts\Billing\OutboxEventHandler;
use App\Enums\Billing\SubscriptionStatus;
use App\Jobs\Billing\PublishOutboxEventsJob;
use App\Models\Billing\BillingOutboxEvent;
use App\Models\Billing\Subscription;
use App\Services\Billing\OutboxEventDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * End-to-end test of the transactional outbox pipeline.
 *
 * Exercises the full PR-H feature in one path:
 *   1. A subscription transition is executed.
 *   2. The outbox writer persists a row in the same DB transaction.
 *   3. The publisher job claims the row.
 *   4. The dispatcher routes the row to a registered handler.
 *   5. The handler observes the event payload.
 *   6. The publisher marks the row published.
 */
final class OutboxPublishE2ETest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_state_transition_flows_through_the_outbox_to_a_registered_handler(): void
    {
        $observed = new class
        {
            /** @var list<array{event_type: string, aggregate_id: string, from: string, to: string}> */
            public array $received = [];
        };

        $handler = new class($observed) implements OutboxEventHandler
        {
            public function __construct(private readonly object $observed) {}

            public function handle(BillingOutboxEvent $event): void
            {
                $this->observed->received[] = [
                    'event_type' => $event->event_type,
                    'aggregate_id' => $event->aggregate_id,
                    'from' => $event->payload['from'],
                    'to' => $event->payload['to'],
                ];
            }
        };

        $this->app->instance($handler::class, $handler);
        $this->app->instance(
            OutboxEventDispatcher::class,
            new OutboxEventDispatcher(
                handlers: ['subscription.state-changed' => $handler::class],
                container: $this->app,
            ),
        );

        /** @var Subscription $sub */
        $sub = Subscription::factory()->create(['status' => SubscriptionStatus::Trialing]);

        $this->app->make(TransitionSubscriptionAction::class)->execute(
            subscription: $sub,
            to: SubscriptionStatus::Active,
            reason: 'e2e_trial_converted',
            actor: 'test_system',
        );

        $this->assertSame(1, BillingOutboxEvent::count(), 'outbox row created by transition');
        $pending = BillingOutboxEvent::query()->sole();
        $this->assertNull($pending->published_at);

        $this->app->make(PublishOutboxEventsJob::class)->handle(
            $this->app->make(OutboxEventDispatcher::class),
        );

        $pending->refresh();
        $this->assertNotNull($pending->published_at, 'row marked published');
        $this->assertNull($pending->failed_at);
        $this->assertSame(1, $pending->attempts);

        $this->assertCount(1, $observed->received);
        $this->assertSame('subscription.state-changed', $observed->received[0]['event_type']);
        $this->assertSame((string) $sub->id, $observed->received[0]['aggregate_id']);
        $this->assertSame('trialing', $observed->received[0]['from']);
        $this->assertSame('active', $observed->received[0]['to']);
    }
}
