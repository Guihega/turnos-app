<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Subscription lifecycle states.
 *
 * State machine (see docs/billing/SPEC.md for the full diagram):
 *
 *   pilot ──► active ◄──► paused
 *               │
 *               ▼
 *          past_due ──► suspended ──► canceled
 *
 * Transitions are validated in SubscriptionStateMachine and audited
 * in billing_subscription_state_transitions.
 *
 * Pilot tenants land here for 90 days. After trial_end, the dunning
 * job moves them to past_due, then suspended, then canceled.
 */
enum SubscriptionStatus: string
{
    /** Free pilot, 90-day trial. No payment method required. */
    case Pilot = 'pilot';

    /** Trialing on a paid plan (payment method captured). */
    case Trialing = 'trialing';

    /** Paid and active. Entitlements granted. */
    case Active = 'active';

    /** Payment failed. In dunning. Entitlements still granted briefly. */
    case PastDue = 'past_due';

    /** Dunning exhausted. Access blocked. Data preserved. */
    case Suspended = 'suspended';

    /** User-paused subscription. Reversible. */
    case Paused = 'paused';

    /** Terminal. Either user-cancelled or auto-cancelled after suspension. */
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Pilot => 'Piloto',
            self::Trialing => 'En prueba',
            self::Active => 'Activa',
            self::PastDue => 'Pago pendiente',
            self::Suspended => 'Suspendida',
            self::Paused => 'Pausada',
            self::Canceled => 'Cancelada',
        };
    }

    /**
     * Whether the tenant has access to product features in this state.
     * Past-due still has access during the dunning grace window.
     */
    public function grantsAccess(): bool
    {
        return match ($this) {
            self::Pilot, self::Trialing, self::Active, self::PastDue => true,
            self::Suspended, self::Paused, self::Canceled => false,
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Canceled;
    }
}
