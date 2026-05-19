<?php

declare(strict_types=1);
use App\Billing\Outbox\Handlers\PastDueEnteredHandler;
use App\Billing\Outbox\Handlers\SubscriptionSuspendedHandler;

/**
 * Billing module configuration.
 *
 * Source of truth for gateway credentials, webhook policies, and
 * cross-gateway invariants used by the Billing module.
 *
 * Per ADR — gateways live behind a Strategy/Adapter, decoupled from
 * the domain. This config keeps each gateway's wiring isolated so
 * adding MercadoPago / OpenPay / Conekta does not touch existing keys.
 *
 * Stripe keys are split into LIVE_* and TEST_* variants. The active set
 * is selected at boot time by STRIPE_MODE. In each environment, only
 * the active set needs to be filled in .env.
 *
 * @see docs/billing/SPEC.md
 * @see docs/billing/SECRETS.md
 * @see docs/billing/DECISIONS.md
 */
$stripeMode = env('STRIPE_MODE', 'test');
$stripePrefix = $stripeMode === 'live' ? 'STRIPE_LIVE_' : 'STRIPE_TEST_';

return [

    /*
    |--------------------------------------------------------------------------
    | Default gateway
    |--------------------------------------------------------------------------
    |
    | Resolved by App\Services\Billing\GatewayResolver when no explicit
    | gateway is provided by the caller (e.g. when starting a brand-new
    | subscription without legacy ties to a specific gateway).
    |
    */

    'default_gateway' => env('BILLING_DEFAULT_GATEWAY', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Gateways
    |--------------------------------------------------------------------------
    |
    | Per-gateway wiring. Disabled gateways have `enabled => false` and are
    | rejected by GatewayResolver before any network call is attempted.
    |
    | Stripe API version is PINNED. Never set this dynamically. A bumped
    | version must be a code change reviewed in a PR — never an env tweak.
    |
    */

    'gateways' => [

        'stripe' => [
            'enabled' => env('STRIPE_ENABLED', true),
            'mode' => $stripeMode,                                  // test | live
            'public_key' => env($stripePrefix.'PUBLIC_KEY'),
            'secret_key' => env($stripePrefix.'SECRET_KEY'),
            'webhook_secret' => env($stripePrefix.'WEBHOOK_SECRET'),
            'api_version' => '2024-11-20.acacia',                   // pinned, see ADR
        ],

        'mercadopago' => [
            'enabled' => env('MERCADOPAGO_ENABLED', false),
            // Wired in Phase 4. Keys deliberately absent until then.
        ],

        'openpay' => [
            'enabled' => env('OPENPAY_ENABLED', false),
            // Backlog (per ADR).
        ],

        'conekta' => [
            'enabled' => env('CONEKTA_ENABLED', false),
            // Backlog (per ADR).
        ],

        'paypal' => [
            'enabled' => env('PAYPAL_ENABLED', false),
            // Backlog (per ADR).
        ],

        'manual' => [
            'enabled' => env('MANUAL_GATEWAY_ENABLED', true),
            // No external API. Used for offline/wire transfer flows.
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook handling
    |--------------------------------------------------------------------------
    |
    | tolerance_seconds: max drift between the timestamp signed by the
    |   gateway and our server clock. Stripe-PHP default is 300s (5 min).
    |   Lower values harden against replay; higher values tolerate clock
    |   skew on stressed hosts. 300 is the conservative default.
    |
    | idempotency_window_hours: how long we remember a processed event_id
    |   before forgetting it. Stripe retries failed deliveries for up to
    |   ~3 days, but practical traffic dedupes within minutes. 24h is the
    |   safe middle ground.
    |
    | retry_max_attempts: cap on how many times the consumer job retries
    |   a transient failure before parking the event for manual review.
    |
    */

    'webhooks' => [
        'tolerance_seconds' => (int) env('BILLING_WEBHOOK_TOLERANCE', 300),
        'idempotency_window_hours' => (int) env('BILLING_WEBHOOK_IDEMPOTENCY_HOURS', 24),
        'retry_max_attempts' => (int) env('BILLING_WEBHOOK_RETRY_MAX', 5),
        'retention_days' => (int) env('BILLING_WEBHOOK_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscriptions
    |--------------------------------------------------------------------------
    |
    | trial_days: how many days a brand-new Subscription spends in the
    |   'trialing' status before Stripe attempts the first charge. PR-E
    |   ADR-016 default is 14. Set to 0 to disable trial (subscriptions
    |   arrive in 'active' or 'incomplete' depending on PM availability).
    |
    */

    'subscriptions' => [
        'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    |
    | ttl_days: how long we retain billing_idempotency_keys rows after
    |   creation. Stripe itself guarantees idempotency for ~24 hours
    |   server-side; we keep them longer for forensics + auditing.
    |   A cleanup job (PR-J) reaps rows past this TTL.
    |
    */

    'idempotency' => [
        'ttl_days' => (int) env('BILLING_IDEMPOTENCY_TTL_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscriptions
    |--------------------------------------------------------------------------
    |
    | trial_days: how many days a brand-new Subscription spends in the
    |   'trialing' status before Stripe attempts the first charge. PR-E
    |   ADR-016 default is 14. Set to 0 to disable trial.
    |
    */

    'subscriptions' => [
        'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    |
    | ttl_days: how long we retain billing_idempotency_keys rows after
    |   creation. Stripe guarantees server-side idempotency for ~24h;
    |   the extra window is for forensics. A cleanup job (PR-J) reaps
    |   rows past this TTL.
    |
    */

    'idempotency' => [
        'ttl_days' => (int) env('BILLING_IDEMPOTENCY_TTL_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Smoke tests (opt-in)
    |--------------------------------------------------------------------------
    |
    | Knobs for tests/Feature/Billing/Stripe/StripeWriteSmokeTest, which
    | hits the real Stripe API in test mode. Both must be set explicitly;
    | the test skips otherwise. See ADR-016 and the test docblock.
    |
    */

    'smoke' => [
        'live' => env('STRIPE_LIVE_SMOKE') === 'true',
        'test_price_id' => env('STRIPE_TEST_PRICE_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbox publisher
    |--------------------------------------------------------------------------
    |
    | Operational knobs for PublishOutboxEventsJob (PR-H). The job runs
    | every 30s via the scheduler and claims a batch of pending rows
    | from billing_outbox_events.
    |
    | publish_batch_size — Max rows claimed per tick. Tuned so the
    | scheduled run completes well under 30s. Increase if backlog grows.
    |
    | handlers — Map of event_type to OutboxEventHandler FQCN. Events
    | with no entry are logged and marked published (no-op delivery).
    | Consumers ship as they appear (PR-I dunning, etc.).
    |
    | See ADR-010 (transactional outbox), ADR-013 (operational defaults).
    */

    'outbox' => [
        'publish_batch_size' => env('BILLING_OUTBOX_PUBLISH_BATCH_SIZE', 100),
        'handlers' => [
            'subscription.state-changed' => [
                PastDueEnteredHandler::class,
                SubscriptionSuspendedHandler::class,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reconciliation
    |--------------------------------------------------------------------------
    |
    | Operational knobs for ReconcileSubscriptionsJob (PR-J). Runs nightly
    | and compares local subscription status against the gateway truth.
    | Drift is logged only — auto-correction is intentionally out of scope.
    | See the job docblock and ADR-017 for rationale.
    |
    */

    'reconciliation' => [
        'batch_size' => (int) env('BILLING_RECONCILIATION_BATCH_SIZE', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency rounding
    |--------------------------------------------------------------------------
    |
    | Some currencies (COP, CLP, ARS) traditionally round to whole units.
    | Others (USD, MXN, PEN) keep two decimals. Stripe always works in
    | the smallest unit (cents). The application layer reads this map to
    | render prices and to validate inputs; storage stays in cents always.
    |
    */

    'currency_decimals' => [
        'USD' => 2,
        'MXN' => 2,
        'PEN' => 2,
        'COP' => 0,
        'CLP' => 0,
        'ARS' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Operational flag and offsets for NotifyPilotExpirationJob (PR-P).
    | When disabled, the job becomes a no-op. Offsets are the day counts
    | before trial_ends_at at which a pilot tenant receives an email
    | nudge. See MIGRATION_PLAN Fase D.
    |
    */
    'notifications' => [
        'enabled' => (bool) env('BILLING_NOTIFICATIONS_ENABLED', false),
        'pilot_expiration_offsets' => [30, 15, 7, 1],
    ],
];
