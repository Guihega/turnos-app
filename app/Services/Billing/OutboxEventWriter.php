<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Events\Billing\Contracts\SubscriptionDomainEvent;
use App\Models\Billing\BillingOutboxEvent;

/**
 * Persists a domain event to the billing_outbox_events table.
 *
 * MUST be called inside the same DB::transaction as the domain
 * change that produced the event. If the surrounding transaction
 * rolls back, the outbox row is rolled back with it — preserving
 * the transactional outbox guarantee that an event exists if and
 * only if its originating state change committed.
 *
 * The publisher (PublishOutboxEventsJob, PR-H step 2) reads
 * pending rows (published_at IS NULL AND failed_at IS NULL) and
 * dispatches them to registered handlers.
 *
 * The returned model is refreshed from the database so DB-side
 * defaults (e.g. attempts = 0) are reflected in the instance.
 *
 * @see docs/billing/DECISIONS.md ADR-010 (transactional outbox)
 */
final class OutboxEventWriter
{
    public function write(SubscriptionDomainEvent $event): BillingOutboxEvent
    {
        $row = BillingOutboxEvent::create([
            'aggregate_type' => $event->aggregateType(),
            'aggregate_id' => $event->aggregateId(),
            'event_type' => $event->eventType(),
            'payload' => $event->toArray(),
        ]);

        return $row->refresh();
    }
}
