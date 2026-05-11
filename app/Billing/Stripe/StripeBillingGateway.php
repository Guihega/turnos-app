<?php

declare(strict_types=1);

namespace App\Billing\Stripe;

use App\Billing\Contracts\BillingGateway;
use App\Billing\Contracts\BillingGatewayWriter;
use App\Billing\DTOs\CreateCustomerInput;
use App\Billing\DTOs\CreateSubscriptionInput;
use App\Billing\DTOs\GatewayCustomer;
use App\Billing\DTOs\GatewayInvoice;
use App\Billing\DTOs\GatewayPaymentMethod;
use App\Billing\DTOs\GatewaySubscription;
use App\Billing\Stripe\Concerns\HandlesStripeExceptions;
use App\Enums\Billing\SubscriptionStatus;
use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\PaymentMethod;
use Stripe\StripeClient;
use Stripe\Subscription;
use Stripe\Webhook;

/**
 * Stripe implementation of BillingGateway. PR-D scope: READ-ONLY.
 *
 * Each public method is one of:
 *   1. retrieve* — pull a resource by id and map it to a DTO,
 *   2. list*     — pull a collection and return DTOs,
 *   3. verifyWebhookSignature — validate + decode a webhook payload.
 *
 * Write methods (createCustomer, createSubscription) implement
 * BillingGatewayWriter (PR-E). Additional writes (cancelSubscription,
 * updateCustomer, attachPaymentMethod, ...) land in later PRs.
 *
 * Exception translation: every public method routes its body through
 * translateStripeExceptions() so the SDK's exception types never leak.
 * See ADR-015 §4.
 */
final class StripeBillingGateway implements BillingGateway, BillingGatewayWriter
{
    use HandlesStripeExceptions;

    public function __construct(
        private readonly StripeClient $client,
        private readonly Repository $config,
    ) {}

    public function retrieveCustomer(string $gatewayCustomerId): GatewayCustomer
    {
        return $this->translateStripeExceptions(function () use ($gatewayCustomerId): GatewayCustomer {
            /** @var Customer $customer */
            $customer = $this->client->customers->retrieve($gatewayCustomerId);

            return $this->mapCustomer($customer);
        });
    }

    public function retrieveSubscription(string $gatewaySubscriptionId): GatewaySubscription
    {
        return $this->translateStripeExceptions(function () use ($gatewaySubscriptionId): GatewaySubscription {
            /** @var Subscription $subscription */
            $subscription = $this->client->subscriptions->retrieve($gatewaySubscriptionId);

            return $this->mapSubscription($subscription);
        });
    }

    public function retrieveInvoice(string $gatewayInvoiceId): GatewayInvoice
    {
        return $this->translateStripeExceptions(function () use ($gatewayInvoiceId): GatewayInvoice {
            /** @var Invoice $invoice */
            $invoice = $this->client->invoices->retrieve($gatewayInvoiceId);

            return $this->mapInvoice($invoice);
        });
    }

