<?php

declare(strict_types=1);

namespace App\Billing\Webhooks\Handlers;

use App\Actions\Billing\TransitionSubscriptionAction;
use App\Billing\Contracts\BillingWebhookHandler;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Billing\Subscription;
use App\Models\Billing\WebhookEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Handler for Stripe event customer.subscription.deleted.
 *
 * In Stripe, "deleted" means the subscription reached the end of its
 * lifecycle (cancelled and the cancel_at_period_end has elapsed, or
 * cancelled immediately). Our domain represents this as Canceled
 * (terminal).
 *
 * Behavior:
 *   1. Resolve local Subscription. If not found or already Canceled:
 *      log and return (idempotent).
 *   2. Transition to Canceled with reason='webhook_canceled'.
 *      ADR-014's ALLOWED matrix has 'canceled' reachable from every
 *      non-terminal status, so this never throws InvalidStateTransition.
 *   3. Stamp canceled_at from the Stripe payload if present, otherwise now.
 */
final class SubscriptionDeletedHandler implements BillingWebhookHandler
{
    public function __construct(
        private readonly TransitionSubscriptionAction $transition,
    ) {}

    public function handle(WebhookEvent $event): void
    {
        $payload = $event->payload;
        if (! is_array($payload)) {
            return;
        }
        $object = $payload['data']['object'] ?? null;
        if (! is_array($object)) {
            return;
        }

        $stripeSubId = isset($object['id']) && is_string($object['id']) ? $object['id'] : null;
        if ($stripeSubId === null) {
            return;
        }

        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()
            ->where('stripe_subscription_id', $stripeSubId)
            ->first();
        if ($subscription === null) {
            Log::info('billing.webhook.subscription_deleted.not_owned', [
                'webhook_event_id' => $event->id,
                'stripe_subscription_id' => $stripeSubId,
            ]);

            return;
        }

        if ($subscription->status === SubscriptionStatus::Canceled) {
            return; // already canceled, idempotent
        }

        $canceledAt = isset($object['canceled_at']) && is_int($object['canceled_at'])
            ? Carbon::createFromTimestamp($object['canceled_at'])
            : Carbon::now();

        $subscription->update(['canceled_at' => $canceledAt]);

        $this->transition->execute(
            subscription: $subscription,
            to: SubscriptionStatus::Canceled,
            reason: 'webhook_canceled',
            actor: 'stripe_webhook',
            metadata: [
                'webhook_event_id' => $event->id,
                'stripe_event_type' => $event->event_type,
            ],
        );
    }
}
