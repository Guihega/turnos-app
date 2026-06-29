<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Webhooks\Handlers;

use App\Billing\Webhooks\Handlers\InvoicePaymentFailedHandler;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Billing\Subscription;
use App\Models\Billing\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InvoicePaymentFailedHandlerTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(string $stripeSubId, string $invoiceId = 'in_TEST', int $attemptCount = 1): WebhookEvent
    {
        /** @var WebhookEvent $event */
        $event = WebhookEvent::create([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_'.uniqid(),
            'event_type' => 'invoice.payment_failed',
            'payload' => [
                'id' => 'evt_x',
                'type' => 'invoice.payment_failed',
                'data' => [
                    'object' => [
                        'id' => $invoiceId,
                        'subscription' => $stripeSubId,
                        'attempt_count' => $attemptCount,
                    ],
                ],
            ],
            'signature_header' => 't=1,v1=dummy',
        ]);

        return $event;
    }

    #[Test]
    public function transitions_active_subscription_to_past_due(): void
    {
        /** @var Subscription $sub */
        $sub = Subscription::factory()
            ->active()
            ->withStripeId('sub_paying_failed')
            ->create();

        $event = $this->makeEvent('sub_paying_failed');

        $handler = $this->app->make(InvoicePaymentFailedHandler::class);
        $handler->handle($event);

        $sub->refresh();
        $this->assertSame(SubscriptionStatus::PastDue, $sub->status);

        $this->assertDatabaseHas('billing_subscription_state_transitions', [
            'subscription_id' => $sub->id,
            'from_status' => 'active',
            'to_status' => 'past_due',
            'reason' => 'webhook_payment_failed',
        ]);
    }

    #[Test]
    public function idempotent_when_already_past_due(): void
    {
        /** @var Subscription $sub */
        $sub = Subscription::factory()
            ->pastDue()
            ->withStripeId('sub_already_past_due')
            ->create();
        $countBefore = $sub->stateTransitions()->count();

        $event = $this->makeEvent('sub_already_past_due');

        $handler = $this->app->make(InvoicePaymentFailedHandler::class);
        $handler->handle($event);

        $sub->refresh();
        $this->assertSame(SubscriptionStatus::PastDue, $sub->status);
        $this->assertSame($countBefore, $sub->stateTransitions()->count());
    }

    #[Test]
    public function noop_on_ineligible_state(): void
    {
        /** @var Subscription $sub */
        $sub = Subscription::factory()
            ->suspended()
            ->withStripeId('sub_suspended')
            ->create();

        $event = $this->makeEvent('sub_suspended');

        $handler = $this->app->make(InvoicePaymentFailedHandler::class);
        $handler->handle($event);

        $sub->refresh();
        // Still suspended; no transition.
        $this->assertSame(SubscriptionStatus::Suspended, $sub->status);
    }
}
