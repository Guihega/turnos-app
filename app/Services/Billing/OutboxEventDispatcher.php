<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\OutboxEventHandler;
use App\Models\Billing\BillingOutboxEvent;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches an outbox row to its registered handler.
 *
 * Handlers are resolved by event_type via the map injected at
 * construction (typically built by BillingServiceProvider from
 * config/billing.php). Each handler implements OutboxEventHandler
 * and consumes the row's payload.
 *
 * Events with no registered handler are considered intentionally
 * unhandled (e.g. consumer ships in a future PR) and treated as
 * successfully published — logged at info level. This avoids
 * accumulating pending rows for events whose consumer hasn't
 * shipped yet.
 *
 * Handler exceptions propagate up; the job catches them and applies
 * the retry / fail policy.
 *
 * @see docs/billing/DECISIONS.md ADR-013
 */
final class OutboxEventDispatcher
{
    /**
     * @param  array<string, class-string<OutboxEventHandler>>  $handlers
     */
    public function __construct(
        private readonly array $handlers,
        private readonly Container $container,
    ) {}

    public function dispatch(BillingOutboxEvent $row): void
    {
        $handlerClass = $this->handlers[$row->event_type] ?? null;

        if ($handlerClass === null) {
            Log::info('billing.outbox.no_handler_registered', [
                'outbox_event_id' => $row->id,
                'event_type' => $row->event_type,
            ]);

            return;
        }

        /** @var OutboxEventHandler $handler */
        $handler = $this->container->make($handlerClass);
        $handler->handle($row);
    }
}
