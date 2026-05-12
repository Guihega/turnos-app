<?php

declare(strict_types=1);

namespace App\Billing\Webhooks\Handlers;

use App\Actions\Billing\TransitionSubscriptionAction;
use App\Billing\Contracts\BillingWebhookHandler;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Billing\Subscription;
use App\Models\Billing\WebhookEvent;
use Illuminate\Support\Facades\Log;

/**
 * Handler for Stripe event invoice.payment_failed.
 *
 * When a recurring invoice fails to charge, Stripe transitions the
 * subscription to past_due on its end. We mirror that locally by
 * invoking TransitionSubscriptionAction.
 *
 * Behavior:
 *   1. Resolve local Subscription via the invoice's subscription field.
 *      If not found: log + return.
 *   2. If already PastDue: idempotent no-op.
 *   3. If in a non-eligible state (Suspended, Canceled, Pilot):
 *      log warning + no-op. The ALLOWED matrix would reject these
 *      transitions anyway.
 *   4. Otherwise (Active, Trialing, Paused): transition to PastDue
 *      with reason='webhook_payment_failed' and the failed invoice
 *      metadata.
 *
 * PR-I (dunning flow) is what acts on the PastDue state — sends
 * retry attempts, eventually transitions to Suspended. This handler
 * only triggers entry into dunning.
 */
final class InvoicePaymentFailedHandler implements BillingWebhookHandler
{
    /** Statuses from which the dunning entry transition is valid. */
    private const ELIGIBLE_FROM = [
        SubscriptionStatus::Active,
        SubscriptionStatus::Trialing,
        SubscriptionStatus::Paused,
    ];

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

        $stripeSubId = isset($object['subscription']) && is_string($object['subscription'])
            ? $object['subscription']
            : null;
        if ($stripeSubId === null) {
            Log::info('billing.webhook.invoice_payment_failed.no_subscription', [
                'webhook_event_id' => $event->id,
                'note' => 'Invoice not linked to a subscription (one-off charge).',
            ]);

            return;
        }

        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()
            ->where('stripe_subscription_id', $stripeSubId)
            ->first();
        if ($subscription === null) {
            Log::info('billing.webhook.invoice_payment_failed.not_owned', [
                'webhook_event_id' => $event->id,
                'stripe_subscription_id' => $stripeSubId,
            ]);

            return;
        }

        if ($subscription->status === SubscriptionStatus::PastDue) {
            return; // idempotent
        }

        if (! in_array($subscription->status, self::ELIGIBLE_FROM, strict: true)) {
            Log::warning('billing.webhook.invoice_payment_failed.ineligible_state', [
                'webhook_event_id' => $event->id,
                'subscription_id' => $subscription->id,
                'current_status' => $subscription->status->value,
            ]);

            return;
        }

        $invoiceId = isset($object['id']) && is_string($object['id']) ? $object['id'] : null;
        $attemptCount = isset($object['attempt_count']) && is_int($object['attempt_count'])
            ? $object['attempt_count']
            : null;

        $this->transition->execute(
            subscription: $subscription,
            to: SubscriptionStatus::PastDue,
            reason: 'webhook_payment_failed',
            actor: 'stripe_webhook',
            metadata: [
                'webhook_event_id' => $event->id,
                'stripe_invoice_id' => $invoiceId,
                'stripe_attempt_count' => $attemptCount,
            ],
        );
    }
}
