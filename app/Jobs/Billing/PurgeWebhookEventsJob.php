<?php

declare(strict_types=1);

namespace App\Jobs\Billing;

use App\Models\Billing\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Purges old processed webhook events.
 *
 * Per ADR-012: rows are purgeable when
 *   processed_at IS NOT NULL AND processed_at < now() - interval '90 days'
 *
 * Rows with needs_review = true are NEVER purged automatically.
 *
 * Runs nightly via the scheduler (see routes/console.php).
 */
final class PurgeWebhookEventsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function viaQueue(): string
    {
        return 'billing-webhooks';
    }

    public function uniqueId(): string
    {
        return 'purge-webhook-events';
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(): void
    {
        $retentionDays = (int) config('billing.webhooks.retention_days', 90);
        $cutoff = Carbon::now()->subDays($retentionDays);

        $deleted = WebhookEvent::query()
            ->whereNotNull('processed_at')
            ->where('needs_review', false)
            ->where('processed_at', '<', $cutoff)
            ->delete();

        Log::info('billing.webhooks.purge_completed', [
            'retention_days' => $retentionDays,
            'cutoff' => $cutoff->toIso8601String(),
            'deleted_rows' => $deleted,
        ]);
    }
}
