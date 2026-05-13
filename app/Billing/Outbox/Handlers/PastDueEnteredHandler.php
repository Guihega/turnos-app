<?php

declare(strict_types=1);

namespace App\Billing\Outbox\Handlers;

use App\Contracts\Billing\OutboxEventHandler;
use App\Mail\Billing\BillingPastDueNotification;
use App\Models\Billing\BillingOutboxEvent;
use App\Models\Billing\Subscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Outbox handler for `subscription.state-changed` events with to=past_due.
 *
 * Sends the past-due notification email to the customer's billing_email.
 * This is the entry point of the dunning UX: the tenant sees their
 * subscription is in payment recovery and learns what to do.
 *
 * Stripe Smart Retries (configured in Stripe Dashboard) handles the
 * actual retry schedule on the gateway side; we just react to the
 * resulting webhooks. See ADR-013 Implementation refinements (PR-H)
 * and ADR-NNN dunning (PR-I).
 *
 * Idempotent: the email is queued, not sent inline. If the same outbox
 * row is delivered twice (at-least-once semantics), two emails ship.
 * Acceptable: past_due is rare per customer, and dedup at the mail
 * provider level is the right place if we ever need it.
 *
 * Events whose payload.to is NOT 'past_due' are ignored — same handler
 * could be registered against state-changed events generally, but only
 * acts on the past_due transition.
 */
final class PastDueEnteredHandler implements OutboxEventHandler
{
    public function handle(BillingOutboxEvent $event): void
    {
        $to = $event->payload['to'] ?? null;
        if ($to !== 'past_due') {
            return;
        }

        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()
            ->with('customer', 'plan')
            ->find($event->aggregate_id);

        if ($subscription === null) {
            Log::warning('billing.dunning.past_due.subscription_not_found', [
                'outbox_event_id' => $event->id,
                'subscription_id' => $event->aggregate_id,
            ]);

            return;
        }

        $recipient = $subscription->customer?->billing_email;
        if ($recipient === null || $recipient === '') {
            Log::warning('billing.dunning.past_due.no_billing_email', [
                'outbox_event_id' => $event->id,
                'subscription_id' => $subscription->id,
                'customer_id' => $subscription->customer_id,
            ]);

            return;
        }

        Mail::to($recipient)->queue(new BillingPastDueNotification($subscription));
    }
}
