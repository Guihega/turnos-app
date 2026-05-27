<?php

declare(strict_types=1);

namespace App\Jobs\Billing;

use App\Actions\Billing\TransitionSubscriptionAction;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Billing\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cancels pilot subscriptions whose trial period has ended (PR-T).
 *
 * Iterates every Subscription in pilot status with a passed
 * trial_ends_at and transitions it to Canceled via the canonical
 * TransitionSubscriptionAction. This realizes the "nightly trial-
 * expiry job" named explicitly in ADR-014 §4 (pilot row, J actor).
 *
 * Why Canceled and not PastDue: ADR-014 records that pilots have
 * no payment method and no committed plan, so PastDue ("billing
 * period expired without payment") is semantically empty for them.
 * The pilot -> canceled transition is in the ALLOWED matrix
 * specifically for this job; pilot -> past_due is not modeled.
 *
 * Pre-requisite for MIGRATION_PLAN Fase F: without this job, an
 * expired pilot keeps status=pilot indefinitely, grantsAccess()
 * returns true, and enforcement would let the tenant in forever.
 *
 * Gated by config('billing.trial_expiration.enabled'). When disabled
 * the job is a no-op so it can be scheduled before being switched on
 * in production (mirrors NotifyPilotExpirationJob).
 *
 * Scope (intentional):
 *   - Only subscriptions with status = Pilot. Trialing subscriptions
 *     (paid trials) have their own dunning path (active/past_due/
 *     suspended) per ADR-014; they are out of scope here.
 *   - Only local pilots (stripe_subscription_id IS NULL). A pilot
 *     that somehow has a gateway id is unusual and warrants manual
 *     inspection rather than automated cancellation.
 *
 * Idempotency:
 *   - TransitionSubscriptionAction is no-op on same-state (ADR-014 §5),
 *     so re-running this job after a partial run is safe.
 *   - Per-subscription error isolation: a single failure logs and the
 *     loop continues.
 */
final class CancelExpiredPilotsJob implements ShouldBeUnique, ShouldQueue
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
        return 'cancel-expired-pilots';
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(TransitionSubscriptionAction $transition): void
    {
        if (! (bool) config('billing.trial_expiration.enabled', false)) {
            Log::info('billing.cancel_expired_pilots.skipped', [
                'reason' => 'trial_expiration_disabled',
            ]);

            return;
        }

        $batchSize = (int) config('billing.trial_expiration.batch_size', 200);

        $canceled = 0;
        $errors = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::Pilot->value)
            ->where('trial_ends_at', '<', now())
            ->whereNull('stripe_subscription_id')
            ->orderBy('id')
            ->chunkById($batchSize, function ($subscriptions) use ($transition, &$canceled, &$errors): void {
                /** @var Collection<int, Subscription> $subscriptions */
                foreach ($subscriptions as $subscription) {
                    assert($subscription instanceof Subscription);

                    try {
                        $transition->execute(
                            subscription: $subscription,
                            to: SubscriptionStatus::Canceled,
                            reason: 'pilot trial expired',
                            actor: 'cancel-expired-pilots-job',
                            metadata: [
                                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                            ],
                        );
                        $canceled++;
                    } catch (Throwable $e) {
                        $errors++;
                        Log::error('billing.cancel_expired_pilots.failed', [
                            'subscription_id' => $subscription->id,
                            'exception' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('billing.cancel_expired_pilots.completed', [
            'canceled' => $canceled,
            'errors' => $errors,
        ]);
    }
}
