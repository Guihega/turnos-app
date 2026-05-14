# ADR-018 — Billing UI: own implementation with Stripe Elements, gateway-agnostic by contract

**Fecha:** 2026-05-14
**Estado:** Aceptada
**Related:** ADR-002 (Stripe primary gateway), ADR-004 (per-tenant billing), ADR-005 (entitlements decoupled), ADR-015 (gateway abstraction), ADR-016 (write contract and create flows)

## Context

Billing infrastructure (Fase 2, PRs A-J) shipped a complete backend: state machine (ADR-014), gateway-agnostic contracts (ADR-015), write surface (ADR-016), webhook + outbox pipeline (PR-F/G/H), dunning (PR-I), reconciliation (PR-J). No user-facing UI exists yet.

For pilot launch (Nivel B), the tenant needs to:

1. View current subscription state (plan, status, next billing date, payment methods).
2. Change plan (upgrade/downgrade with mid-cycle proration).
3. Cancel subscription (self-service, immediate effect with period-end grace).
4. Manage payment methods (add, remove, set default, view multiple stored cards).
5. View invoice history with PDF download and resend.
6. Sign up to a plan via a public checkout flow (no auth required pre-conversion).

Three UI architectures were on the table:

1. **Stripe Customer Portal (hosted).** Stripe-managed pages at `billing.stripe.com`, limited branding (logo + brand colors + headline).
2. **Hybrid.** Custom UI for primary flows (signup, plan view), Customer Portal redirect for secondary flows (invoices, payment methods).
3. **Own UI.** Inertia + React pages (`.jsx`) inside the tenant admin panel, talking to Stripe via API and PCI-scoped flows via Stripe Elements.

Three product constraints drive the decision:

- **Branding "extension of the product"** (Bloque 1 of PR-N discovery). The UI must feel native to turnos-app, not Stripe-branded.
- **Multi-gateway future.** MercadoPago is planned for Sprint 3+ (Bloque 3). The UI must consume `BillingGateway` (the contract from ADR-015), not `StripeBillingGateway` (the concrete adapter). Any UI hosted by a single gateway breaks this.
- **B2B audience with self-service expectations** (Bloque 2). Plan changes, cancellation, and payment method management must be self-service total, without redirects to external domains during normal operations.

## Decision

### 1. Own UI, Stripe Elements for PCI scope

All billing UI is built as Inertia + React pages (`.jsx`) inside the tenant admin panel, following the existing convention (Vite + `@inertiajs/react`, `resources/js/Pages/`):

- `tenant/billing` — dashboard: current subscription, plan, next billing date.
- `tenant/billing/plan` — change plan flow with proration preview.
- `tenant/billing/payment-methods` — list, add, remove, set default.
- `tenant/billing/invoices` — invoice history with PDF download and resend.
- `tenant/billing/cancel` — cancellation flow with confirmation modal.
- `billing/checkout/{plan_slug}` — public signup, no auth required.

For card capture (signup new card, add second card), use Stripe **Payment Element** with the Payment Intents API. The Payment Element is an iframe rendered inside our page, styled with Stripe Appearance API to match the tenant's brand variables. PCI scope stays SAQ A (Stripe-hosted iframe, our server never sees raw card data).

**Not used:** Customer Portal (any flow), Stripe Checkout (hosted page), Embedded Checkout (semi-hosted), Card Element (deprecated by Stripe in favor of Payment Element).

### 2. Gateway-agnostic by contract, Stripe-specific by adapter

UI components consume the `BillingGateway` and `BillingGatewayWriter` contracts from ADR-015/016, not concrete `StripeBillingGateway` / `StripeBillingGatewayWriter`. Concrete bindings live in `BillingServiceProvider` and respect the `STRIPE_MODE` / `BILLING_DEFAULT_GATEWAY` selection.

For Stripe-specific UI elements (Payment Element iframe initialization, Stripe.js loader), encapsulate in dedicated React components (`Pages/Billing/Stripe/PaymentElement.jsx`, `Pages/Billing/Stripe/CheckoutForm.jsx`) that the gateway-agnostic UI imports conditionally based on the resolved gateway. When MercadoPago lands in Sprint 3+, equivalent components (`Pages/Billing/MercadoPago/Brick.jsx`) will exist as siblings, selected at render time by the gateway resolver.

