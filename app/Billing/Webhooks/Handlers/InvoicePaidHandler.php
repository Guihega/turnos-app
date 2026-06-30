<?php

declare(strict_types=1);

namespace App\Billing\Webhooks\Handlers;

use App\Billing\Contracts\BillingWebhookHandler;
use App\Enums\Billing\InvoiceStatus;
use App\Models\Billing\Invoice;
use App\Models\Billing\Subscription;
use App\Models\Billing\WebhookEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Handler for Stripe event invoice.paid.
 *
 * Persists a successful invoice into billing_invoices so the tenant has
 * a local billing history. Stripe sends amounts already in the minor
 * unit, so they map directly to the *_cents columns.
 *
 * Behavior:
 *   1. Read data.object from the payload. If missing: log + return.
 *   2. Resolve the local Subscription via the invoice's subscription
 *      field. If not found (or the invoice is a one-off without a
 *      subscription): log + return — the invoice is not ours to record.
 *   3. upsert by stripe_invoice_id (UNIQUE) so retries / duplicate
 *      deliveries do not create a second row (idempotent per contract).
 *
 * Scope: header only. Invoice line items and Payment rows are handled
 * elsewhere; this handler records the invoice document itself.
 */
final class InvoicePaidHandler implements BillingWebhookHandler
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

        $stripeInvoiceId = isset($object['id']) && is_string($object['id'])
            ? $object['id']
            : null;
        if ($stripeInvoiceId === null) {
            Log::info('billing.webhook.invoice_paid.no_invoice_id', [
                'webhook_event_id' => $event->id,
            ]);

            return;
        }

        $stripeSubId = isset($object['subscription']) && is_string($object['subscription'])
            ? $object['subscription']
            : null;
        if ($stripeSubId === null) {
            Log::info('billing.webhook.invoice_paid.no_subscription', [
                'webhook_event_id' => $event->id,
                'stripe_invoice_id' => $stripeInvoiceId,
                'note' => 'Invoice not linked to a subscription (one-off charge).',
            ]);

            return;
        }

        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()
            ->where('stripe_subscription_id', $stripeSubId)
            ->first();
        if ($subscription === null) {
            Log::info('billing.webhook.invoice_paid.not_owned', [
                'webhook_event_id' => $event->id,
                'stripe_subscription_id' => $stripeSubId,
            ]);

            return;
        }

        $paidAt = isset($object['status_transitions']['paid_at'])
            && is_int($object['status_transitions']['paid_at'])
            ? Carbon::createFromTimestamp($object['status_transitions']['paid_at'])
            : Carbon::now();

        Invoice::query()->updateOrCreate(
            ['stripe_invoice_id' => $stripeInvoiceId],
            [
                'subscription_id' => $subscription->id,
                'customer_id' => $subscription->customer_id,
                'status' => InvoiceStatus::Paid,
                'invoice_number' => $this->stringOr($object, 'number', $stripeInvoiceId),
                'currency' => mb_strtoupper($this->stringOr($object, 'currency', 'MXN')),
                'subtotal_cents' => $this->intOr($object, 'subtotal', 0),
                'tax_cents' => $this->intOr($object, 'tax', 0),
                'total_cents' => $this->intOr($object, 'total', 0),
                'amount_paid_cents' => $this->intOr($object, 'amount_paid', 0),
                'amount_due_cents' => $this->intOr($object, 'amount_due', 0),
                'issued_at' => $paidAt,
                'paid_at' => $paidAt,
                'metadata' => ['webhook_event_id' => $event->id],
            ],
        );

        Log::info('billing.webhook.invoice_paid.persisted', [
            'webhook_event_id' => $event->id,
            'stripe_invoice_id' => $stripeInvoiceId,
            'subscription_id' => $subscription->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function stringOr(array $object, string $key, string $default): string
    {
        return isset($object[$key]) && is_string($object[$key]) && $object[$key] !== ''
            ? $object[$key]
            : $default;
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function intOr(array $object, string $key, int $default): int
    {
        return isset($object[$key]) && is_int($object[$key])
            ? $object[$key]
            : $default;
    }
}
