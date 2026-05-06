# Billing — Operational guide for secrets

Operational reference for billing-module credentials. Audience: developers
and ops people on the project. Lives next to `SPEC.md` so secrets handling
is part of the architecture record, not tribal knowledge.

---

## Inventory

The Billing module needs the following secrets per environment.
Variables prefixed with `STRIPE_*` are gateway-specific. Variables prefixed
with `BILLING_*` are gateway-agnostic and apply to the whole module.

| Variable | Required | Type | Notes |
|---|---|---|---|
| `STRIPE_ENABLED` | no | bool | Defaults to `true`. Set `false` to short-circuit GatewayResolver. |
| `STRIPE_MODE` | yes | string | `test` or `live`. Defaults to `test`. Selects which key set is read. |
| `STRIPE_TEST_PUBLIC_KEY` | yes (in test) | string | `pk_test_*`. Read when `STRIPE_MODE=test`. |
| `STRIPE_TEST_SECRET_KEY` | yes (in test) | string | `sk_test_*`. Server-side only. |
| `STRIPE_TEST_WEBHOOK_SECRET` | yes (in test) | string | `whsec_*` for test endpoint or Stripe CLI session. |
| `STRIPE_LIVE_PUBLIC_KEY` | yes (in live) | string | `pk_live_*`. Read when `STRIPE_MODE=live`. |
| `STRIPE_LIVE_SECRET_KEY` | yes (in live) | string | `sk_live_*`. Server-side only. |
| `STRIPE_LIVE_WEBHOOK_SECRET` | yes (in live) | string | `whsec_*` for the production webhook endpoint. |
| `BILLING_DEFAULT_GATEWAY` | no | string | Defaults to `stripe`. |
| `BILLING_WEBHOOK_TOLERANCE` | no | int | Seconds. Defaults to `300`. |
| `BILLING_WEBHOOK_IDEMPOTENCY_HOURS` | no | int | Defaults to `24`. |
| `BILLING_WEBHOOK_RETRY_MAX` | no | int | Defaults to `5`. |

**Per-environment rule:** in each `.env` file, only the active mode's keys
need to be filled. The other set can stay empty. The active set is
determined by `STRIPE_MODE`.

---

## Provisioning a Stripe account (first time)

1. Go to <https://dashboard.stripe.com/register> and create a Stripe account
   for the Olinora business.
2. Without activating the account (no business details required yet), Stripe
   gives you full access to **test mode**. The toggle in the top-right of
   the dashboard switches between test and live.
3. Stay in **test mode** while developing.
4. Settings → Developers → API keys. Reveal the secret key once and copy
   both keys to a temporary password-protected note.
5. Settings → Developers → Webhooks. Click "Add endpoint" once Phase 2 has
   defined the URL (typically `https://<env>.olinora.com.mx/webhooks/stripe`).
   Stripe will show a `whsec_*` value — copy it. **It is shown only once.**

---

## Local development

In `~/Proyectos/turnos-app/.env`:

```env
STRIPE_MODE=test
STRIPE_TEST_PUBLIC_KEY=pk_test_<your_key_here>
STRIPE_TEST_SECRET_KEY=sk_test_<your_key_here>
STRIPE_TEST_WEBHOOK_SECRET=whsec_<your_key_here>
```

The `STRIPE_LIVE_*` variables can stay empty in local — they will be
ignored because `STRIPE_MODE=test` selects the TEST set.

Verify Laravel reads the right keys:

```bash
php artisan tinker --execute="echo config('billing.gateways.stripe.mode');"
# expected output: test

php artisan tinker --execute="echo str_starts_with(config('billing.gateways.stripe.secret_key'), 'sk_test_') ? 'OK' : 'WRONG';"
# expected output: OK
```

For local webhook testing, use the Stripe CLI:

```bash
stripe listen --forward-to localhost:8000/webhooks/stripe
# the CLI prints a temporary whsec_* — paste that one in STRIPE_TEST_WEBHOOK_SECRET
```

The CLI's `whsec_*` is valid only for that listening session. On restart,
Stripe issues a new one. Use the dashboard-issued `whsec_*` only on real envs.

---

## Production

The project uses a plain `.env` file on the production server (no secret
manager — see `docs/billing/BACKLOG.md` for migration plan).

1. SSH into the production server.
2. Edit `/var/www/turnos-app/.env` (path may vary).
3. Set `STRIPE_MODE=live` and add the **live** keys:

   ```env
   STRIPE_MODE=live
   STRIPE_LIVE_PUBLIC_KEY=pk_live_<key>
   STRIPE_LIVE_SECRET_KEY=sk_live_<key>
   STRIPE_LIVE_WEBHOOK_SECRET=whsec_<key>
   ```

   The `STRIPE_TEST_*` variables can stay empty in production.
4. `chmod 600 .env` to ensure only the application user can read it.
5. Restart the Laravel queue and Horizon workers so the new config is
   picked up:

   ```bash
   php artisan config:clear
   php artisan queue:restart
   sudo supervisorctl restart horizon  # or: sudo systemctl restart horizon
   ```

Never commit a real `.env` to git. The `.gitignore` already excludes it;
keep it that way.

---

## Rotating keys

**Routine rotation** (recommended every 90 days for live keys):

1. In Stripe dashboard → Developers → API keys, click "Roll key" on the
   secret key. Stripe generates a new `sk_*` and revokes the old one
   after a 12-hour grace period.
2. Update the corresponding `STRIPE_<MODE>_SECRET_KEY` in the `.env` of
   every environment that uses live keys.
3. `php artisan config:clear` and restart workers.
4. Verify a smoke-test charge works in the new key (test mode equivalent
   if rolling test keys).

The webhook secret can be rotated independently:

1. In Stripe dashboard → Developers → Webhooks, click the endpoint, then
   "Roll signing secret".
2. Stripe gives a 24-hour overlap where both old and new secrets verify
   signatures — long enough to deploy without dropping events.
3. Update `STRIPE_<MODE>_WEBHOOK_SECRET` in `.env`, clear config cache,
   restart workers.

---

## Incident response — leaked key

If `STRIPE_LIVE_SECRET_KEY` is leaked (committed to git, posted in chat,
visible in a screenshot, etc.):

1. **Immediately** roll the key in the Stripe dashboard. Do not wait.
   Revocation is instant once you click roll.
2. Check Stripe dashboard → Developers → Logs for any unauthorized API
   calls in the last hour. If any are found, escalate to the founder
   immediately.
3. Update `.env` on all affected environments and restart workers.
4. If the key was committed to git, run BFG Repo-Cleaner or
   `git filter-branch` to scrub it from history. Force-push **must**
   coordinate with everyone holding clones; better to rotate and move on.
5. Open an incident report in `docs/incidents/<date>-stripe-key-leak.md`
   describing root cause, blast radius, fix, and prevention.

A leaked **publishable** key (`pk_*`) is lower-priority — by design it is
embedded in client-side code and only authorizes specific operations.
Rotate when convenient.

A leaked **webhook secret** (`whsec_*`) lets attackers forge fake events.
Roll in dashboard immediately, same urgency as a secret key leak.

A leaked **TEST** key (`sk_test_*`) is a low-impact event — test keys cannot
move real money. Rotate at next convenience.

---

## Future migration to a secret manager

This document assumes plain `.env`. When the project moves to a managed
secret store (AWS Secrets Manager, HashiCorp Vault, Doppler, 1Password
Connect, etc.), the migration plan is documented in
`docs/billing/BACKLOG.md` under "Secrets management".
