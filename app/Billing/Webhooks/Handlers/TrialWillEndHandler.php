<?php

declare(strict_types=1);

namespace App\Billing\Webhooks\Handlers;

use App\Billing\Contracts\BillingWebhookHandler;
use App\Events\Billing\BillingTrialWillEnd;
use App\Models\Billing\Subscription;
use App\Models\Billing\WebhookEvent;
use Illuminate\Support\Facades\Log;

/**
 * Handler for Stripe event customer.subscription.trial_will_end.
 *
 * Stripe fires this ~3 days before trial_end. We translate it into a
 * domain event (BillingTrialWillEnd) so downstream listeners (email,
 * push, in-app notification) can react. Those listeners are out of
 * scope for PR-G — they land in PR-H/I.
 *
 * Behavior:
 *   1. Resolve local Subscription by stripe_subscription_id.
 *      If not found: log info + return (not ours).
 *   2. Compute days remaining from trial_end timestamp (if present).
 *   3. Dispatch BillingTrialWillEnd.
 *
 * Idempotent: dispatching the same event twice produces no DB changes.
 * The listener layer is responsible for its own dedup (e.g. don't
 * send two emails). For now, with no listeners, repeated dispatches
 * are harmless.
 */
final class TrialWillEndHandler implements BillingWebhookHandler
{
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

        BillingTrialWillEnd::dispatch($subscription->id, $daysRemaining);
    }
}
