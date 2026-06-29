<?php

declare(strict_types=1);

namespace App\Billing\Contracts;

use App\Models\Billing\WebhookEvent;

/**
 * Per-event-type handler for a billing webhook.
 *
 * Each handler is invoked by WebhookEventDispatcher when its
 * registered event type is received. The dispatcher resolves the
 * concrete handler from the container.
 *
 * Idempotency: a handler may be invoked more than once for the same
 * WebhookEvent if the job retries. Implementations MUST be idempotent —
 * typically by checking the local resource's state before mutating.
 *
 * Error semantics:
 *   - throw to signal "retry this job" (e.g. transient DB failure)
 *   - return normally to signal "done; do not retry" (success OR
 *     irrecoverable skip such as "this event refers to a resource
 *     we don't own")
 *
 * @see App\Services\Billing\WebhookEventDispatcher
 * @see docs/billing/DECISIONS.md ADR-007, ADR-012
 */
interface BillingWebhookHandler
{
    public function handle(WebhookEvent $event): void;
}
