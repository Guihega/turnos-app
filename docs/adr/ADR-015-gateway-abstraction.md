# ADR-015: Gateway Abstraction

- **Status**: Accepted
- **Date**: 2026-05-08
- **Deciders**: Guillermo Herrera (sole maintainer)
- **Related**: ADR-005 (entitlements decoupled), ADR-007 (webhooks), ADR-012 (webhook inbox), PR #8 (config & secrets)

## Context

PR-D introduces the boundary between application code and Stripe (and any future payment gateway). Three concrete questions had to be answered:

1. **Where does gateway-specific code live?** If `\Stripe\Customer` objects leak into controllers, actions, or tests, swapping or adding a gateway becomes a refactor rather than a feature flag.
2. **What does the boundary look like?** A single read-only contract for PR-D, extensible to write operations in PR-E.
3. **How are SDK errors mapped to domain errors?** Application code shouldn't need to know about `\Stripe\Exception\*` class names.

This ADR locks down those decisions before any consumer (PR-F webhook handler, PR-G processors, controllers in PR-E) is written.

## Decision

### 1. Boundary location: `App\Billing\`

Gateway-specific code is confined to one tree:

```
app/Billing/
├── Contracts/        ← interfaces consumed by app code
├── DTOs/             ← gateway-agnostic value objects
├── Exceptions/       ← domain exception hierarchy
├── Stripe/           ← Stripe-specific implementation (or other gateway)
│   ├── Concerns/     ← traits private to the adapter
│   └── *.php         ← StripeBillingGateway, StripeClientFactory, etc.
└── ...               ← future gateway adapters live as siblings to Stripe/
```

