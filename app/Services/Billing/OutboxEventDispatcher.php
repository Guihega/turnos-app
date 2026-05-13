<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\OutboxEventHandler;
use App\Models\Billing\BillingOutboxEvent;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches an outbox row to its registered handler(s).
 *
 * Handlers are resolved by event_type via the map injected at
 * construction (typically built by BillingServiceProvider from
 * config/billing.php). Each event_type may have a single handler
 * (class-string) or a list of handlers (list<class-string>).
 *
 * When multiple handlers are registered for the same event_type,
 * they are invoked in order. If one throws, the exception propagates
 * and remaining handlers in the list are NOT invoked — the publisher
 * job's retry policy will replay the entire chain on next attempt
 * (handlers must be idempotent per OutboxEventHandler contract).
 *
 * Events with no registered handler are logged at info and treated
 * as successfully published — see ADR-013 (PR-H implementation
 * refinements) for rationale.
 *
 * @see docs/billing/DECISIONS.md ADR-013
 */
final class OutboxEventDispatcher
{
    /**
     * @param  array<string, class-string<OutboxEventHandler>|list<class-string<OutboxEventHandler>>>  $handlers
     */
    public function __construct(
        private readonly array $handlers,
        private readonly Container $container,
    ) {}

    public function dispatch(BillingOutboxEvent $row): void
    {
        $entry = $this->handlers[$row->event_type] ?? null;

        if ($entry === null) {
            Log::info('billing.outbox.no_handler_registered', [
                'outbox_event_id' => $row->id,
                'event_type' => $row->event_type,
            ]);

            return;
        }

        $handlerClasses = is_array($entry) ? $entry : [$entry];

        foreach ($handlerClasses as $handlerClass) {
            /** @var OutboxEventHandler $handler */
            $handler = $this->container->make($handlerClass);
            $handler->handle($row);
        }
    }
}
