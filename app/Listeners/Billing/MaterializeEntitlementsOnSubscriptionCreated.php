<?php

declare(strict_types=1);

namespace App\Listeners\Billing;

use App\Actions\Billing\MaterializeEntitlementsAction;
use App\Events\Billing\SubscriptionCreated;

/**
 * Materializes plan-derived entitlements when a subscription is created.
 *
 * Synchronous and inline: SubscriptionCreated is dispatched post-commit
 * (ADR-016), so the subscription is already durable when this runs. The
 * materialization is a small, idempotent set of upserts, so queueing it
 * would only widen the window during which the new subscription has no
 * billing_entitlements rows. While billing.enforcement.enabled is false,
 * EntitlementService's dual-read fallback covers that window anyway; once
 * enforcement is on, keeping this inline keeps the window negligible.
 *
 * Thin shell over MaterializeEntitlementsAction, which owns the logic and
 * is also driven by the backfill command (PR-S2).
 */
final class MaterializeEntitlementsOnSubscriptionCreated
{
    public function __construct(
        private readonly MaterializeEntitlementsAction $materialize,
    ) {}

    public function handle(SubscriptionCreated $event): void
    {
        $this->materialize->execute($event->subscription);
    }
}
