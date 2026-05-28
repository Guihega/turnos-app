<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\GeneratesIdempotencyKeys;
use App\Billing\Contracts\BillingGatewayWriter;
use App\Enums\Billing\Gateway;
use App\Enums\Billing\PaymentMethodType;
use App\Events\Billing\PaymentMethodAttached;
use App\Exceptions\Billing\CustomerNotRegisteredInGatewayException;
use App\Models\Billing\Customer;
use App\Models\Billing\CustomerGatewayRef;
use App\Models\Billing\PaymentMethod;
use Illuminate\Support\Facades\DB;

/**
 * Attaches a tokenized PaymentMethod to a Customer in both the gateway
 * and the local DB.
 *
 * The frontend (Stripe Elements per ADR-018, or MercadoPago Brick in
 * Fase 4) confirms a SetupIntent with the cardholder-entered details
 * and produces a $paymentMethodId token. This Action takes that token,
 * attaches it to the gateway customer, optionally marks it as the
 * default, and mirrors the result locally in billing_payment_methods.
 *
 * Per ADR-016, this is a saga:
 *
 *   Step 1. Resolve the gateway_customer_id from CustomerGatewayRef.
 *           If missing, throw CustomerNotRegisteredInGatewayException.
 *
 *   Step 2. Lookup or mint an idempotency key. The hash includes the
 *           PM id + setAsDefault flag so retries with the same key
 *           but different defaultness are caught as conflicts at the
 *           gateway level (ADR-016 §3, idempotency surface).
 *
 *   Step 3. If a response_snapshot exists, fetch the existing local
 *           PaymentMethod and return it (transparent retry).
 *
 *   Step 4. Call the gateway. Failures propagate; nothing in DB.
 *
 *   Step 5. In one DB::transaction: upsert the local PaymentMethod
 *           row (unique on stripe_payment_method_id) and, if
 *           setAsDefault, clear is_default on the customer's other
 *           PMs and mark this one as default. Snapshot the response
 *           and link the key to the customer.
 *
 *   Step 6. Dispatch PaymentMethodAttached post-commit.
 *
 * Failure semantics:
 *
 *   - Gateway failure → re-thrown, no DB writes. Retry hits the
 *     gateway with the same key; if it had partially succeeded
 *     (e.g. attach succeeded but invoice_settings update failed),
 *     Stripe deduplicates the attach and finishes the default-update.
 *
 *   - DB failure after gateway success → snapshot is empty, so a retry
 *     re-runs steps 4-5. Stripe attach is idempotent; the local upsert
 *     converges.
 *
 * @see App\Actions\Billing\CreateSetupIntentAction
 * @see docs/adr/ADR-016-billing-write-contract-and-create-flows.md
 */
final class UpdatePaymentMethodAction
{
    use GeneratesIdempotencyKeys;

    public function __construct(
        private readonly BillingGatewayWriter $writer,
    ) {}

    public function execute(
        Customer $customer,
        string $paymentMethodId,
        bool $setAsDefault = true,
    ): PaymentMethod {
        $gateway = Gateway::Stripe;

        // Step 1: resolve the gateway_customer_id.
        /** @var CustomerGatewayRef|null $gatewayRef */
        $gatewayRef = $customer->gatewayRefs()
            ->where('gateway', $gateway->value)
            ->first();

        if ($gatewayRef === null) {
            throw new CustomerNotRegisteredInGatewayException(
                "Customer {$customer->id} has no {$gateway->value} gateway ref. Run CreateCustomerAction first.",
            );
        }

        // Step 2: find or mint the idempotency key. The hash includes
        // every input that meaningfully changes the gateway operation:
        // a retry with a different setAsDefault is a conflict, not a
        // reuse, and the gateway will reject it.
        $hashablePayload = [
            'customer_id' => $customer->id,
            'gateway_customer_id' => $gatewayRef->gateway_customer_id,
            'payment_method_id' => $paymentMethodId,
            'set_as_default' => $setAsDefault,
        ];

        $key = $this->findOrCreateIdempotencyKey(
            operation: 'attach_payment_method',
            gateway: $gateway,
            payload: $hashablePayload,
            customerId: $customer->id,
        );

        // Step 3: transparent retry if we already have a snapshot.
        if ($key->response_snapshot !== null) {
            $snapshot = $key->response_snapshot;
            $localId = is_string($snapshot['local_payment_method_id'] ?? null)
                ? $snapshot['local_payment_method_id']
                : null;

            if ($localId !== null) {
                /** @var PaymentMethod|null $existing */
                $existing = PaymentMethod::find($localId);
                if ($existing !== null) {
                    return $existing;
                }
                // Row was hard-deleted out-of-band; fall through to
                // re-run gateway + local insert with the same key.
            }
        }

        // Step 4: call the gateway.
        $gatewayPm = $this->writer->attachPaymentMethod(
            gatewayCustomerId: $gatewayRef->gateway_customer_id,
            paymentMethodId: $paymentMethodId,
            setAsDefault: $setAsDefault,
            idempotencyKey: $key->idempotency_key,
        );

        // Step 5: persist locally in one transaction.
        $paymentMethod = DB::transaction(function () use (
            $customer,
            $gatewayPm,
            $setAsDefault,
            $key,
        ): PaymentMethod {
            // If this PM becomes default, clear is_default on all
            // other PMs for this customer first. Single statement,
            // race-free under the surrounding transaction.
            if ($setAsDefault) {
                PaymentMethod::query()
                    ->where('customer_id', $customer->id)
                    ->where('stripe_payment_method_id', '!=', $gatewayPm->gatewayId)
                    ->update(['is_default' => false]);
            }

            // Upsert: if the same stripe_payment_method_id was attached
            // before (e.g. concurrent retry or repeat re-attach), update
            // the existing row instead of duplicating.
            /** @var PaymentMethod $paymentMethod */
            $paymentMethod = PaymentMethod::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'stripe_payment_method_id' => $gatewayPm->gatewayId,
                ],
                [
                    'type' => PaymentMethodType::from($gatewayPm->type),
                    'is_default' => $setAsDefault || $gatewayPm->isDefault,
                    'brand' => $gatewayPm->brand,
                    'last4' => $gatewayPm->last4,
                    'exp_month' => $gatewayPm->expMonth,
                    'exp_year' => $gatewayPm->expYear,
                    'metadata' => $gatewayPm->metadata !== [] ? $gatewayPm->metadata : null,
                ],
            );

            $this->snapshotResponse($key, [
                'local_payment_method_id' => $paymentMethod->id,
                'gateway_payment_method_id' => $gatewayPm->gatewayId,
                'brand' => $gatewayPm->brand,
                'last4' => $gatewayPm->last4,
                'is_default' => $paymentMethod->is_default,
            ]);

            $key->update(['customer_id' => $customer->id]);

            return $paymentMethod;
        });

        // Step 6: dispatch event post-commit so listeners see committed rows.
        DB::afterCommit(fn () => event(new PaymentMethodAttached(
            paymentMethod: $paymentMethod,
            wasSetAsDefault: $setAsDefault,
        )));

        return $paymentMethod;
    }
}
