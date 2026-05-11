# ADR-016 — Billing write contract and create flows

**Status:** Accepted
**Date:** 2026-05-11
**Authors:** PR-E
**Related:** ADR-005, ADR-007, ADR-011, ADR-012, ADR-013, ADR-014, ADR-015

## Context

PR-D shipped the read-only side of the billing gateway abstraction: a BillingGateway interface, DTOs, exception hierarchy, and a Stripe adapter wired to read customers, subscriptions, invoices, and payment methods.

PR-E extends this with the write-side: the ability to create a Customer and a Subscription. This is the first time the system performs side-effecting calls against the gateway, so a series of decisions are needed about contract shape, idempotency, persistence ordering, failure recovery, and HTTP exposure.

## Decision

### 1. Contract split (ISP)

Writes live in a separate interface, BillingGatewayWriter, complementary to BillingGateway. Consumers that only need to read (webhook handlers in PR-F, reconciliation jobs in PR-J) depend on the narrower surface. This follows ADR-015 section 4 (read/write split) and the Interface Segregation Principle.

Additional write methods (cancel, update, attach PM, etc.) land in subsequent PRs without breaking this contract.

### 2. Idempotency strategy

Every logical write operation generates a deterministic idempotency key on our side and forwards it to the gateway as the gateway's native idempotency channel (Stripe-Idempotency-Key header).

We persist these keys locally in billing_idempotency_keys with operation discriminator, gateway value, the ULID we minted and sent, sha256 of canonicalized payload (keys sorted recursively), response_snapshot populated after the gateway returns ok, optional customer_id FK, and created_at/expires_at.

Lookup: (operation, gateway, request_hash) plus optional customer_id. A non-expired match short-circuits the entire saga — we don't even call the gateway.

UNIQUE constraint on (gateway, idempotency_key) ensures keys are unique per-gateway. The same string can appear across gateways without collision.

TTL is 7 days by default (config('billing.idempotency.ttl_days')). Stripe guarantees server-side idempotency for ~24h; the extra window is for forensics.

### 3. Saga semantics (no rollback of the gateway)

A write operation is a saga with two effectful steps:

1. **Gateway call.** If it fails, we re-throw and do not write to the local DB. The idempotency key was already minted; a retry reuses it, so the gateway dedupes.
2. **Local DB writes.** Wrapped in a single DB::transaction. If they fail, the gateway resource is orphaned — but the idempotency key has no response_snapshot, so a retry calls the gateway again with the same key, gets the same resource back, and completes the local writes.

We do NOT attempt to delete the orphan resource in the gateway. Reasons: Stripe customers are extremely cheap to leave; a delete call can itself fail putting us in an infinite recovery loop; the idempotency snapshot path is simpler and recovers correctly with a retry.

Events are dispatched post-commit via DB::afterCommit. If the transaction rolls back, no listener observes the partial state.

### 4. Trial period

Subscriptions arrive in the 'trialing' status by default, with trial_ends_at = now() + config('billing.subscriptions.trial_days') (default 14).

We forward trial_period_days to Stripe and use payment_behavior=default_incomplete together with payment_settings.save_default_payment_method=on_subscription. The combination means no payment method is required at creation time, Stripe creates a PaymentIntent that will be activated when a PM is attached, and the subscription remains in trialing for the trial window then transitions to past_due if no PM is captured — at which point the PR-I dunning flow takes over.

This trades fast signup conversion for a deferred PM capture. The customer.subscription.trial_will_end webhook (PR-F) fires 3 days before trial end and triggers an email with a Stripe Checkout link (PR-G).

### 5. One Customer per Tenant

Enforced at the DB level by the partial UNIQUE index on billing_customers.tenant_id (PR-A). The HTTP endpoint takes no explicit tenant_id; it is implicit from the authenticated user.

### 6. Plan + Price resolution

The HTTP endpoint receives plan_id and interval. The backend resolves the concrete Price using customer.default_currency, preferring country-specific rows over the country=NULL fallback.

If no row matches: PriceNotFoundException → 422. If the Price's gateway_refs.stripe field is missing: PriceMissingGatewayMappingException → 422.

### 7. Initial subscription state row

TransitionSubscriptionAction (ADR-014) models transitions between statuses. The first row is a birth, not a transition. We insert it manually with from_status = to_status = 'trialing' and reason = 'created'. Analytics queries can filter WHERE reason != 'created' to see only real transitions.

### 8. HTTP exposure

Endpoints follow the project convention of /api/v1/ prefix with auth:sanctum and tenant.scope middleware: POST /api/v1/billing/customers and POST /api/v1/billing/subscriptions.

Authorization is via BillingPolicy: any user belonging to the tenant may manage that tenant's billing. We do not distinguish owner vs member at this layer.

Exception to HTTP mappings registered in bootstrap/app.php:

| Exception | HTTP | error code |
|---|---|---|
| PriceNotFoundException | 422 | price_not_found |
| PriceMissingGatewayMappingException | 422 | price_gateway_mapping_missing |
| CustomerNotRegisteredInGatewayException | 409 | customer_not_registered |
| GatewayValidationException | 422 | gateway_validation_failed |

## Consequences

**Positive:** Future write methods extend BillingGatewayWriter without touching read consumers. Idempotent retries are transparent for transient gateway/DB failures. Trial-aware signup with deferred PM capture maximizes conversion. The state machine (ADR-014) and partial UNIQUE index (PR-A) remain the sole sources of truth for subscription validity. The HTTP layer is thin: Controllers → FormRequests → Actions.

**Negative:** billing_idempotency_keys grows linearly with write throughput; cleanup job (PR-J) required. Failed gateway calls leave dangling rows with response_snapshot=NULL until TTL expires. Orphan gateway resources can accumulate in Stripe test mode during development.

## Alternatives considered

- Idempotency in Redis: faster but loses durability and audit trail. Rejected.
- Two-phase commit between DB and Stripe: correct but operationally complex. Saga+idempotency is the industry standard.
- Owner-restricted billing authorization: deferred. tenant_id match is sufficient; role gates can be layered later.
- Inline PM capture at customer creation: rejected for PR-E scope. Stripe Checkout post-trial (PR-G) achieves the same UX with less scope here.

## References

- ADR-014: Subscription state machine
- ADR-015: Gateway abstraction
- Stripe API docs (customers.create, subscriptions.create, idempotency keys)
- docs/billing/SPEC.md sections 5-6
