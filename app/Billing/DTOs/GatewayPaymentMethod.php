<?php

declare(strict_types=1);

namespace App\Billing\DTOs;

/**
 * Gateway-agnostic representation of a payment method (typically a card).
 *
 * Sensitive details (PAN, full expiry, CVC) are NEVER carried here.
 * Only display-safe last4 + brand + expiry month/year, which gateways
 * already redact for tokenized cards.
 */
final readonly class GatewayPaymentMethod
{
    /**
     * @param  string  $type  e.g. 'card', 'oxxo', 'bank_transfer'. Domain
     *                        code maps this to PaymentMethodType enum (PR-A).
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $gatewayId,
        public string $gatewayCustomerId,
        public string $type,
        public ?string $brand,
        public ?string $last4,
        public ?int $expMonth,
        public ?int $expYear,
        public bool $isDefault,
        public array $metadata,
    ) {}
}
