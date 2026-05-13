<?php

declare(strict_types=1);

namespace App\Jobs\Billing;

use App\Billing\Contracts\BillingGateway;
use App\Billing\Exceptions\GatewayException;
use App\Billing\Exceptions\GatewayNotFoundException;
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

/**
 * Reconciles local subscription status against the gateway truth.
 *
 * Detection-only: logs drift, never auto-corrects. See ADR-017 for the
 * full rationale (audit cleanliness, state machine bypass, visibility).
 *
 * Three drift categories are logged:
 *   1. STATUS_MISMATCH — both sides have a known status but disagree.
 *   2. UNMAPPED_GATEWAY_STATUS — gateway status we don't model.
 *   3. NOT_FOUND — the subscription no longer exists at the gateway.
 *
 * Transient gateway errors (rate limit, timeout) are logged as
 * gateway_error and skipped — the next nightly run retries.
 *
 * Scope: every Subscription whose status is in RECONCILABLE_STATUSES
 * and which has a stripe_subscription_id. Canceled subscriptions are
 * frozen and intentionally excluded.
 */
final class ReconcileSubscriptionsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /**
     * Lifecycle statuses worth checking.
     */
    public const RECONCILABLE_STATUSES = [
        SubscriptionStatus::Pilot,
        SubscriptionStatus::Trialing,
        SubscriptionStatus::Active,
        SubscriptionStatus::PastDue,
        SubscriptionStatus::Paused,
        SubscriptionStatus::Suspended,
    ];

    public function viaQueue(): string
    {
        return 'billing-outbox';
    }

    public function uniqueId(): string
    {
        return 'reconcile-subscriptions';
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(BillingGateway $gateway): void
    {
        $batchSize = (int) config('billing.reconciliation.batch_size', 200);
        $statuses = array_map(
            static fn (SubscriptionStatus $s): string => $s->value,
            self::RECONCILABLE_STATUSES,
        );

        $checked = 0;
        $drifts = 0;

        Subscription::query()
            ->whereNotNull('stripe_subscription_id')
            ->whereIn('status', $statuses)
            ->orderBy('id')
            ->chunkById($batchSize, function ($subscriptions) use ($gateway, &$checked, &$drifts): void {
                /** @var Collection<int, Subscription> $subscriptions */
                foreach ($subscriptions as $subscription) {
                    $checked++;
                    if ($this->checkOne($subscription, $gateway)) {
                        $drifts++;
                    }
                }
            });

        Log::info('billing.reconcile.completed', [
            'checked' => $checked,
            'drifts_detected' => $drifts,
        ]);
    }

    /**
     * Returns true if drift was detected (and logged), false otherwise.
     */
    private function checkOne(Subscription $subscription, BillingGateway $gateway): bool
    {
        $stripeSubId = $subscription->stripe_subscription_id;
        if ($stripeSubId === null) {
            return false;
        }

        try {
            $dto = $gateway->retrieveSubscription($stripeSubId);
        } catch (GatewayNotFoundException) {
            Log::warning('billing.reconcile.drift.not_found', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $stripeSubId,
                'local_status' => $subscription->status->value,
            ]);

            return true;
        } catch (GatewayException $e) {
            Log::error('billing.reconcile.gateway_error', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $stripeSubId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($dto->status === null) {
            Log::warning('billing.reconcile.drift.unmapped_gateway_status', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $stripeSubId,
                'local_status' => $subscription->status->value,
                'gateway_raw_status' => $dto->rawStatus,
            ]);

            return true;
        }

        if ($subscription->status->value !== $dto->status) {
            Log::warning('billing.reconcile.drift.status_mismatch', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $stripeSubId,
                'local_status' => $subscription->status->value,
                'gateway_status' => $dto->status,
                'gateway_raw_status' => $dto->rawStatus,
            ]);

            return true;
        }

        return false;
    }
}
