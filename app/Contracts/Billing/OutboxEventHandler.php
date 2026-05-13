<?php

declare(strict_types=1);

namespace App\Contracts\Billing;

use App\Models\Billing\BillingOutboxEvent;

/**
 * Contract for handlers consuming billing outbox events.
 *
 * Implementations MUST be idempotent: the publisher provides
 * at-least-once delivery, so a handler may receive the same
 * outbox row more than once (e.g. after a worker crash between
 * processing and marking published_at).
 *
 * Handlers throw to signal failure; the publisher applies the
 * configured retry / backoff policy.
 */
interface OutboxEventHandler
{
    public function handle(BillingOutboxEvent $event): void;
}
