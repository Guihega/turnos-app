<?php

declare(strict_types=1);

namespace App\Billing\Webhooks\Handlers;

use App\Billing\Contracts\BillingWebhookHandler;
use App\Events\Billing\BillingTrialWillEnd;
use App\Models\Billing\Subscription;
use App\Models\Billing\WebhookEvent;
use App\Services\Billing\OutboxEventWriter;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handler for Stripe event customer.subscription.trial_will_end.
 *
 * Stripe fires this ~3 days before trial_end. We persist a
 * BillingTrialWillEnd domain event to the outbox (transactional)
 * and dispatch it in-process after commit so downstream listeners
 * (email, push, in-app notification, PR-I) can react.
 *
 * Behavior:
 *   1. Resolve local Subscription by stripe_subscription_id.
 *      If not found: log info + return (not ours).
 *   2. Compute days remaining from trial_end timestamp (if present).
 *   3. Inside a DB::transaction: persist to outbox.
 *   4. After commit: dispatch in-process.
 *
 * Idempotency at the listener layer is the listener's responsibility
 * (e.g. don't send two emails). The outbox row itself is unique per
 * webhook delivery; Stripe may retry, which is handled upstream by
 * the webhook deduplication in WebhookEvent.
 */
final class TrialWillEndHandler implements BillingWebhookHandler
{
    public function __construct(
        private readonly OutboxEventWriter $outboxWriter,
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
            Log::info('billing.webhook.trial_will_end.not_owned', [
                'webhook_event_id' => $event->id,
                'stripe_subscription_id' => $stripeSubId,
            ]);

            return;
        }

        $daysRemaining = null;
        if (isset($object['trial_end']) && is_int($object['trial_end'])) {
            $secondsUntil = $object['trial_end'] - time();
            $daysRemaining = $secondsUntil > 0 ? (int) ceil($secondsUntil / 86400) : 0;
        }

        $domainEvent = new BillingTrialWillEnd(
            subscriptionId: (string) $subscription->id,
            daysRemaining: $daysRemaining,
            occurredAt: new DateTimeImmutable,
        );

        DB::transaction(function () use ($domainEvent): void {
            $this->outboxWriter->write($domainEvent);
        });

        DB::afterCommit(function () use ($domainEvent): void {
            event($domainEvent);
        });
    }
}
