<?php

declare(strict_types=1);

namespace App\Events\Billing\Contracts;

/**
 * Marker interface for subscription domain events.
 *
 * Events implementing this interface are persisted to
 * billing_outbox_events in the SAME database transaction as the
 * domain change that produced them (transactional outbox pattern).
 * This is done via explicit calls to OutboxEventWriter from the
 * producing Action / Handler — there is no wildcard listener that
 * would run after commit.
 *
 * After commit, Laravel's event(...) helper still dispatches the
 * event to any in-process listeners (cache invalidation, etc.).
 * Async / cross-boundary delivery is handled exclusively by the
 * outbox publisher (PublishOutboxEventsJob, PR-H).
 *
 * Implementers MUST expose an immutable payload via toArray() that
 * is JSON-serializable and contains no Eloquent models or closures.
 *
 * @see docs/billing/DECISIONS.md ADR-010 (outbox), ADR-013 (ops defaults)
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
     * Fully-qualified class name of the aggregate root the event
     * refers to, used as billing_outbox_events.aggregate_type.
     * Typically a model FQCN, e.g. App\Models\Billing\Subscription::class.
     */
    public function aggregateType(): string;

    /**
     * ULID of the aggregate this event refers to. Stored as
     * billing_outbox_events.aggregate_id.
     */
    public function aggregateId(): string;

    /**
     * Wall-clock instant when the domain change occurred. Distinct
     * from the dispatch time; for state transitions it equals the
     * transitioned_at column on billing_subscription_state_transitions.
     */
    public function occurredAt(): \DateTimeImmutable;

    /**
     * Serializable payload persisted as billing_outbox_events.payload
     * and consumed by downstream handlers. MUST NOT contain Eloquent
     * models or closures.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
