# ADR-014: Subscription State Machine

- **Status**: Accepted
- **Date**: 2026-05-07
- **Deciders**: Guillermo Herrera (sole maintainer)
- **Related**: ADR-011 (state transition immutability), ADR-012 (webhook inbox), ADR-013 (outbox)

## Context

PR-A introduced the `billing_subscriptions` table with a `status` column typed via the `App\Enums\Billing\SubscriptionStatus` enum, plus a `billing_subscription_state_transitions` audit table protected by an immutable PostgreSQL trigger (ADR-011). PR-A also created a partial unique index, `one_active_subscription_per_customer`, that prevents a customer from having more than one row in the active set `{pilot, trialing, active, past_due, paused}` simultaneously.

What PR-A intentionally did **not** define:

- Which `from → to` transitions are legal.
- Who can trigger which transition (user, webhook, scheduled job, admin).
- Whether app code or the database is the source of truth for transition validity.
- How idempotency is handled for retried jobs.
- How and when domain events are emitted.

This ADR closes those questions. The next PR (PR-C) implements the decisions here.

## Decision

### 1. Source of truth: app-level matrix, DB as defense-in-depth

The legal transitions are encoded in `App\Actions\Billing\TransitionSubscriptionAction`. The action consults the matrix below before touching the database. The DB-level partial unique index from PR-A acts as a final guard if app-level checks are bypassed (e.g. a manual SQL fix-up) — but the application is responsible for clear error messages and is the primary enforcer.

### 2. Where the logic lives: Action class

A single action, `App\Actions\Billing\TransitionSubscriptionAction`, owns the transition. Following the project convention (Actions pattern, see SPEC.md), the action exposes a single `execute()` method:

```php
public function execute(
    Subscription $subscription,
    SubscriptionStatus $to,
    string $reason,
    ?string $actor = null,
    array $metadata = [],
): Subscription
```

Reasons for choosing an Action over a service class or model methods:

- **Atomic use case.** A transition has one clear input and one clear effect.
- **Queue-friendly.** Webhook processors (PR-G) and the dunning job (PR-I) will invoke this from queued jobs; an action is trivially injectable.
- **Test boundary.** Unit-testing an action without a database is straightforward.
- **Convention.** The project already organizes domain operations under `app/Actions/`.

### 3. The transition matrix

Seven states from `SubscriptionStatus`. Rows are the current state, columns are the target. Cell legend:

- `—` prohibited. Calling `execute()` raises `InvalidStateTransitionException`.
- `✓` permitted. Annotation in parentheses indicates which actor type typically triggers it: `U` user, `W` payment-webhook, `J` scheduled job, `A` admin/support.

| from \ to     | pilot | trialing | active   | past_due | paused | suspended | canceled    |
| ------------- | :---: | :------: | :------: | :------: | :----: | :-------: | :---------: |
| **pilot**     |  —    | (U) ✓    | (W) ✓    |  —       |  —     |  —        | (U/A/J) ✓   |
| **trialing**  |  —    |  —       | (W) ✓    | (J) ✓    |  —     |  —        | (U/A) ✓     |
| **active**    |  —    |  —       |  —       | (W) ✓    | (U) ✓  |  —        | (U/A) ✓     |
| **past_due**  |  —    |  —       | (W) ✓    |  —       |  —     | (J) ✓     | (U/A) ✓     |
| **paused**    |  —    |  —       | (U) ✓    |  —       |  —     |  —        | (U/A) ✓     |
| **suspended** |  —    |  —       | (W/A) ✓  |  —       |  —     |  —        | (J/A) ✓     |
| **canceled**  |  —    |  —       |  —       |  —       |  —     |  —        |  —          |

Total: **16 permitted cross-state transitions, 26 prohibited cross-state transitions, 7 same-state cells** (the diagonal; not counted as either, handled separately — see §5). Cell-count check: 7 × 7 = 49 cells = 16 + 26 + 7. ✓

### 4. Per-row rationale

**pilot** (free 90-day trial, no payment method required)

- `→ trialing` (U): customer adds a payment method during the pilot, upgrading to a paid trial.
- `→ active` (W): customer adds a PM and pays immediately, skipping the trial. Driven by `invoice.paid` webhook.
- `→ canceled` (U/A/J): user abandons, admin cancels, or the nightly trial-expiry job cancels at day 90 with no conversion. Pilot has no payment method and no plan committed, so going to `past_due` makes no sense — there is nothing to dun. This matches Stripe's behavior for "trial without payment method": expiry leads directly to cancellation.

**trialing** (paid plan trial, payment method captured)

- `→ active` (W): trial ends, first charge succeeds.
- `→ past_due` (J): trial ends, first charge fails. Enters dunning.
- `→ canceled` (U/A): customer cancels during trial, or admin cancels.
- Not allowed to go back to `pilot`. There is no concept of "downgrade to free trial". Once a customer is on a paid trial, they either convert, fail to pay, or cancel.

**active** (the happy state)

- `→ past_due` (W): renewal charge fails.
- `→ paused` (U): customer-initiated pause. The subscription continues to occupy the customer's "active slot" (per the partial unique index from PR-A), so a customer cannot have a paused sub plus a new active sub simultaneously; they must cancel the paused one first.
- `→ canceled` (U/A): voluntary cancel or admin cancel.
- Not allowed to go directly to `suspended`. The state machine requires `active → past_due → suspended` so that the dunning flow always runs.

**past_due** (in dunning, access still granted per `grantsAccess()`)

