<?php

declare(strict_types=1);

namespace App\Exceptions\Billing;

use DomainException;

/**
 * Thrown when transitioning into the "active set"
 * {pilot, trialing, active, past_due, paused} would create a second
 * concurrent active subscription for the same customer, violating
 * the partial unique index `one_active_subscription_per_customer`
 * defined in PR-A.
 *
 * The action raises this BEFORE touching the database (defense in
 * depth, see ADR-014 §1). The DB index is the last line of defense
 * if the application check is bypassed.
 */
final class ConcurrentActiveSubscriptionException extends DomainException
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $existingSubscriptionId,
        public readonly string $attemptedSubscriptionId,
    ) {
        parent::__construct(sprintf(
            'Customer %s already has an active subscription (%s); cannot activate %s concurrently.',
            $customerId,
            $existingSubscriptionId,
            $attemptedSubscriptionId,
        ));
    }
}
