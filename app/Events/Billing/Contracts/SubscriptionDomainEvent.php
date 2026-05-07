<?php

declare(strict_types=1);

namespace App\Events\Billing\Contracts;

/**
 * Marker interface for subscription domain events.
 *
 * PR-H will register a global listener on this interface to write
 * implementing events into billing_outbox_events. Until then, events
 * dispatched through Laravel's event() helper are observed only by
 * direct listeners.
 *
 * Implementers MUST expose an immutable payload via toArray(). The
 * outbox writer in PR-H will persist the result of toArray() as the
 * event payload column.
 */
interface SubscriptionDomainEvent
{
    /**
     * Stable identifier for the event type, used as the
     * billing_outbox_events.event_type column. Conventionally the
     * past-tense kebab-case form, e.g. 'subscription.state-changed'.
     */
    public function eventType(): string;

    /**
     * The aggregate (subscription) ULID this event refers to.
     */
    public function aggregateId(): string;

    /**
     * Wall-clock instant when the domain change occurred. Distinct
     * from the dispatch time; for state transitions it equals the
     * occurred_at column on billing_subscription_state_transitions.
     */
    public function occurredAt(): \DateTimeImmutable;

    /**
     * Serializable payload for outbox persistence and downstream
     * consumers. MUST NOT contain Eloquent models or closures.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
