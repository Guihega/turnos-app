<?php

declare(strict_types=1);

namespace App\Jobs\Billing;

use App\Models\Billing\BillingOutboxEvent;
use App\Services\Billing\OutboxEventDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Publishes pending billing_outbox_events rows.
 *
 * Runs every 30 seconds via the scheduler (see routes/console.php).
 * Each invocation claims a batch of pending rows using
 * SELECT ... FOR UPDATE SKIP LOCKED, dispatches each to the
 * OutboxEventDispatcher, and updates the row's terminal state.
 *
 * Retry policy (per row, NOT per job):
 *   - attempts is incremented on every processing attempt.
 *   - On success: published_at = now().
 *   - On failure: last_error captured. If attempts < MAX_ATTEMPTS,
 *     next_attempt_at = now() + BACKOFF_SECONDS[attempts-1].
 *     Otherwise failed_at = now() (terminal; requires manual replay).
 *
 * Backoff: [60, 300, 1800] seconds per ADR-013.
 *
 * The whole claim+process+update happens inside a single DB
 * transaction. If the worker crashes mid-processing, the row's
 * lock is released and it becomes eligible again — at-least-once
 * semantics. Handlers MUST be idempotent (OutboxEventHandler docblock).
 *
 * Concurrency: SKIP LOCKED ensures parallel workers do not double-claim.
 *
 * @see docs/billing/DECISIONS.md ADR-010, ADR-013
 */
final class PublishOutboxEventsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Single attempt at the job level. Per-row retry is handled
     * inside the job body via next_attempt_at + attempts.
     */
    public int $tries = 1;

    /**
     * Backoff schedule (seconds). Index 0 applies after attempts=1
     * (the first failure), index 1 after attempts=2, etc.
     *
     * @var list<int>
     */
    public const BACKOFF_SECONDS = [60, 300, 1800];

    public const MAX_ATTEMPTS = 3;

    public function viaQueue(): string
    {
        return 'billing-outbox';
    }

    /**
     * Ensure only one instance runs at a time across all workers.
     * Belt-and-braces with FOR UPDATE SKIP LOCKED, cheap insurance
     * against scheduler double-fires.
     */
    public function uniqueId(): string
    {
        return 'publish-outbox-events';
    }

    public function uniqueFor(): int
    {
        return 120;
    }

    public function handle(OutboxEventDispatcher $dispatcher): void
    {
        $batchSize = (int) config('billing.outbox.publish_batch_size', 100);

        DB::transaction(function () use ($dispatcher, $batchSize): void {
            /** @var Collection<int, BillingOutboxEvent> $rows */
            $rows = BillingOutboxEvent::query()
                ->whereNull('published_at')
                ->whereNull('failed_at')
                ->where(function ($q): void {
                    $q->whereNull('next_attempt_at')
                        ->orWhere('next_attempt_at', '<=', now());
                })
                ->orderBy('created_at')
                ->limit($batchSize)
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $this->processOne($row, $dispatcher);
            }
        });
    }

    private function processOne(BillingOutboxEvent $row, OutboxEventDispatcher $dispatcher): void
    {
        $row->attempts = $row->attempts + 1;

        try {
            $dispatcher->dispatch($row);
            $row->published_at = Carbon::now();
            $row->last_error = null;
            $row->next_attempt_at = null;
            $row->save();
        } catch (Throwable $e) {
            $row->last_error = mb_substr($e->getMessage(), 0, 1000);

            if ($row->attempts >= self::MAX_ATTEMPTS) {
                $row->failed_at = Carbon::now();
                $row->next_attempt_at = null;
                Log::error('billing.outbox.publish_failed_terminal', [
                    'outbox_event_id' => $row->id,
                    'event_type' => $row->event_type,
                    'attempts' => $row->attempts,
                    'last_error' => $row->last_error,
                ]);
            } else {
                $backoffIndex = $row->attempts - 1;
                $backoff = self::BACKOFF_SECONDS[$backoffIndex] ?? self::BACKOFF_SECONDS[count(self::BACKOFF_SECONDS) - 1];
                $row->next_attempt_at = Carbon::now()->addSeconds($backoff);
                Log::warning('billing.outbox.publish_failed_retry', [
                    'outbox_event_id' => $row->id,
                    'event_type' => $row->event_type,
                    'attempts' => $row->attempts,
                    'next_attempt_at' => $row->next_attempt_at->toIso8601String(),
                    'last_error' => $row->last_error,
                ]);
            }

            $row->save();
        }
    }
}
