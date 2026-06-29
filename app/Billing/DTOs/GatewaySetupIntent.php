<?php

declare(strict_types=1);

namespace App\Billing\DTOs;

/**
 * Gateway-agnostic representation of a SetupIntent.
 *
 * A SetupIntent is a server-side handle that authorizes the frontend
 * to collect a payment method without immediately charging it. The
 * adapter (StripeBillingGateway et al.) creates the intent and returns
 * this DTO; the application surfaces $clientSecret to the frontend SDK
 * (Stripe.js, MercadoPago Brick, etc.) which uses it to confirm the
 * customer-entered card data and produce a reusable PaymentMethod token.
 *
 * Per ADR-015 §3, application code MUST consume this DTO and never
 * reach into the underlying SDK types.
 *
 * Per ADR-018, the $clientSecret travels to the gateway-specific
 * frontend component (e.g. Pages/Billing/Stripe/PaymentElement.jsx)
 * and is exchanged for a tokenized PaymentMethod that the application
 * then attaches via BillingGatewayWriter::attachPaymentMethod().
 */
final readonly class GatewaySetupIntent
{
    public function __construct(
        public string $gatewayId,
        public string $clientSecret,
        public string $status,
    ) {}
}
