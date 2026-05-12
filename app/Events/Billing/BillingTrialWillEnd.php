<?php

declare(strict_types=1);

namespace App\Events\Billing;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when Stripe notifies us that a subscription's trial is about
 * to end (typically 3 days before trial_end).
 *
 * Listeners (out of scope for PR-G; land in PR-H/I) will:
 *   - Send an email to the tenant prompting for a payment method
 *   - Send a push/in-app notification
 *
 * This PR only emits the event. The payload is minimal — listeners
 * load whatever they need from the DB.
 */
final class BillingTrialWillEnd
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $subscriptionId,
        public readonly ?int $daysRemaining,
    ) {}
}
