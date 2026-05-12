<?php

declare(strict_types=1);

namespace App\Billing\Webhooks\Handlers;

use App\Actions\Billing\TransitionSubscriptionAction;
use App\Billing\Contracts\BillingWebhookHandler;
use App\Billing\Stripe\Mappers\StripeSubscriptionStatusMapper;
use App\Models\Billing\Subscription;
use App\Models\Billing\WebhookEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Handler for Stripe event customer.subscription.updated.
 *
 * Behavior:
 *   1. Resolves the local Subscription by stripe_subscription_id.
 *      If not found: logs and returns (not ours).
 *   2. Refreshes period dates (current_period_start/end, trial_ends_at)
 *      from Stripe's authoritative payload. These are always overwritten.
 *   3. Maps Stripe status to our enum.
 *      - If null (incomplete states): skip the state transition.
 *      - If same as current: no-op (TransitionSubscriptionAction
 *        already handles same-state as silent no-op).
 *      - Otherwise: invokes TransitionSubscriptionAction with
 *        reason='webhook_sync'.
 *
 * The handler is idempotent: re-running with the same payload produces
 * the same state. The state machine itself rejects forbidden transitions
 * (ADR-014), so a malicious or malformed event cannot put us in an
 * impossible state.
 */
final class SubscriptionUpdatedHandler implements BillingWebhookHandler
{
    public function __construct(
        private readonly TransitionSubscriptionAction $transition,
    ) {}

    public function handle(WebhookEvent $event): void
    {
        $object = $this->extractSubscriptionObject($event);
        if ($object === null) {
            return;
        }

        $stripeSubId = isset($object['id']) && is_string($object['id']) ? $object['id'] : null;
        if ($stripeSubId === null) {
            Log::warning('billing.webhook.subscription_updated.missing_id', [
                'webhook_event_id' => $event->id,
            ]);

            return;
        }

        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()
            ->where('stripe_subscription_id', $stripeSubId)
            ->first();
        if ($subscription === null) {
            Log::info('billing.webhook.subscription_updated.not_owned', [
                'webhook_event_id' => $event->id,
                'stripe_subscription_id' => $stripeSubId,
            ]);

            return;
        }

        // Always refresh period dates from the gateway truth.
        $updates = [];
        if (isset($object['current_period_start']) && is_int($object['current_period_start'])) {
            $updates['current_period_start'] = Carbon::createFromTimestamp($object['current_period_start']);
        }
        if (isset($object['current_period_end']) && is_int($object['current_period_end'])) {
            $updates['current_period_end'] = Carbon::createFromTimestamp($object['current_period_end']);
        }
        if (isset($object['trial_end']) && is_int($object['trial_end'])) {
            $updates['trial_ends_at'] = Carbon::createFromTimestamp($object['trial_end']);
        }
        if ($updates !== []) {
            $subscription->update($updates);
        }

        // Map status. Null means "Stripe state we don't model" — skip
        // the transition but keep the date updates we just applied.
        $rawStatus = isset($object['status']) && is_string($object['status']) ? $object['status'] : '';
        $newStatus = StripeSubscriptionStatusMapper::fromStripeString($rawStatus);
        if ($newStatus === null) {
            Log::info('billing.webhook.subscription_updated.unmapped_status', [
                'webhook_event_id' => $event->id,
                'stripe_subscription_id' => $stripeSubId,
                'raw_status' => $rawStatus,
            ]);

            return;
        }

        // TransitionSubscriptionAction handles same-state as a silent no-op
        // and rejects forbidden transitions with InvalidStateTransitionException.
        $this->transition->execute(
            subscription: $subscription,
            to: $newStatus,
            reason: 'webhook_sync',
            actor: 'stripe_webhook',
            metadata: [
                'webhook_event_id' => $event->id,
                'stripe_event_type' => $event->event_type,
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractSubscriptionObject(WebhookEvent $event): ?array
    {
        $payload = $event->payload;
        if (! is_array($payload)) {
            return null;
        }
        $data = $payload['data'] ?? null;
        if (! is_array($data)) {
            return null;
        }
        $object = $data['object'] ?? null;

        return is_array($object) ? $object : null;
    }
}
