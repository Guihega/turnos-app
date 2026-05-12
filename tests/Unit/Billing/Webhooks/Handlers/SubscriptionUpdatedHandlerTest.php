<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Webhooks\Handlers;

use App\Billing\Webhooks\Handlers\SubscriptionUpdatedHandler;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Billing\Subscription;
use App\Models\Billing\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SubscriptionUpdatedHandlerTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(string $stripeSubId, string $status, ?int $trialEnd = null): WebhookEvent
    {
        $object = [
            'id' => $stripeSubId,
            'status' => $status,
            'current_period_start' => 1_700_000_000,
            'current_period_end' => 1_702_592_000,
        ];
        if ($trialEnd !== null) {
            $object['trial_end'] = $trialEnd;
        }

        /** @var WebhookEvent $event */
        $event = WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_'.uniqid(),
            'event_type' => 'customer.subscription.updated',
            'payload' => [
                'id' => 'evt_x',
                'type' => 'customer.subscription.updated',
                'data' => ['object' => $object],
            ],
            'signature_header' => 't=1,v1=dummy',
        ]);

        return $event;
    }

    #[Test]
    public function transitions_status_and_refreshes_period_dates(): void
    {
        /** @var Subscription $sub */
        $sub = Subscription::factory()
            ->trialing()
            ->withStripeId('sub_test_001')
            ->create();
        $this->assertSame(SubscriptionStatus::Trialing, $sub->status);

        $event = $this->makeEvent('sub_test_001', 'active', trialEnd: 1_701_000_000);

        $handler = $this->app->make(SubscriptionUpdatedHandler::class);
        $handler->handle($event);

        $sub->refresh();
        $this->assertSame(SubscriptionStatus::Active, $sub->status);
        $this->assertSame('1700000000', (string) $sub->current_period_start?->getTimestamp());
        $this->assertSame('1702592000', (string) $sub->current_period_end?->getTimestamp());
        $this->assertSame('1701000000', (string) $sub->trial_ends_at?->getTimestamp());

        // A new row in the state transitions audit table.
        $this->assertDatabaseHas('billing_subscription_state_transitions', [
            'subscription_id' => $sub->id,
            'from_status' => 'trialing',
            'to_status' => 'active',
            'reason' => 'webhook_sync',
        ]);
    }

    #[Test]
    public function unmapped_status_skips_transition_but_updates_period_dates(): void
    {
        /** @var Subscription $sub */
        $sub = Subscription::factory()
            ->trialing()
            ->withStripeId('sub_test_incomplete')
            ->create();

        $event = $this->makeEvent('sub_test_incomplete', 'incomplete');

        $handler = $this->app->make(SubscriptionUpdatedHandler::class);
        $handler->handle($event);

        $sub->refresh();
        // Status unchanged because 'incomplete' is unmapped.
        $this->assertSame(SubscriptionStatus::Trialing, $sub->status);
        // But period dates ARE updated from the payload.
        $this->assertSame('1700000000', (string) $sub->current_period_start?->getTimestamp());
    }

    #[Test]
    public function noop_when_stripe_subscription_id_not_owned(): void
    {
        // No local subscription with this stripe_subscription_id.
        $event = $this->makeEvent('sub_someone_else', 'active');

        $handler = $this->app->make(SubscriptionUpdatedHandler::class);
        $handler->handle($event);

        // Nothing to assert beyond no exception.
        $this->assertSame(0, Subscription::count());
    }
}