The Action layer (`CreateCustomerAction`, `CreateSubscriptionAction`, etc.) already operates against `BillingGateway` contracts. No app-level code change required for multi-gateway support; only adapter swap.

### 3. Checkout flow: public, plan-driven, deferred payment method capture

Public route `GET /checkout/{plan_slug}` is unauthenticated. Visitor selects plan, fills out tenant signup form (org name, email, etc.), and the system creates the tenant + user + gateway customer + trialing subscription in one transaction (per ADR-016 §4: "Trial period — capture PM later").

Payment method is captured **after** signup, during the trial period, via the authenticated `tenant/billing/payment-methods` flow. This maximises conversion: tenants enter their data without a card form blocking them mid-funnel. Trial is the existing 14-day window from ADR-016 §4.

### 4. Polish target: functional MVP, no animations

Per Bloque 3 of discovery, UI prioritises functional correctness over polish. Tailwind defaults + project base styles (existing `ThemeProvider` from `resources/js/Theme/ThemeContext`). Error states clear and actionable. No micro-animations, no skeleton loaders, no progressive disclosure tricks. Iteration after pilot feedback.

### 5. Invoice PDF and resend

Stripe generates invoice PDFs automatically; we expose them via `invoice.invoice_pdf` URL on the gateway response. Resend uses `stripe.invoices.sendInvoice` (or equivalent on `BillingGatewayWriter` when extended). UI shows download button and resend button per invoice row.

### 6. Cancellation flow

Two-step: confirmation modal with consequence summary (effective date = period end, access until then), then call `BillingGatewayWriter::cancelSubscription` with `cancel_at_period_end: true`. State machine transitions to `canceled` happen via webhook (`customer.subscription.deleted` arrives at period end), not synchronously. UI surfaces "Canceled — access until {date}" status during the grace window.

### 7. Plan change with proration preview

Before committing a plan change, fetch a proration preview via `stripe.invoices.retrieveUpcoming` (extended on `BillingGateway` if not present). Show the user the prorated amount before they confirm. Commit calls `BillingGatewayWriter::changeSubscriptionPlan` (new method, scope of subsequent PR after PR-N).

### 8. HTTP exposure

New routes in `routes/web.php` (authenticated tenant) and `routes/web.php` (public for checkout). All actions behind FormRequest validation. CSRF protected (Inertia React adapter handles this). Rate-limited on public checkout (5/min/IP) per existing project conventions (see `routes/api.php` rate limits).

## Consequences

### Positive

- ✅ Full brand control. UI feels native to turnos-app.
- ✅ Gateway-agnostic. Adding MercadoPago in Sprint 3+ requires zero changes to the React pages, only adapter + Stripe-specific component swap.
- ✅ PCI scope minimised (SAQ A). Stripe Elements iframe captures cards; our backend never handles raw PAN/CVV.
- ✅ Self-service total. No redirects to external domains during normal billing operations.
- ✅ Reuses existing infrastructure. `BillingGateway`, `BillingGatewayWriter`, Actions, state machine, webhooks — all already wired. PR-O onwards only adds HTTP + UI layers.
- ✅ Public checkout decouples conversion from payment. Trial-deferred PM capture (already decided in ADR-016) maps naturally to deferred Payment Element flow.

### Negative / accepted trade-offs

- ⚠️ More UI code than Customer Portal hosted. ~5-7 days of UI work spread across follow-up PRs (estimate: PR-O public checkout 3-5 days, PR-P tenant management UI 5-10 days, PR-Q E2E 2-3 days).
- ⚠️ Maintenance: Stripe API evolves. The codebase must track breaking changes in API versioning. Mitigated by the `BillingGateway` contract isolating Stripe specifics.
- ⚠️ Branding burden on tenants. Tenants must provide logo/colors that render correctly in Stripe Elements appearance API. Default fallback to project base palette.
- ⚠️ No third-party "billing portal" recovery path. If our UI breaks, tenants cannot fallback to a hosted Stripe page (we never redirect there).

## Alternatives considered

### Alternative A — Stripe Customer Portal (rejected)

Stripe hosts the entire post-signup management UI at `billing.stripe.com/p/session/...`. Brand customisation limited to logo, brand colors, and a headline. Session URL is always `billing.stripe.com`. Sessions expire after 5 minutes of inactivity; not embeddable via iframe.

