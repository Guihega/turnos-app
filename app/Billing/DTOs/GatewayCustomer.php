<?php

declare(strict_types=1);

namespace App\Billing\DTOs;

/**
 * Gateway-agnostic representation of a customer record.
 *
 * Adapters (StripeBillingGateway et al.) construct these from their
 * SDK objects at the adapter boundary. Application code (controllers,
 * actions, listeners) MUST consume DTOs and never reach into the
 * underlying SDK types. See ADR-015 §3.
 */
final readonly class GatewayCustomer
{
    /**
     * @param  array<string, mixed>  $metadata  Free-form gateway metadata. May be empty.
     */
    public function __construct(
        public string $gatewayId,
        public ?string $email,
        public ?string $name,
        public ?string $defaultPaymentMethodId,
        public string $currency,
        public bool $deleted,
        public array $metadata,
    ) {}
}
