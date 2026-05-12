<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Webhooks\Handlers;

use App\Billing\Webhooks\Handlers\SubscriptionDeletedHandler;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Billing\Subscription;
use App\Models\Billing\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SubscriptionDeletedHandlerTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(string $stripeSubId, ?int $canceledAt = null): WebhookEvent
    {
        $object = ['id' => $stripeSubId, 'status' => 'canceled'];
        if ($canceledAt !== null) {
            $object['canceled_at'] = $canceledAt;
        }

        /** @var WebhookEvent $event */
        $event = WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_'.uniqid(),
            'event_type' => 'customer.subscription.deleted',
            'payload' => [
                'id' => 'evt_x',
                'type' => 'customer.subscription.deleted',
                'data' => ['object' => $object],
            ],
            'signature_header' => 't=1,v1=dummy',
        ]);

        return $event;
    }

    #[Test]
    public function transitions_active_subscription_to_canceled(): void
    {
        /** @var Subscription $sub */
        $sub = Subscription::factory()
            ->active()
            ->withStripeId('sub_test_delete')
            ->create();

        $event = $this->makeEvent('sub_test_delete', canceledAt: 1_700_500_000);

        $handler = $this->app->make(SubscriptionDeletedHandler::class);
        $handler->handle($event);

        $sub->refresh();
        $this->assertSame(SubscriptionStatus::Canceled, $sub->status);
        $this->assertNotNull($sub->canceled_at);
        $this->assertSame('1700500000', (string) $sub->canceled_at->getTimestamp());

        $this->assertDatabaseHas('billing_subscription_state_transitions', [
            'subscription_id' => $sub->id,
            'from_status' => 'active',
            'to_status' => 'canceled',
            'reason' => 'webhook_canceled',
        ]);
    }

    #[Test]
    public function idempotent_when_already_canceled(): void
    {
        /** @var Subscription $sub */
        $sub = Subscription::factory()
            ->canceled()
            ->withStripeId('sub_already_canceled')
            ->create();
        $countBefore = $sub->stateTransitions()->count();

        $event = $this->makeEvent('sub_already_canceled');

        $handler = $this->app->make(SubscriptionDeletedHandler::class);
        $handler->handle($event);

        $sub->refresh();
        $this->assertSame(SubscriptionStatus::Canceled, $sub->status);
        // No new transition row.
        $this->assertSame($countBefore, $sub->stateTransitions()->count());
    }
}
