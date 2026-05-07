<?php

declare(strict_types=1);

namespace App\Events\Billing;

use App\Enums\Billing\SubscriptionStatus;
use App\Events\Billing\Contracts\SubscriptionDomainEvent;
use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Emitted whenever a subscription's status column changes via the
 * state machine. Same-state no-ops do NOT emit this event (see ADR-014 §5).
 */
final class SubscriptionStateChanged implements SubscriptionDomainEvent
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $metadata  Free-form context, e.g.
     *                                          ['invoice_id' => '...', 'attempt' => 3].
     */
    public function __construct(
        public readonly string $subscriptionId,
        public readonly SubscriptionStatus $from,
        public readonly SubscriptionStatus $to,
        public readonly string $reason,
        public readonly ?string $actor,
        public readonly DateTimeImmutable $occurredAt,
        public readonly array $metadata = [],
    ) {}

    public function eventType(): string
    {
        return 'subscription.state-changed';
    }

    public function aggregateId(): string
    {
        return $this->subscriptionId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subscription_id' => $this->subscriptionId,
            'from' => $this->from->value,
            'to' => $this->to->value,
            'reason' => $this->reason,
            'actor' => $this->actor,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'metadata' => $this->metadata,
        ];
    }
}
