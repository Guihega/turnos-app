<?php

declare(strict_types=1);

namespace App\Events\Billing;

use App\Models\Billing\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after a Subscription has been created in both the gateway
 * and the local DB (post-commit), with its initial state transition
 * row recorded. Listeners may safely query the Subscription and its
 * state history.
 *
 * @see App\Actions\Billing\CreateSubscriptionAction
 * @see docs/adr/ADR-016-billing-write-contract-and-create-flows.md
 */
final class SubscriptionCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Subscription $subscription) {}
}