Anything outside `App\Billing\Stripe\` MUST NOT reference the Stripe SDK. Any `use Stripe\…` line outside that namespace is a code-review reject.

### 2. Read/write split: PR-D ships read-only

`BillingGateway` (PR-D) has only the methods needed to read state and verify webhooks:

- `retrieveCustomer(string $id): GatewayCustomer`
- `retrieveSubscription(string $id): GatewaySubscription`
- `retrieveInvoice(string $id): GatewayInvoice`
- `listPaymentMethods(string $customerId): array`
- `verifyWebhookSignature(string $payload, string $signature): array`

Write operations (`createCustomer`, `createSubscription`, `cancelSubscription`, `attachPaymentMethod`, …) belong to a complementary contract introduced in PR-E. Reasons for the split:

- PR-D is shippable without a Stripe account. Its tests run against mocks only; integration with the real API begins in PR-E.
- The write surface needs more thought: idempotency keys, customer creation flow, plan switching, proration policy. Locking those into PR-D would couple unrelated concerns.
- Most consumers (the webhook handler in PR-F, the dunning job in PR-I, the reconciliation job in PR-J) only need read access. They can be tested and shipped without the write contract existing yet.

The decision to keep them as separate interfaces (rather than one big `BillingGateway` with placeholders) is intentional: callers depend on the smallest interface they actually need (interface segregation). PR-E will define a `BillingGatewayWriter` interface; the Stripe adapter will implement both.

### 3. DTOs over SDK objects

Each gateway concept maps to a `final readonly class` DTO under `App\Billing\DTOs\`. The adapter constructs DTOs at the boundary; consumers see DTOs, never `\Stripe\Customer` or similar.

Rationale:

- **Decoupling.** Swapping or adding a gateway changes only the adapter, not controllers or actions.
- **Testability.** Building a DTO in a test is `new GatewayCustomer(...)`. Building a `\Stripe\Customer` in a test requires constructing or mocking the SDK's hierarchy, which is verbose and brittle.
- **Type narrowing.** SDK objects expose hundreds of optional fields. DTOs expose only the fields we use, and PHPStan can verify that.
- **Cost.** The mapping cost is paid once per resource type, in the adapter, with full unit-test coverage. Consumers are simpler.

DTOs preserve a `rawStatus` field where applicable (subscriptions, invoices) so the original gateway token survives even when no domain status maps cleanly.

### 4. Exception translation at the boundary

Every adapter method routes through a private `translateStripeExceptions(Closure $op)` helper (a trait) that catches the SDK's exception types and re-throws domain types:

| SDK exception                                          | Domain exception                                |
| ------------------------------------------------------ | ----------------------------------------------- |
| `\Stripe\Exception\SignatureVerificationException`     | `App\Billing\Exceptions\GatewaySignatureException`     |
| `\Stripe\Exception\AuthenticationException`            | `App\Billing\Exceptions\GatewayAuthenticationException` |
| `\Stripe\Exception\InvalidRequestException` (`resource_missing`) | `App\Billing\Exceptions\GatewayNotFoundException` |
| `\Stripe\Exception\InvalidRequestException` (other)    | `App\Billing\Exceptions\GatewayException`              |
| Any other `\Stripe\Exception\ApiErrorException`        | `App\Billing\Exceptions\GatewayException`              |
| Any other `\Throwable`                                 | `App\Billing\Exceptions\GatewayException`              |

The original SDK exception is preserved as `$previous` so observability tools can still surface Stripe-specific codes when needed.

Callers catch one of:

- `GatewayNotFoundException` — the resource doesn't exist; make a decision (404? upsert? skip?).
- `GatewayAuthenticationException` — credentials are wrong; alert ops, do not retry.
- `GatewaySignatureException` — webhook is forged or stale; drop and return 400.
- `GatewayException` — anything else; retry policy decides.

### 5. Status mapping

The `mapStripeStatus()` helper translates Stripe's `subscription.status` strings to `SubscriptionStatus::value`:

| Stripe status         | Domain status |
| --------------------- | ------------- |
| `trialing`            | `trialing`    |
| `active`              | `active`      |
| `past_due`            | `past_due`    |
| `paused`              | `paused`      |
| `canceled`            | `canceled`    |
| `unpaid`              | `suspended`   |
| `incomplete`          | `null` (preserve in `rawStatus`) |
| `incomplete_expired`  | `null` (preserve in `rawStatus`) |

The `pilot` status is purely a domain concept — a free 90-day trial without a payment method, not visible to Stripe. Pilot subscriptions are created locally with no Stripe counterpart; when they convert, a Stripe subscription is created and the local row's status is updated by the appropriate action. There is no reverse mapping for `pilot`.

### 6. Configuration

The adapter never reads environment variables directly. It reads `config('billing.stripe.*')`, which already encapsulates the test/live mode switch via `STRIPE_MODE` (PR #8). This indirection is what makes the adapter testable: a fake config repository in tests provides whatever values the test needs.

### 7. Container binding

`BillingServiceProvider` binds `BillingGateway` to `StripeBillingGateway`. There is no mode-switching binding (different gateway per tenant or environment) — that's not needed yet. When/if we add it, the binding becomes a closure that resolves a per-request gateway.

The provider also binds `StripeClientFactory` so tests can swap in a different factory and isolate the SDK configuration from the adapter under test.

## Consequences

### Positive

- One clear place to add support for a second gateway: a sibling directory under `App\Billing\`, a new implementation of `BillingGateway`, and (eventually) a different binding.
- Application code is testable without `Mockery` chains 4 levels deep into Stripe's class hierarchy.
- A junior reading a controller can see `retrieveCustomer(): GatewayCustomer` and understand the contract without learning the Stripe SDK first.
- SDK upgrades can change exception class names or method signatures without rippling through application code.

### Negative / accepted trade-offs

- Mapping is duplicated work: every new field of interest has to be added to the DTO, the mapping helper, and the test.
- DTOs are read-only snapshots; if a consumer needs to "reload" they must call the gateway again. (This is fine for PR-D's read-only scope; PR-E will introduce models that hold persistent state.)
- The exception trait sits between every adapter call and the SDK, which makes the call path one indirection deeper. Worth it for the uniformity.

### Future work (backlog)

- Per-call telemetry (request id, latency, status code) in a structured log.
- Optional read-through cache (per-request) for `retrieveCustomer` if PR-E hits the same customer repeatedly during a single request.
- Idempotency-key plumbing on the write contract (PR-E).
- Second gateway adapter for non-Stripe markets (BACKLOG.md item if/when needed).

## References

- ADR-005: entitlements decoupled from billing.
- ADR-007: webhook strategy.
- ADR-012: webhook inbox operations.
- PR #8: `config/billing.php` and secrets management.
- Stripe API version pinned at `2024-11-20.acacia` (PR #8).
