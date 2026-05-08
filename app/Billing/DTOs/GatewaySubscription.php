<?php

declare(strict_types=1);

namespace App\Billing\DTOs;

use DateTimeImmutable;

/**
 * Gateway-agnostic representation of a subscription record.
 *
 * Status is normalized to the SubscriptionStatus enum's string values
 * where possible. When the gateway reports a status we don't model
 * (e.g. Stripe's 'incomplete'), $rawStatus carries the original token
 * and $status is null — the caller decides how to interpret.
 *
 * See ADR-015 §3 for DTO design rationale.
 */
final readonly class GatewaySubscription
{
    /**
     * @param  string|null  $status        Normalized to SubscriptionStatus::value, or null if unmappable.
     * @param  string       $rawStatus     The gateway's raw status string (always populated).
     * @param  list<array{price_id: string, quantity: int}>  $items
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $gatewayId,
        public string $gatewayCustomerId,
        public ?string $status,
        public string $rawStatus,
        public array $items,
        public ?DateTimeImmutable $currentPeriodStart,
        public ?DateTimeImmutable $currentPeriodEnd,
        public ?DateTimeImmutable $trialEnd,
        public ?DateTimeImmutable $cancelAt,
        public ?DateTimeImmutable $canceledAt,
        public bool $cancelAtPeriodEnd,
        public array $metadata,
    ) {}
}
