<?php

declare(strict_types=1);

namespace App\Billing\Outbox\Handlers;

use App\Contracts\Billing\OutboxEventHandler;
use App\Mail\Billing\BillingSubscriptionSuspendedNotification;
use App\Models\Billing\BillingOutboxEvent;
use App\Models\Billing\Subscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Outbox handler for `subscription.state-changed` events with to=suspended.
 *
 * Sends the suspension notification email. This fires when dunning has
 * exhausted (Stripe Smart Retries gave up) and the subscription
 * transitions past_due → suspended via SubscriptionUpdatedHandler
 * mapping Stripe's `unpaid` status.
 *
 * Same idempotency notes as PastDueEnteredHandler apply.
 */
final class SubscriptionSuspendedHandler implements OutboxEventHandler
{
    public function handle(BillingOutboxEvent $event): void
    {
        $to = $event->payload['to'] ?? null;
        if ($to !== 'suspended') {
            return;
        }

        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()
            ->with('customer', 'plan')
            ->find($event->aggregate_id);

        if ($subscription === null) {
            Log::warning('billing.dunning.suspended.subscription_not_found', [
                'outbox_event_id' => $event->id,
                'subscription_id' => $event->aggregate_id,
            ]);

            return;
        }

        $recipient = $subscription->customer?->billing_email;
        if ($recipient === null || $recipient === '') {
            Log::warning('billing.dunning.suspended.no_billing_email', [
                'outbox_event_id' => $event->id,
                'subscription_id' => $subscription->id,
                'customer_id' => $subscription->customer_id,
            ]);

            return;
        }

        Mail::to($recipient)->queue(new BillingSubscriptionSuspendedNotification($subscription));
    }
}
