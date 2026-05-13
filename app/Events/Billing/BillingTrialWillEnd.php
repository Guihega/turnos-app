<?php

declare(strict_types=1);

namespace App\Events\Billing;

use App\Events\Billing\Contracts\SubscriptionDomainEvent;
use App\Models\Billing\Subscription;
use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when Stripe notifies us that a subscription's trial is about
 * to end (typically 3 days before trial_end).
 *
 * Listeners (out of scope for PR-H; land in PR-I) will send email,
 * push, and in-app notifications.
 *
 * Persisted to the outbox by TrialWillEndHandler so downstream async
 * consumers can react even if in-process listeners fail.
 */
final class BillingTrialWillEnd implements SubscriptionDomainEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $subscriptionId,
        public readonly ?int $daysRemaining,
        public readonly DateTimeImmutable $occurredAt,
    ) {}

    public function eventType(): string
    {
        return 'subscription.trial-will-end';
    }

    public function aggregateType(): string
    {
        return Subscription::class;
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
            'days_remaining' => $this->daysRemaining,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
        ];
    }
}