**Why rejected:**
- Branding "extension of the product" criterion fails. The redirect to `billing.stripe.com` plus Stripe-styled chrome breaks product cohesion.
- Multi-gateway future blocked. Customer Portal is Stripe-only; switching to MercadoPago for a subset of tenants would require a parallel hosted experience or hybrid UI, contradicting ADR-015's gateway abstraction goal.
- Self-service plan change with proration is supported but UX is Stripe-prescribed; we cannot inject the proration preview as required by Bloque 2.

### Alternative B — Hybrid (rejected)

Custom UI for primary flows (dashboard, plan change, cancellation), Customer Portal redirect for invoices and payment methods.

**Why rejected:**
- Inconsistent UX. Tenant sees branded pages and then a Stripe-branded page within the same session. Bloque 1 explicitly required "extension of the product" coherence.
- Multi-gateway blocker compounds: hybrid implies hosted Stripe Portal as the "secondary" flow surface, which has no MercadoPago equivalent.
- Implementation effort similar to full own UI (still need branded primary flows), benefit marginal.

### Alternative C — Stripe Checkout (hosted) for signup, own UI for management (rejected)

Public checkout uses Stripe Checkout (hosted page at `checkout.stripe.com`), management uses own UI.

**Why rejected:**
- Same branding problem at the signup step, which is the highest-stakes conversion moment.
- Inconsistent visual experience between checkout and post-signup management.
- Future MercadoPago checkout would need its own hosted page, breaking the unified `BillingGateway` abstraction at the most visible UI touchpoint.

### Alternative D — Embedded Checkout (rejected)

Stripe Embedded Checkout renders Checkout UI in a modal/page within the tenant's domain. More brand integration than hosted Checkout.

**Why rejected:**
- Inconsistent with the management UI: Embedded Checkout has a fixed UX flow (single-page, fixed sections) that does not match our intended public checkout (multi-step: plan select → org info → confirmation).
- Stripe Elements with Payment Intents API delivers equivalent PCI-off-loading with more layout control. Embedded Checkout's advantage is faster implementation, which is irrelevant given the management UI must be hand-built anyway.

### Alternative E — Stripe Connect Embedded Components (rejected as not applicable)

Connect Embedded Components allow embedding Stripe-managed UI for marketplace platforms (multi-account, e.g. Shopify-style).

**Why rejected (not applicable):**
- Designed for Connect platforms, not direct SaaS billing.
- Architecturally inappropriate: turnos-app bills tenants directly, not through a connected-account model.

## Implementation notes

These are scope-defining for the PRs that follow PR-N. They are not implementation requirements of this ADR.

- **PR-O: public checkout flow.** Route, FormRequest, controller, React pages, Payment Element integration. ~3-5 days.
- **PR-P: tenant management UI.** Dashboard, plan change, cancel, invoices, payment methods. ~5-10 days. May be split across multiple PRs by scope.
- **PR-Q: E2E tests with Stripe test mode.** Full flow covered: checkout → trial → PM capture → renewal webhook → cancellation. ~2-3 days.
- **Future PR (Sprint 3+):** MercadoPago adapter + `Pages/Billing/MercadoPago/Brick.jsx` component. UI pages remain untouched; only the resolved gateway changes.

The `BillingGateway` and `BillingGatewayWriter` contracts may need extension for:
- `retrieveUpcomingInvoice` (for proration preview).
- `changeSubscriptionPlan` (with proration_behavior parameter).
- `setDefaultPaymentMethod`.
- `detachPaymentMethod`.

These extensions go in PR-O / PR-P respectively, each with its own ADR amendment if the contract changes are significant.

## References

- ADR-015: Gateway Abstraction (`docs/adr/ADR-015-gateway-abstraction.md`).
- ADR-016: Billing write contract and create flows (`docs/adr/ADR-016-billing-write-contract-and-create-flows.md`).
- Stripe Payment Element documentation: https://docs.stripe.com/payments/payment-element
- Stripe Customer Portal limitations (B2B perspective): https://payrequest.io/blog/stripe-customer-portal-limitations-b2b-2026
- Discovery responses for PR-N: 3 bloques de respuestas del solo dev (B2B, branding alto, ES+EN, self-service total, MercadoPago en Sprint 3+, MVP > polish).
