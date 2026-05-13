# ADR-017 — Nightly reconciliation and cleanups

Status: Accepted (PR-J)
Date: 2026-05-13

## Context

Fase 2 cierra con dos huecos operativos que las decisiones previas dejaron asentadas pero sin implementar:

1. **Retention de tablas operativas**. `billing_outbox_events` y `billing_webhook_events` acumulan filas indefinidamente. ADR-012 fijó retention 90d para webhooks procesados; ADR-013 fijó 30d para outbox publicados. Ambas decisiones referencian un "job semanal" que nunca se escribió.

2. **Drift entre estado local y gateway**. Los webhooks son confiables pero no infalibles: un endpoint caído, un deploy que pierde una entrega, un cambio manual en el Dashboard de Stripe — todos producen drift. Sin un check periódico, el drift se descubre cuando un cliente reclama o un cobro falla por motivos opacos.

## Decision

Tres jobs nightly, schedulados en `routes/console.php`:

- **`PurgeOutboxEventsJob`** (03:30): elimina filas de `billing_outbox_events` con `published_at IS NOT NULL AND failed_at IS NULL AND published_at < now() - retention`. Filas con `failed_at` quedan intocadas — requieren resolución manual.
- **`PurgeWebhookEventsJob`** (04:00): mismo patrón sobre `billing_webhook_events` con `processed_at` + `needs_review = false`. Filas `needs_review = true` se preservan.
- **`ReconcileSubscriptionsJob`** (03:00): para cada Subscription local en estado no terminal con `stripe_subscription_id`, fetcha la sub del gateway y compara `status`. Logueá drift, no autocorrige.

### Detection-only reconciliation

La reconciliación NO transiciona estados ni modifica DB. Razones:

- **Audit cleanliness**: una transición automática vía reconciliación contamina `billing_subscription_state_transitions` con rows `reason='reconciliation'` que no corresponden a cambios reales del dominio. El historial pierde fidelidad.
- **Bypass del state machine**: forzar transiciones desde un job nightly puede saltar reglas de ADR-014 (e.g. matriz de transiciones permitidas, guards de active-slot). El state machine existe por algo.
- **Visibilidad operativa**: el drift suele tener una causa investigable (webhook perdido, deploy roto, intervención manual). Auto-corregir oculta el síntoma. Logueá WARN y dejá que un humano decida.
- **Baja frecuencia**: drift real es raro. Los webhooks usualmente alcanzan al estado correcto. Cuando el drift persiste, una persona debe mirar.

Si en el futuro se decide que algunos tipos de drift sí deben autocorregirse (e.g. terminal cancellations que Stripe ya confirmó), eso requiere un PR aparte con su propio ADR.

### Categorías de drift logueadas

- `billing.reconcile.drift.status_mismatch` — ambas partes tienen status mapeable pero distinto.
- `billing.reconcile.drift.unmapped_gateway_status` — el gateway reporta un status que no modelamos (e.g. Stripe `incomplete`). Se logea `rawStatus` para contexto.
- `billing.reconcile.drift.not_found` — la subscription ya no existe en el gateway. Indica borrado manual desde Dashboard u otra anomalía severa.

Errores transitorios del gateway (rate limit, timeout) se loguean como `billing.reconcile.gateway_error` y NO cuentan como drift — la siguiente corrida revisará la fila.

### Scope de subscriptions a chequear

Se reconcilan las subscriptions en `RECONCILABLE_STATUSES`: `pilot`, `trialing`, `active`, `past_due`, `paused`, `suspended`. `canceled` se excluye intencionalmente — es terminal y no puede driftear.

### Configurabilidad

- `billing.outbox.retention_days` (default 30, env `BILLING_OUTBOX_RETENTION_DAYS`)
- `billing.webhooks.retention_days` (default 90, env `BILLING_WEBHOOK_RETENTION_DAYS`)
- `billing.reconciliation.batch_size` (default 200, env `BILLING_RECONCILIATION_BATCH_SIZE`)

### Concurrencia y seguridad

Los tres jobs implementan `ShouldBeUnique` con `uniqueFor=3600s` y se programan con `withoutOverlapping(3600)` + `onOneServer()`. Cinturón y tirantes: un solo worker por tipo de job a la vez, incluso si el scheduler dispara doble (deploys, replicas).

## Consequences

- ✅ Las tablas operativas no crecen indefinidamente en producción.
- ✅ El drift se detecta en ≤24h, no cuando un cliente reclama.
- ✅ Los logs estructurados (`billing.reconcile.*`) son agregables en dashboards y alertables.
- ⚠️ Reconciliation hace N requests al gateway por noche. Con N ≈ subs activas y rate limit de Stripe (100 req/s burst), debería estar holgado, pero al escalar a miles de tenants conviene paginar o paralelizar. El `batch_size` configurable da el knob inicial.
- ⚠️ La política "drift logged, not corrected" requiere un proceso humano de triage. Si nadie revisa los logs, el drift acumula. Mitigación operativa: dashboard de Grafana o equivalente sobre los 3 mensajes `billing.reconcile.drift.*`.
- ⚠️ El test del job mockea `BillingGateway`. El smoke test contra Stripe real queda en `StripeConnectivitySmokeTest` (opcional, env-gated).

---