- `→ active` (W): retry charge succeeds.
- `→ suspended` (J): dunning policy exhausted. PR-I defines the retry schedule and the threshold for transitioning here.
- `→ canceled` (U/A): customer cancels during dunning, or admin cancels.

**paused** (reversible inactive)

- `→ active` (U): customer reactivates. This is the **only** non-cancellation exit. Admins do not reactivate other customers' paused subscriptions; that is an organizational concern, not a state-machine one.
- `→ canceled` (U/A): cancel from paused state.

**suspended** (post-dunning, access blocked but data retained)

- `→ active` (W/A): late recovery. If the customer pays the outstanding invoice, a webhook moves them back to active. Admin can also force this when taking manual payment. This matches the Stripe default for `unpaid` subscriptions.
- `→ canceled` (J/A): a nightly job cancels suspended subscriptions after a configured retention window (defined in PR-I), or admin cancels manually.

**canceled** (terminal)

- No outgoing transitions. `SubscriptionStatus::isTerminal()` already returns `true` only for this state. To "reactivate" a canceled subscription, the application creates a new subscription row.

### 5. Idempotency policy: silent no-op on same-state

If `execute()` is called with `to === $subscription->status`, the action returns the subscription unchanged:

- No row is written to `billing_subscription_state_transitions`.
- No domain event is dispatched.
- No exception is raised.

This is required so that webhook processors and queued jobs can safely retry without conditional checks. If the action were strict, every caller would need a guard like `if ($sub->status !== $target)`, which is ceremony and a source of bugs.

The trade-off — that callers cannot use a same-state call to "force-record" anything — is accepted. Audit-only inserts to `state_transitions` should be done by a different code path if ever needed.

### 6. Atomicity

Every successful transition runs inside a `DB::transaction` closure:

1. Verify the transition is permitted by the matrix. If not, throw `InvalidStateTransitionException`.
2. Verify the partial-unique invariant (active-slot occupancy) at the application level. If violated, throw `ConcurrentActiveSubscriptionException`.
3. Insert a row into `billing_subscription_state_transitions` with `from_status`, `to_status`, `reason`, `context`, `transitioned_at`. The schema (defined in PR-A) does not have a dedicated `actor` column; the action folds the optional `actor` argument into the `context` JSON column under the `actor` key, alongside any caller-supplied metadata. Callers and listeners read `actor` from `context['actor']`.
4. Update `billing_subscriptions.status`.
5. Dispatch a `SubscriptionStateChanged` event using Laravel's `event()` helper, deferred to commit via `DB::afterCommit()` so listeners cannot observe a state that was rolled back.

If any step fails, the entire transaction rolls back. The dispatched event never fires when the transaction does not commit.

### 7. Domain events

PR-C introduces a thin event layer:

- An interface `App\Events\Billing\Contracts\SubscriptionDomainEvent` that all subscription events will implement. Stub for now; the outbox writer in PR-H will key off this interface to know what to persist.
- A concrete event `App\Events\Billing\SubscriptionStateChanged` carrying `{subscription_id, from, to, reason, actor, occurred_at, metadata}`. The event surface is intentionally decoupled from the database schema: it exposes `actor` and `metadata` as separate fields even though the `state_transitions` table folds them into a single `context` JSON column. Listeners and the future outbox writer (PR-H) consume the event shape, not the row shape.

PR-C does **not** write events to `billing_outbox_events`. Events go through Laravel's standard dispatcher only. PR-H connects the dispatcher to the outbox via a global listener on `SubscriptionDomainEvent`. This separation keeps PR-C's scope tight.

### 8. Errors

Two custom exceptions, both extending `\DomainException`:

- `App\Exceptions\Billing\InvalidStateTransitionException` — thrown when the matrix rejects a transition. Carries `from`, `to`, and the subscription ID for diagnostics.
- `App\Exceptions\Billing\ConcurrentActiveSubscriptionException` — thrown when transitioning into the active set would violate the unique constraint. Carries the customer ID and the existing active subscription ID.

Callers should catch these explicitly. The HTTP layer (in PR-E) maps them to 422 responses.

## Consequences

### Positive

- Clear contract: every transition either succeeds, is a silent no-op (same-state), or throws one of two named exceptions.
- Audit trail is automatic and immutable (per ADR-011 trigger).
- The active-slot invariant is enforced both at the app level (clear error) and at the DB level (last line of defense).
- New transitions are added by editing the matrix in one place.
- Domain events have a stable shape from day one; PR-H can implement the outbox writer without touching transition logic.

### Negative / accepted trade-offs

- Same-state calls are silently swallowed. Any future need for "audit-only" entries requires a different mechanism.
- The matrix lives in PHP, not in the database. Schema migrations to add a new state require both an enum update and a matrix update; tests catch the mismatch.
- `suspended → active` is allowed (recovery via late payment). If the product later wants suspended to be near-terminal, the matrix changes and existing suspended subscriptions remain unaffected.

## Implementation notes

The `TransitionSubscriptionAction` constructor takes no dependencies — all collaborators are resolved per-call (the `Subscription` model and Laravel's facades). This keeps the action stateless and trivially queueable.

For testing, the matrix is exposed as a `public const ALLOWED` static map keyed `[from-value => [to-values]]`, so tests can assert the matrix structure directly without parsing private state.

## References

- ADR-011: state transition immutability via PostgreSQL trigger.
- `App\Enums\Billing\SubscriptionStatus` (PR #5).
- `billing_subscriptions`, `billing_subscription_state_transitions` migrations (PR #11).
- Stripe billing lifecycle docs (referenced for industry-standard transition naming and behavior).
