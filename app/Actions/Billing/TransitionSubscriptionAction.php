<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Billing\SubscriptionStatus;
use App\Events\Billing\SubscriptionStateChanged;
use App\Exceptions\Billing\ConcurrentActiveSubscriptionException;
use App\Exceptions\Billing\InvalidStateTransitionException;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionStateTransition;
use App\Services\Billing\OutboxEventWriter;
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
 *      - persists the SubscriptionStateChanged event to the outbox
 *        (transactional outbox pattern, ADR-010),
 *      - dispatches SubscriptionStateChanged in-process after commit
 *        for any synchronous listeners (cache, etc.).
 */
final class TransitionSubscriptionAction
{
    public function __construct(
        private readonly OutboxEventWriter $outboxWriter,
    ) {}

    /**
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

        if ($from === $to) {
            return $subscription;
        }

        if (! self::isAllowed($from, $to)) {
            throw new InvalidStateTransitionException(
                subscriptionId: (string) $subscription->id,
                from: $from,
                to: $to,
            );
        }

        if (in_array($to->value, self::ACTIVE_SET, true)) {
            $this->guardConcurrentActive($subscription);
        }

        $occurredAt = new DateTimeImmutable;

        return DB::transaction(function () use ($subscription, $from, $to, $reason, $actor, $metadata, $occurredAt): Subscription {
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

            $event = new SubscriptionStateChanged(
                subscriptionId: (string) $subscription->id,
                from: $from,
                to: $to,
                reason: $reason,
                actor: $actor,
                occurredAt: $occurredAt,
                metadata: $metadata,
            );

            // Transactional outbox: persist event in the same tx as the
            // status change. If this throws, the transition rolls back.
            $this->outboxWriter->write($event);

            DB::afterCommit(function () use ($event): void {
                event($event);
            });

            return $subscription->refresh();
        });
    }

    public static function isAllowed(SubscriptionStatus $from, SubscriptionStatus $to): bool
    {
        if ($from === $to) {
            return false;
        }

        return in_array($to->value, self::ALLOWED[$from->value] ?? [], true);
    }

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
