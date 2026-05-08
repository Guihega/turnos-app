<?php

declare(strict_types=1);

namespace App\Billing\DTOs;

use DateTimeImmutable;

/**
 * Gateway-agnostic representation of an invoice.
 *
 * Amounts are in MINOR UNITS (cents for USD, centavos for MXN, etc.)
 * to match the rest of the billing schema. Currency is ISO 4217 lower
 * or upper-case as the gateway returns it; consumers should normalize
 * if comparing.
 */
final readonly class GatewayInvoice
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $gatewayId,
        public string $gatewayCustomerId,
        public ?string $gatewaySubscriptionId,
        public string $rawStatus,
        public string $currency,
        public int $amountDue,
        public int $amountPaid,
        public int $amountRemaining,
        public ?string $hostedInvoiceUrl,
        public ?DateTimeImmutable $created,
        public ?DateTimeImmutable $dueDate,
        public ?DateTimeImmutable $paidAt,
        public array $metadata,
    ) {}
}
