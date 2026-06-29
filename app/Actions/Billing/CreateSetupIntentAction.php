<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Billing\Concerns\GeneratesIdempotencyKeys;
use App\Billing\Contracts\BillingGatewayWriter;
use App\Billing\DTOs\GatewaySetupIntent;
use App\Enums\Billing\Gateway;
use App\Exceptions\Billing\CustomerNotRegisteredInGatewayException;
use App\Models\Billing\Customer;
use App\Models\Billing\CustomerGatewayRef;

/**
 * Creates a gateway SetupIntent for an existing local Customer.
 *
 * The SetupIntent is the gateway primitive that lets the frontend
 * (Stripe Elements per ADR-018, or MercadoPago Brick in Fase 4)
 * collect a payment method without an immediate charge. The returned
 * DTO carries the $clientSecret that the frontend SDK consumes.
 *
 * Per ADR-016, this is a thin saga:
 *
 *   Step 1. Resolve the gateway_customer_id from CustomerGatewayRef.
 *           If the Customer has no Stripe ref, throw
 *           CustomerNotRegisteredInGatewayException — invoke
 *           CreateCustomerAction first.
 *
 *   Step 2. Lookup or mint an idempotency key for this request.
 *
 *   Step 3. If the key already has a cached response_snapshot,
 *           reconstruct the GatewaySetupIntent DTO from it and skip
 *           the gateway call (transparent retry). Stripe SetupIntents
 *           are immutable after creation; the client_secret remains
 *           valid until the SetupIntent is canceled or used, so
 *           reusing the cached one is safe.
 *
 *   Step 4. Otherwise, call the gateway, snapshot the response, and
 *           return the DTO.
 *
 * No local DB writes beyond the IdempotencyKey row maintained by the
 * trait. The SetupIntent is a transient gateway artifact; once the
 * frontend confirms a payment method, UpdatePaymentMethodAction
 * persists the resulting PaymentMethod locally.
 *
 * @see App\Actions\Billing\UpdatePaymentMethodAction
 * @see docs/adr/ADR-016-billing-write-contract-and-create-flows.md
 */
final class CreateSetupIntentAction
{
    use GeneratesIdempotencyKeys;

    public function __construct(
        private readonly BillingGatewayWriter $writer,
    ) {}

    public function execute(Customer $customer): GatewaySetupIntent
    {
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

        // Step 2: find or mint the idempotency key.
        // Payload includes the customer_id + gateway_customer_id so
        // distinct customers can never share a hash collision, and the
        // same customer requesting a SetupIntent twice in the TTL
        // window reuses the same gateway intent.
        $hashablePayload = [
            'customer_id' => $customer->id,
            'gateway_customer_id' => $gatewayRef->gateway_customer_id,
        ];

        $key = $this->findOrCreateIdempotencyKey(
            operation: 'create_setup_intent',
            gateway: $gateway,
            payload: $hashablePayload,
            customerId: $customer->id,
        );

        // Step 3: transparent retry if we already have a snapshot.
        if ($key->response_snapshot !== null) {
            $snapshot = $key->response_snapshot;

            return new GatewaySetupIntent(
                gatewayId: is_string($snapshot['gateway_id'] ?? null) ? $snapshot['gateway_id'] : '',
                clientSecret: is_string($snapshot['client_secret'] ?? null) ? $snapshot['client_secret'] : '',
                status: is_string($snapshot['status'] ?? null) ? $snapshot['status'] : 'unknown',
            );
        }

        // Step 4: call the gateway and snapshot the response.
        $setupIntent = $this->writer->createSetupIntent(
            $gatewayRef->gateway_customer_id,
            $key->idempotency_key,
        );

        $this->snapshotResponse($key, [
            'gateway_id' => $setupIntent->gatewayId,
            'client_secret' => $setupIntent->clientSecret,
            'status' => $setupIntent->status,
        ]);

        return $setupIntent;
    }
}
