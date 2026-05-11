<?php

declare(strict_types=1);

namespace App\Billing\DTOs;

/**
 * Input DTO for BillingGatewayWriter::createCustomer().
 *
 * Gateway-agnostic shape. Adapters map these fields to their SDK's
 * native customer-creation payload (e.g. Stripe Customer::create()).
 *
 * The $metadata array is forwarded verbatim to the gateway. Per
 * ADR-016, the Action layer MUST include 'tenant_id' and
 * 'app_customer_id' keys so that webhook handlers can reverse-resolve
 * gateway events to our domain entities without an extra DB lookup.
 */
final readonly class CreateCustomerInput
{
    /**
     * @param  string  $email  Billing contact email (already validated, not encrypted here).
     * @param  string|null  $name  Billing name (legal_name preferred when available).
     * @param  string  $country  ISO 3166-1 alpha-2 (e.g. 'MX').
     * @param  string|null  $taxId  Country-specific tax ID (RFC, RUT, NIT...). Plain text; encryption happens at storage time.
     * @param  array<string, mixed>  $metadata  Forwarded to the gateway. Must include tenant_id and app_customer_id (see ADR-016).
     */
    public function __construct(
        public string $email,
        public ?string $name,
        public string $country,
        public ?string $taxId,
        public array $metadata,
    ) {}
}
