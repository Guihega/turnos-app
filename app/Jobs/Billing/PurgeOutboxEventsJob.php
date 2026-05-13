<?php

declare(strict_types=1);

namespace App\Jobs\Billing;

use App\Models\Billing\BillingOutboxEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Purges old published outbox events.
 *
 * Per ADR-013 §Retención: rows are purgeable when
 *   published_at IS NOT NULL AND published_at < now() - interval '30 days'
 *
 * Rows with failed_at IS NOT NULL are NEVER purged automatically.
 *
 * Runs nightly via the scheduler (see routes/console.php).
 */
final class PurgeOutboxEventsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function viaQueue(): string
    {
        return 'billing-outbox';
    }

    public function uniqueId(): string
    {
        return 'purge-outbox-events';
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(): void
    {
        $retentionDays = (int) config('billing.outbox.retention_days', 30);
        $cutoff = Carbon::now()->subDays($retentionDays);

        $deleted = BillingOutboxEvent::query()
            ->whereNotNull('published_at')
            ->whereNull('failed_at')
            ->where('published_at', '<', $cutoff)
            ->delete();

        Log::info('billing.outbox.purge_completed', [
            'retention_days' => $retentionDays,
            'cutoff' => $cutoff->toIso8601String(),
            'deleted_rows' => $deleted,
        ]);
    }
}