    /**
     * @return list<GatewayPaymentMethod>
     */
    public function listPaymentMethods(string $gatewayCustomerId): array
    {
        return $this->translateStripeExceptions(function () use ($gatewayCustomerId): array {
            // Resolve the customer's default PM id once, so we can flag
            // it on every returned DTO without N round-trips.
            /** @var Customer $customer */
            $customer = $this->client->customers->retrieve($gatewayCustomerId);
            $defaultPmId = $this->extractDefaultPaymentMethodId($customer);

            // PR-D fetches card-type PMs by default. PR-E will broaden
            // this when adding non-card support (OXXO, bank transfers).
            $page = $this->client->paymentMethods->all([
                'customer' => $gatewayCustomerId,
                'type' => 'card',
                'limit' => 100,
            ]);

            $methods = [];
            foreach ($page->data as $pm) {
                /** @var PaymentMethod $pm */
                $methods[] = $this->mapPaymentMethod($pm, $defaultPmId);
            }

            return $methods;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): array
    {
        return $this->translateStripeExceptions(function () use ($payload, $signatureHeader): array {
            $secret = $this->config->get('billing.gateways.stripe.webhook_secret');
            if (! is_string($secret) || $secret === '') {
                throw new \RuntimeException(
                    'billing.gateways.stripe.webhook_secret is missing. Check STRIPE_MODE and the matching STRIPE_*_WEBHOOK_SECRET env var.'
                );
            }

            $event = Webhook::constructEvent($payload, $signatureHeader, $secret);

            // Webhook::constructEvent returns a \Stripe\Event. We expose
            // the verified payload as a plain associative array so PR-F's
            // handler doesn't need to know about Stripe types.
            /** @var array<string, mixed> $array */
            $array = $event->toArray();

            return $array;
        });
    }

    // ---------------------------------------------------------------
    // Mapping helpers
    // ---------------------------------------------------------------

    private function mapCustomer(Customer $customer): GatewayCustomer
    {
        return new GatewayCustomer(
            gatewayId: $customer->id,
            email: is_string($customer->email) ? $customer->email : null,
            name: is_string($customer->name) ? $customer->name : null,
            defaultPaymentMethodId: $this->extractDefaultPaymentMethodId($customer),
            currency: is_string($customer->currency) ? $customer->currency : '',
            deleted: (bool) ($customer->deleted ?? false),
            metadata: $this->metadataToArray($customer->metadata ?? null),
        );
    }

    private function mapSubscription(Subscription $subscription): GatewaySubscription
    {
        $items = [];
        foreach ($subscription->items->data ?? [] as $item) {
            $items[] = [
                'price_id' => is_string($item->price->id ?? null) ? $item->price->id : '',
                'quantity' => (int) ($item->quantity ?? 1),
            ];
        }

        return new GatewaySubscription(
            gatewayId: $subscription->id,
            gatewayCustomerId: is_string($subscription->customer) ? $subscription->customer : '',
            status: $this->mapStripeStatus(is_string($subscription->status) ? $subscription->status : ''),
            rawStatus: is_string($subscription->status) ? $subscription->status : '',
            items: $items,
            currentPeriodStart: $this->timestampToDateTime($subscription->current_period_start ?? null),
            currentPeriodEnd: $this->timestampToDateTime($subscription->current_period_end ?? null),
            trialEnd: $this->timestampToDateTime($subscription->trial_end ?? null),
            cancelAt: $this->timestampToDateTime($subscription->cancel_at ?? null),
            canceledAt: $this->timestampToDateTime($subscription->canceled_at ?? null),
            cancelAtPeriodEnd: (bool) ($subscription->cancel_at_period_end ?? false),
            metadata: $this->metadataToArray($subscription->metadata ?? null),
        );
    }

    private function mapInvoice(Invoice $invoice): GatewayInvoice
    {
        return new GatewayInvoice(
            gatewayId: $invoice->id,
            gatewayCustomerId: is_string($invoice->customer) ? $invoice->customer : '',
            gatewaySubscriptionId: is_string($invoice->subscription ?? null) ? $invoice->subscription : null,
            rawStatus: is_string($invoice->status) ? $invoice->status : '',
            currency: is_string($invoice->currency) ? $invoice->currency : '',
            amountDue: (int) ($invoice->amount_due ?? 0),
            amountPaid: (int) ($invoice->amount_paid ?? 0),
            amountRemaining: (int) ($invoice->amount_remaining ?? 0),
            hostedInvoiceUrl: is_string($invoice->hosted_invoice_url ?? null) ? $invoice->hosted_invoice_url : null,
            created: $this->timestampToDateTime($invoice->created ?? null),
            dueDate: $this->timestampToDateTime($invoice->due_date ?? null),
            paidAt: $this->timestampToDateTime($invoice->status_transitions->paid_at ?? null),
            metadata: $this->metadataToArray($invoice->metadata ?? null),
        );
    }

    private function mapPaymentMethod(PaymentMethod $pm, ?string $defaultPmId): GatewayPaymentMethod
    {
        $card = $pm->card ?? null;

        return new GatewayPaymentMethod(
            gatewayId: $pm->id,
            gatewayCustomerId: is_string($pm->customer ?? null) ? $pm->customer : '',
            type: is_string($pm->type) ? $pm->type : 'unknown',
            brand: is_string($card->brand ?? null) ? $card->brand : null,
            last4: is_string($card->last4 ?? null) ? $card->last4 : null,
            expMonth: isset($card->exp_month) ? (int) $card->exp_month : null,
            expYear: isset($card->exp_year) ? (int) $card->exp_year : null,
            isDefault: $defaultPmId !== null && $pm->id === $defaultPmId,
            metadata: $this->metadataToArray($pm->metadata ?? null),
        );
    }

    /**
     * Map Stripe's subscription.status to SubscriptionStatus::value, or
     * null if the status doesn't have a domain equivalent.
     *
     * Stripe states: incomplete, incomplete_expired, trialing, active,
     *                past_due, canceled, unpaid, paused.
     *
     * Domain has: pilot, trialing, active, past_due, suspended, paused, canceled.
     *
     * 'pilot' is exclusively a domain concept (free 90-day trial without
     * payment method); Stripe never reports it. The reverse-mapping for
     * pilot subs happens at creation time, not here.
     */
    private function mapStripeStatus(string $stripeStatus): ?string
    {
        return match ($stripeStatus) {
            'trialing' => SubscriptionStatus::Trialing->value,
            'active' => SubscriptionStatus::Active->value,
            'past_due' => SubscriptionStatus::PastDue->value,
            'paused' => SubscriptionStatus::Paused->value,
            'canceled' => SubscriptionStatus::Canceled->value,
            // Stripe's 'unpaid' is the closest analog of our 'suspended'
            // (post-dunning, no access). The webhook handler in PR-G will
            // translate this when it triggers a state transition.
            'unpaid' => SubscriptionStatus::Suspended->value,
            // 'incomplete' and 'incomplete_expired' are Stripe states with
            // no domain equivalent. Caller inspects rawStatus.
            default => null,
        };
    }

    private function extractDefaultPaymentMethodId(Customer $customer): ?string
    {
        $invoiceSettings = $customer->invoice_settings ?? null;
        if ($invoiceSettings === null) {
            return null;
        }

        $defaultPm = $invoiceSettings->default_payment_method ?? null;
        if (is_string($defaultPm)) {
            return $defaultPm;
        }
        if (is_object($defaultPm) && isset($defaultPm->id) && is_string($defaultPm->id)) {
            return $defaultPm->id;
        }

        return null;
    }

    // ---------------------------------------------------------------
    // Writer contract (BillingGatewayWriter)
    // ---------------------------------------------------------------

    public function createCustomer(CreateCustomerInput $input, string $idempotencyKey): GatewayCustomer
    {
        return $this->translateStripeExceptions(function () use ($input, $idempotencyKey): GatewayCustomer {
            $payload = [
                'email' => $input->email,
                'metadata' => $input->metadata,
            ];
            if ($input->name !== null) {
                $payload['name'] = $input->name;
            }

            // TODO(ADR-016): map $input->taxId via tax_ids->create() in a
            // follow-up. Same for billing_address (Stripe expects a nested
            // object with line1/line2/city/state/postal_code/country).

            /** @var Customer $customer */
            $customer = $this->client->customers->create(
                $payload,
                ['idempotency_key' => $idempotencyKey],
            );

            return $this->mapCustomer($customer);
        });
    }

    public function createSubscription(CreateSubscriptionInput $input, string $idempotencyKey): GatewaySubscription
    {
        return $this->translateStripeExceptions(function () use ($input, $idempotencyKey): GatewaySubscription {
            $payload = [
                'customer' => $input->gatewayCustomerId,
                'items' => [
                    ['price' => $input->gatewayPriceId],
                ],
                // ADR-016 §4: create subscription WITHOUT requiring a PM.
                // Stripe will mark the subscription as 'incomplete' until
                // payment is collected, but the trial flow makes that
                // collection deferred (see trial_will_end webhook in PR-F).
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => [
                    'save_default_payment_method' => 'on_subscription',
                ],
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => $input->metadata,
            ];
            if ($input->trialDays > 0) {
                $payload['trial_period_days'] = $input->trialDays;
            }

            /** @var Subscription $subscription */
            $subscription = $this->client->subscriptions->create(
                $payload,
                ['idempotency_key' => $idempotencyKey],
            );

            return $this->mapSubscription($subscription);
        });
    }

    // ---------------------------------------------------------------
    // Mapping helpers (private)
    // ---------------------------------------------------------------

    private function timestampToDateTime(mixed $timestamp): ?DateTimeImmutable
    {
        if ($timestamp === null) {
            return null;
        }
        if (! is_int($timestamp) && ! (is_string($timestamp) && ctype_digit($timestamp))) {
            return null;
        }

        return (new DateTimeImmutable)->setTimestamp((int) $timestamp);
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataToArray(mixed $metadata): array
    {
        if ($metadata === null) {
            return [];
        }
        if (is_array($metadata)) {
            /** @var array<string, mixed> $metadata */
            return $metadata;
        }
        if (is_object($metadata) && method_exists($metadata, 'toArray')) {
            /** @var array<string, mixed> $array */
            $array = $metadata->toArray();

            return $array;
        }

        return [];
    }
}
