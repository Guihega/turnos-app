<?php

declare(strict_types=1);

namespace App\Exceptions\Billing;

use App\Enums\Billing\SubscriptionStatus;
use DomainException;

/**
 * Thrown when TransitionSubscriptionAction is asked to perform a
 * (from, to) pair that is not allowed by the matrix in ADR-014.
 *
 * Same-state calls (to === current) are NOT a violation; the action
 * treats them as a silent no-op.
 */
final class InvalidStateTransitionException extends DomainException
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly SubscriptionStatus $from,
        public readonly SubscriptionStatus $to,
    ) {
        parent::__construct(sprintf(
            'Subscription %s cannot transition from "%s" to "%s" (see ADR-014).',
            $subscriptionId,
            $from->value,
            $to->value,
        ));
    }
}
