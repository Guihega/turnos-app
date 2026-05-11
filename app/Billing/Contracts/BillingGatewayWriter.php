<?php

declare(strict_types=1);

namespace App\Billing\Contracts;

use App\Billing\DTOs\CreateCustomerInput;
use App\Billing\DTOs\CreateSubscriptionInput;
use App\Billing\DTOs\GatewayCustomer;
use App\Billing\DTOs\GatewaySubscription;
use App\Billing\Exceptions\GatewayException;

/**
 * Write contract for billing gateways.
 *
 * Complementary to BillingGateway (read-only). Per ADR-015 we split
 * read and write to allow components that only read (webhook handlers,
 * reconciliation jobs) to depend on a narrower surface, and to keep
 * the write-side blast radius isolated during refactors.
 *
 * Per ADR-016:
 *
 * - All write methods MUST be idempotent at the gateway protocol level.
 *   The Action layer generates a ULID idempotency key per logical
 *   operation and passes it as $idempotencyKey. The adapter MUST forward
 *   that key to the underlying gateway in whatever channel the gateway
 *   exposes (Stripe-Idempotency-Key header, etc).
 *
 * - All write methods return the gateway's view of the resulting
 *   resource as a domain DTO. Domain code does NOT see SDK objects.
 *
 * - On failure, methods throw a subclass of GatewayException. Validation
 *   failures (the gateway rejected the input shape) throw
 *   GatewayValidationException. Idempotency conflicts (same key but
 *   different payload than the original call) throw
 *   GatewayIdempotencyConflictException.
 *
 * @see docs/adr/ADR-016-billing-write-contract-and-create-flows.md
 */
interface BillingGatewayWriter
{
    /**
     * Create a customer in the gateway.
     *
     * Idempotent: calling twice with the same $idempotencyKey and the
     * same input returns the same customer without creating a duplicate.
     *
     * @throws GatewayException
     */
    public function createCustomer(CreateCustomerInput $input, string $idempotencyKey): GatewayCustomer;

    /**
     * Create a subscription for an existing gateway customer.
     *
     * Per ADR-016, subscriptions start in 'trialing' by default. The
     * adapter MUST pass payment_behavior=default_incomplete so the
     * subscription is created without requiring a payment method.
     *
     * @throws GatewayException
     */
    public function createSubscription(CreateSubscriptionInput $input, string $idempotencyKey): GatewaySubscription;
}
