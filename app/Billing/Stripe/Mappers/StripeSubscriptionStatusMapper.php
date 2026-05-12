<?php

declare(strict_types=1);

namespace App\Billing\Stripe\Mappers;

use App\Enums\Billing\SubscriptionStatus;

/**
 * Maps Stripe subscription status strings to our SubscriptionStatus enum.
 *
 * Stripe statuses we DO map:
 *   trialing      → Trialing
 *   active        → Active
 *   past_due      → PastDue
 *   paused        → Paused
 *   canceled      → Canceled
 *   unpaid        → Suspended  (Stripe's closest analog of our suspended)
 *
 * Stripe statuses we DO NOT map (return null):
 *   incomplete           — sub created, payment intent pending
 *   incomplete_expired   — payment intent expired before completion
 *
 * Callers that get null SHOULD inspect the raw status string and decide
 * (e.g. update period fields but skip the state transition).
 *
 * This is the canonical location for the mapping. StripeBillingGateway
 * also has a private copy for backward compatibility with the read-side
 * mapSubscription(), but new code uses this class.
 */
final class StripeSubscriptionStatusMapper
{
    public static function fromStripeString(string $stripeStatus): ?SubscriptionStatus
    {
        return match ($stripeStatus) {
            'trialing' => SubscriptionStatus::Trialing,
            'active' => SubscriptionStatus::Active,
            'past_due' => SubscriptionStatus::PastDue,
            'paused' => SubscriptionStatus::Paused,
            'canceled' => SubscriptionStatus::Canceled,
            'unpaid' => SubscriptionStatus::Suspended,
            default => null,
        };
    }
}
