<?php

declare(strict_types=1);

namespace App\Jobs\Billing;

use App\Models\Billing\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes a persisted webhook event from billing_webhook_events.
 *
 * PR-F scope: skeleton. Loads the event, logs receipt, marks
 * processed_at, increments attempts. The per-event-type dispatch
 * (subscription.updated, invoice.payment_failed, etc.) lands in
 * PR-G with concrete handlers.
 *
 * Retry policy:
 *   - 3 tries with default Laravel backoff.
 *   - On final failure, mark needs_review = true so the admin panel
 *     surfaces the row for manual intervention.
 *
 * Idempotency at this layer: re-running the job on an already-processed
 * event is a no-op (processed_at non-null is the check). The DB-level
 * UNIQUE(gateway, gateway_event_id) prevents duplicates at insert time.
 *
 * @see docs/billing/DECISIONS.md ADR-012
 */
final class ProcessBillingWebhookEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $webhookEventId) {}

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return ['billing', 'webhook', 'webhook-event:'.$this->webhookEventId];
    }

    public function handle(): void
    {
        $event = WebhookEvent::find($this->webhookEventId);
        if ($event === null) {
            Log::warning('billing.webhook.job.not_found', [
                'webhook_event_id' => $this->webhookEventId,
            ]);

            return;
        }

        if ($event->processed_at !== null) {
            // Already processed by a previous run — idempotent no-op.
            return;
        }

        $event->increment('attempts');

        try {
            $this->dispatchByEventType($event);

            $event->update([
                'processed_at' => Carbon::now(),
                'last_error' => null,
            ]);

            Log::info('billing.webhook.job.processed', [
                'webhook_event_id' => $event->id,
                'event_type' => $event->event_type,
            ]);
        } catch (Throwable $e) {
            $event->update([
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            throw $e;
        }
    }

    /**
     * Called by the queue runner after all retries fail. Marks the
     * row so the admin panel can surface it.
     */
    public function failed(Throwable $exception): void
    {
        $event = WebhookEvent::find($this->webhookEventId);
        if ($event === null) {
            return;
        }

        $event->update([
            'needs_review' => true,
            'last_error' => mb_substr($exception->getMessage(), 0, 1000),
        ]);

        Log::error('billing.webhook.job.failed', [
            'webhook_event_id' => $event->id,
            'event_type' => $event->event_type,
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * PR-F scope: no-op (the controller already filtered by signature
     * and persisted the row). PR-G will replace this with a dispatch
     * table that resolves $event->event_type to a concrete handler.
     */
    private function dispatchByEventType(WebhookEvent $event): void
    {
        Log::info('billing.webhook.job.received', [
            'webhook_event_id' => $event->id,
            'event_type' => $event->event_type,
            'note' => 'PR-G will route this to a concrete handler.',
        ]);
    }
}
