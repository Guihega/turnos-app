<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Webhooks\Handlers;

use App\Billing\Webhooks\Handlers\TrialWillEndHandler;
use App\Events\Billing\BillingTrialWillEnd;
use App\Models\Billing\BillingOutboxEvent;
use App\Models\Billing\Subscription;
use App\Models\Billing\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TrialWillEndHandlerOutboxTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_writes_a_trial_will_end_event_to_the_outbox_and_dispatches_in_process(): void
    {
        Event::fake([BillingTrialWillEnd::class]);

        /** @var Subscription $sub */
        $sub = Subscription::factory()->create([
            'stripe_subscription_id' => 'sub_test_trialwillend_1',
        ]);

        $webhookEvent = WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_trialwillend_test_1',
            'event_type' => 'customer.subscription.trial_will_end',
            'payload' => [
                'data' => [
                    'object' => [
                        'id' => 'sub_test_trialwillend_1',
                        'trial_end' => time() + (3 * 86400),
                    ],
                ],
            ],
        ]);

        $handler = $this->app->make(TrialWillEndHandler::class);
        $handler->handle($webhookEvent);

        $this->assertSame(1, BillingOutboxEvent::count());
        $row = BillingOutboxEvent::query()->sole();
        $this->assertSame('subscription.trial-will-end', $row->event_type);
        $this->assertSame(Subscription::class, $row->aggregate_type);
        $this->assertSame((string) $sub->id, $row->aggregate_id);
        $this->assertSame(3, $row->payload['days_remaining']);
        $this->assertNull($row->published_at);

        Event::assertDispatched(BillingTrialWillEnd::class, function (BillingTrialWillEnd $e) use ($sub): bool {
            return $e->subscriptionId === (string) $sub->id && $e->daysRemaining === 3;
        });
    }

    #[Test]
    public function it_writes_nothing_when_subscription_is_not_owned(): void
    {
        Event::fake([BillingTrialWillEnd::class]);

        $webhookEvent = WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_trialwillend_test_unknown',
            'event_type' => 'customer.subscription.trial_will_end',
            'payload' => [
                'data' => [
                    'object' => [
                        'id' => 'sub_unknown_xxx',
                        'trial_end' => time() + 86400,
                    ],
                ],
            ],
        ]);

        $handler = $this->app->make(TrialWillEndHandler::class);
        $handler->handle($webhookEvent);

        $this->assertSame(0, BillingOutboxEvent::count());
        Event::assertNotDispatched(BillingTrialWillEnd::class);
    }
}
