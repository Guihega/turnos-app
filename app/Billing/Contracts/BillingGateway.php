<?php

declare(strict_types=1);

namespace App\Billing\Contracts;

use App\Billing\DTOs\GatewayCustomer;
use App\Billing\DTOs\GatewayInvoice;
use App\Billing\DTOs\GatewayPaymentMethod;
use App\Billing\DTOs\GatewaySubscription;
use App\Billing\Exceptions\GatewayAuthenticationException;
use App\Billing\Exceptions\GatewayException;
use App\Billing\Exceptions\GatewayNotFoundException;
use App\Billing\Exceptions\GatewaySignatureException;
use App\Billing\Stripe\StripeBillingGateway;

/**
 * The boundary between application code and any specific payment gateway.
 *
 * PR-D scope: READ-ONLY. Methods here either fetch state from the
 * gateway, or verify a webhook signature. Write operations (create,
 * update, cancel) belong to a complementary contract introduced in
 * PR-E. Keeping the read surface minimal lets PR-D ship without a
 * Stripe account, against mocks only.
 *
 * All implementations MUST translate SDK-specific exceptions into the
 * App\Billing\Exceptions\* hierarchy at the adapter boundary, so that
 * callers can catch domain exceptions without knowing which gateway
 * is wired in. See ADR-015.
 *
 * @see StripeBillingGateway
 */
interface BillingGateway
{
    /**
     * @throws GatewayNotFoundException If the customer does not exist.
     * @throws GatewayAuthenticationException If credentials are invalid.
     * @throws GatewayException For any other gateway error.
     */
    public function retrieveCustomer(string $gatewayCustomerId): GatewayCustomer;

    /**
     * @throws GatewayNotFoundException
     * @throws GatewayAuthenticationException
     * @throws GatewayException
     */
    public function retrieveSubscription(string $gatewaySubscriptionId): GatewaySubscription;

    /**
     * @throws GatewayNotFoundException
     * @throws GatewayAuthenticationException
     * @throws GatewayException
     */
    public function retrieveInvoice(string $gatewayInvoiceId): GatewayInvoice;

    /**
     * List the customer's payment methods. Returns an empty list if the
     * customer has none; throws if the customer itself does not exist.
     *
     * @return list<GatewayPaymentMethod>
     *
     * @throws GatewayNotFoundException
     * @throws GatewayAuthenticationException
     * @throws GatewayException
     */
    public function listPaymentMethods(string $gatewayCustomerId): array;

    /**
     * Verify the cryptographic signature on a webhook payload, returning
     * the decoded event payload as an array if (and only if) the
     * signature matches the configured webhook secret.
     *
     * Per ADR-012, callers MUST invoke this BEFORE persisting any row
     * in billing_webhook_events.
     *
     * @return array<string, mixed> The verified event body.
     *
     * @throws GatewaySignatureException If signature is missing or invalid.
     * @throws GatewayException For malformed payload or other errors.
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): array;
}
