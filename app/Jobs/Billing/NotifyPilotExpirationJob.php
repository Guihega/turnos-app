<?php

declare(strict_types=1);

namespace App\Jobs\Billing;

use App\Enums\Billing\SubscriptionStatus;
use App\Enums\UserRole;
use App\Mail\Billing\BillingPilotExpiringNotification;
use App\Models\Billing\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notifies pilot tenants that their trial is approaching expiration.
 *
 * For each configured offset day (default: 30, 15, 7, 1 days before
 * trial_ends_at), finds all Subscription rows in pilot status whose
 * trial_ends_at falls on today + offset, and sends one email per
 * tenant_admin user.
 *
 * Per MIGRATION_PLAN Fase D: the job is gated by
 * config('billing.notifications.enabled'). When disabled, it is a
 * no-op so we can schedule it before the flag is flipped in
 * production without side effects.
 *
 * Scope (intentional):
 *   - Only subscriptions with status = pilot. Trialing subscriptions
 *     (from PR-O public checkout) are not in scope; if they need
 *     reminders, a separate job will be added.
 *   - Mails go to every TENANT_ADMIN of the tenant. If a tenant has
 *     no admin, a warning is logged and the tenant is skipped.
 *
 * Idempotency caveat (tech-debt):
 *   - Running the job twice in the same calendar day sends each
 *     email twice. Single-instance scheduling (onOneServer +
 *     withoutOverlapping) makes this unlikely but not impossible.
 *   - A future PR should add a billing_notification_log table.
 */
final class NotifyPilotExpirationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        if (! (bool) config('billing.notifications.enabled', false)) {
            Log::info('billing.notify_pilot_expiration.skipped', [
                'reason' => 'notifications_disabled',
            ]);

            return;
        }

        /** @var array<int, int> $offsets */
        $offsets = config('billing.notifications.pilot_expiration_offsets', [30, 15, 7, 1]);

        $totalSent = 0;
        $totalSkipped = 0;

        foreach ($offsets as $offset) {
            $targetDate = now()->addDays($offset)->toDateString();

            $subscriptions = Subscription::query()
                ->where('status', SubscriptionStatus::Pilot)
                ->whereDate('trial_ends_at', $targetDate)
                ->with(['customer.tenant.users' => function ($query): void {
                    $query->where('role', UserRole::TENANT_ADMIN->value);
                }])
                ->get();

            $sentForBucket = 0;
            $skippedForBucket = 0;

            foreach ($subscriptions as $subscription) {
                /** @var Tenant|null $tenant */
                $tenant = $subscription->customer?->tenant;

                if ($tenant === null) {
                    Log::warning('billing.notify_pilot_expiration.skipped', [
                        'reason' => 'tenant_missing',
                        'subscription_id' => $subscription->id,
                    ]);
                    $skippedForBucket++;

                    continue;
                }

                $admins = $tenant->users;

                if ($admins->isEmpty()) {
                    Log::warning('billing.notify_pilot_expiration.skipped', [
                        'reason' => 'no_tenant_admin',
                        'subscription_id' => $subscription->id,
                        'tenant_id' => $tenant->id,
                    ]);
                    $skippedForBucket++;

                    continue;
                }

                foreach ($admins as $admin) {
                    Mail::to($admin)->queue(
                        new BillingPilotExpiringNotification($subscription, $offset),
                    );
                    $sentForBucket++;
                }
            }

            Log::info('billing.notify_pilot_expiration.bucket_processed', [
                'offset_days' => $offset,
                'target_date' => $targetDate,
                'subscriptions_found' => $subscriptions->count(),
                'emails_queued' => $sentForBucket,
                'skipped' => $skippedForBucket,
            ]);

            $totalSent += $sentForBucket;
            $totalSkipped += $skippedForBucket;
        }

        Log::info('billing.notify_pilot_expiration.completed', [
            'offsets' => $offsets,
            'total_emails_queued' => $totalSent,
            'total_skipped' => $totalSkipped,
        ]);
    }
}
