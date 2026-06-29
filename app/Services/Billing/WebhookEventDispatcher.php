<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Billing\Contracts\BillingWebhookHandler;
use App\Models\Billing\WebhookEvent;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

/**
 * Routes a WebhookEvent to the appropriate BillingWebhookHandler based
 * on event.type.
 *
 * Per PR-G scope: only 4 event types are handled. Stripe sends hundreds
 * of event types; those we don't register here are logged at info level
 * and skipped silently. The job calling this dispatcher considers a
 * skipped event a success (no retry), preventing the queue from
 * thrashing on events we will never care about.
 *
 * The dispatch table is constructor-injected so tests can pass a
 * narrower table without rebooting the container.
 */
final class WebhookEventDispatcher
{
    /**
     * @param  array<string, class-string<BillingWebhookHandler>>  $handlers
     */
    public function __construct(
        private readonly array $handlers,
        private readonly Container $container,
    ) {}

    public function dispatch(WebhookEvent $event): void
    {
        $handlerClass = $this->handlers[$event->event_type] ?? null;

        if ($handlerClass === null) {
            Log::info('billing.webhook.unhandled', [
                'webhook_event_id' => $event->id,
                'event_type' => $event->event_type,
                'note' => 'No handler registered for this event type — skipping.',
            ]);

            return;
        }

        /** @var BillingWebhookHandler $handler */
        $handler = $this->container->make($handlerClass);
        $handler->handle($event);
    }
}
