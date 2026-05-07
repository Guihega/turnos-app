<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\SubscriptionStatus;
use App\Events\Billing\SubscriptionStateChanged;
use App\Exceptions\Billing\ConcurrentActiveSubscriptionException;
use App\Exceptions\Billing\InvalidStateTransitionException;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionStateTransition;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a subscription from one SubscriptionStatus to another,
 * enforcing the matrix in ADR-014.
 *
 * Behaviour summary:
 *
 * 1. If $to === $subscription->status, returns the subscription
 *    unchanged. No row, no event, no exception (ADR-014 §5).
 * 2. If the (from, to) pair is not in self::ALLOWED, throws
 *    InvalidStateTransitionException.
 * 3. If $to is in the active set and the customer already has another
 *    subscription in the active set, throws
 *    ConcurrentActiveSubscriptionException.
 * 4. Otherwise, inside a single DB::transaction:
 *      - inserts a row in billing_subscription_state_transitions,
 *      - updates billing_subscriptions.status,
 *      - dispatches SubscriptionStateChanged after commit.
 */
final class TransitionSubscriptionAction
{
    /**
     * The subset of SubscriptionStatus values that occupy the
     * "active slot" enforced by the partial unique index
     * `one_active_subscription_per_customer` (PR-A).
     *
     * @var list<string>
     */
    public const ACTIVE_SET = [
        'pilot',
        'trialing',
        'active',
        'past_due',
        'paused',
    ];

    /**
     * The transition matrix from ADR-014. Keys are the `from` enum
     * value; values are the list of allowed `to` enum values.
     *
     * Same-state targets are NOT included here; they are handled
     * separately as a silent no-op.
     *
     * @var array<string, list<string>>
     */
    public const ALLOWED = [
        'pilot' => ['trialing', 'active', 'canceled'],
        'trialing' => ['active', 'past_due', 'canceled'],
        'active' => ['past_due', 'paused', 'canceled'],
        'past_due' => ['active', 'suspended', 'canceled'],
        'paused' => ['active', 'canceled'],
        'suspended' => ['active', 'canceled'],
        'canceled' => [],
    ];

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function execute(
        Subscription $subscription,
        SubscriptionStatus $to,
        string $reason,
        ?string $actor = null,
        array $metadata = [],
    ): Subscription {
        $from = $subscription->status;

        // Same-state: silent no-op.
        if ($from === $to) {
            return $subscription;
        }

        // Matrix check.
        if (! self::isAllowed($from, $to)) {
            throw new InvalidStateTransitionException(
                subscriptionId: (string) $subscription->id,
                from: $from,
                to: $to,
            );
        }

        // Active-slot check (only when entering the active set).
        if (in_array($to->value, self::ACTIVE_SET, true)) {
            $this->guardConcurrentActive($subscription);
        }

        $occurredAt = new DateTimeImmutable;

        return DB::transaction(function () use ($subscription, $from, $to, $reason, $actor, $metadata, $occurredAt): Subscription {
            // Persist using PR-A's column names. The schema does not have a
            // dedicated `actor` column; we fold it into `context` along with
            // any caller-provided metadata. ADR-014 §6 documents the mapping.
            $context = $metadata;
            if ($actor !== null) {
                $context['actor'] = $actor;
            }

            SubscriptionStateTransition::create([
                'subscription_id' => $subscription->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'reason' => $reason,
                'context' => $context !== [] ? $context : null,
                'transitioned_at' => $occurredAt,
            ]);

            $subscription->status = $to;
            $subscription->save();

            DB::afterCommit(function () use ($subscription, $from, $to, $reason, $actor, $occurredAt, $metadata): void {
                event(new SubscriptionStateChanged(
                    subscriptionId: (string) $subscription->id,
                    from: $from,
                    to: $to,
                    reason: $reason,
                    actor: $actor,
                    occurredAt: $occurredAt,
                    metadata: $metadata,
                ));
            });

            return $subscription->refresh();
        });
    }

    public static function isAllowed(SubscriptionStatus $from, SubscriptionStatus $to): bool
    {
        if ($from === $to) {
            // Same-state is handled by execute() as a no-op; the matrix
            // itself does not "allow" it in the sense of generating a
            // recorded transition.
            return false;
        }

        return in_array($to->value, self::ALLOWED[$from->value] ?? [], true);
    }

    /**
     * Raise ConcurrentActiveSubscriptionException if the customer
     * already has another subscription occupying the active slot.
     *
     * Excludes the subscription being transitioned, so a sub already
     * in the active set transitioning to another active state (e.g.
     * active → past_due) does not trip the guard.
     */
    private function guardConcurrentActive(Subscription $subscription): void
    {
        $existing = Subscription::query()
            ->where('customer_id', $subscription->customer_id)
            ->where('id', '!=', $subscription->id)
            ->whereIn('status', self::ACTIVE_SET)
            ->first();

        if ($existing !== null) {
            throw new ConcurrentActiveSubscriptionException(
                customerId: (string) $subscription->customer_id,
                existingSubscriptionId: (string) $existing->id,
                attemptedSubscriptionId: (string) $subscription->id,
            );
        }
    }
}
