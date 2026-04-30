# Olinora Billing — Especificación Técnica

> **Estado:** Draft v1.0
> **Última actualización:** 2026-04-30
> **Owners:** Equipo Olinora
> **Audiencia:** Desarrolladores, QA, soporte, dirección técnica

Este documento es la fuente única de verdad para el módulo de facturación, suscripciones y cobros de Olinora. Cualquier desviación respecto a este SPEC requiere un ADR en `docs/billing/DECISIONS.md`.

---

## 1. Contexto del producto

Olinora es un SaaS multi-tenant de gestión de turnos / colas / atención presencial (clínicas, bancos, gobierno, retail). El cobro se realiza al **Tenant**, no al `User`. Un Tenant agrupa N sucursales (`branches`), N usuarios y N servicios.

### Resumen de decisiones

| Aspecto | Decisión |
|---|---|
| Unidad de cobro | Tenant |
| Modelo de pricing | Tiers fijos con sucursales incluidas + cobro por sucursal adicional (metered) |
| Onboarding gratis | Plan `pilot` con 90 días de expiración → fuerza upgrade |
| Pasarela primaria (Fase 2) | Stripe |
| Pasarelas siguientes | Mercado Pago (Fase 4); OpenPay/Conekta y PayPal en backlog |
| Multi-moneda | MXN, USD, COP, ARS, CLP, PEN |
| Facturación fiscal MX | NO en MVP. Solo PDF comercial. CFDI 4.0 queda en backlog |
| Estructura de código | Sub-namespaces dentro de la estructura existente; sin "modules" aparte |
| Identificadores | ULID en todas las tablas nuevas |
| Idempotencia | Obligatoria en toda escritura contra pasarela |
| Outbox pattern | Obligatorio para eventos de dominio |
| Auditoría | Inmutable por trigger PostgreSQL |
| Webhooks | Persistir antes de procesar; deduplicación por `gateway_event_id` |
| Reconciliación | Job nocturno BD vs pasarela |

---

## 2. Modelo de dominio

### Entidades núcleo

- **Customer** — espejo del Tenant en el contexto de billing. 1:1 con Tenant.
- **CustomerGatewayRef** — id del Customer en cada pasarela.
- **Plan** — definición comercial (pilot, starter, professional, business, enterprise).
- **Feature** — capacidad del producto (boolean | quota | metered | string).
- **PlanFeature** — entitlements base de cada plan.
- **Price** — precio concreto de un Plan en una moneda + país + intervalo.
- **Subscription** — suscripción activa de un Customer a un Plan/Price.
- **SubscriptionItem** — componentes de la suscripción (base + add-ons + metered).
- **SubscriptionStateTransition** — auditoría inmutable de cambios de estado.
- **Entitlement** — vista materializada por tenant que el resto de la app consulta en runtime.
- **UsageRecord** — registro inmutable e idempotente de consumo.
- **Invoice** — factura emitida (PDF comercial; CFDI fiscal queda fuera de MVP).
- **InvoiceLine** — línea de factura.
- **Payment** — intento de cobro contra una Invoice.
- **PaymentMethod** — método tokenizado (NUNCA PAN).
- **RefundRequest** — devolución con flujo de aprobación.
- **DunningAttempt** — intento de recuperación de cobro fallido.
- **WebhookEvent** — registro de todo webhook entrante (auditoría + idempotencia).
- **OutboxEvent** — eventos de dominio pendientes de publicar.
- **AuditLog** — bitácora de acciones administrativas.

### Diagrama de relaciones

```
Tenant (existente)
  └─ has_one  Customer
                ├─ has_many CustomerGatewayRef
                ├─ has_many Subscription (histórico, UNA active)
                │     ├─ has_many SubscriptionItem
                │     ├─ has_many SubscriptionStateTransition (inmutable)
                │     ├─ has_many Entitlement
                │     ├─ has_many UsageRecord
                │     └─ has_many Invoice
                │           ├─ has_many InvoiceLine
                │           ├─ has_many Payment
                │           └─ has_many DunningAttempt
                └─ has_many PaymentMethod
```

---

## 3. Catálogo de Planes

### `pilot` — onboarding gratis con expiración

- **Audiencia:** tenants nuevos del onboarding público.
- **Duración:** 90 días desde la creación.
- **Precio:** $0.
- **Sucursales incluidas:** 1 (sin posibilidad de añadir).
- **Operadores máximos:** 2.
- **Tickets/mes:** 500.
- **Features incluidas:** kiosco, display, atención básica, reportes 7 días.
- **Features excluidas:** white-label, multi-sucursal, API externa, anuncios multimedia, Telegram alerts, soporte prioritario.
- **Al expirar:**
  - Día 90: estado `past_due` + email de aviso.
  - Día 97: si no hay upgrade → `suspended` (acceso bloqueado, datos preservados).
  - Día 127: → `canceled` (datos retenidos 90 días para reactivación).
  - Día 217: borrado lógico tras 90 días en `canceled`.

### `starter`

- **Audiencia:** PyME con una sucursal.
- **Sucursales incluidas:** 1 (sin extras).
- **Operadores:** 5.
- **Tickets/mes:** 5,000.
- **Features:** pilot + reportes 90 días + branding básico (logo + color).
- **Precio sugerido:** $29 USD / $499 MXN al mes (anual con 2 meses gratis).

### `professional`

- **Audiencia:** clínicas medianas, retail multi-sucursal.
- **Sucursales incluidas:** 3, hasta 10 con extras.
- **Sucursal adicional:** $9 USD / $149 MXN cada una (metered).
- **Operadores:** 20.
- **Tickets/mes:** 25,000.
- **Features:** starter + white-label completo + anuncios multimedia + API + analytics avanzado + 2FA forzado a admins + Telegram alerts + soporte por email.
- **Precio sugerido:** $79 USD / $1,399 MXN al mes.

### `business`

- **Audiencia:** bancos, gobierno, cadenas grandes.
- **Sucursales incluidas:** 10, hasta 50 con extras.
- **Sucursal adicional:** $7 USD / $119 MXN cada una.
- **Operadores:** ilimitado.
- **Tickets/mes:** ilimitado.
- **Features:** professional + SLA 99.9% + soporte prioritario + onboarding asistido + auditoría avanzada + custom domain.
- **Precio sugerido:** $199 USD / $3,499 MXN al mes.

### `enterprise`

- **Audiencia:** contratos custom.
- `is_public = false`. Solo SuperAdmin lo asigna.
- Sin checkout público.
- Soporta features especiales (SSO, integraciones a medida, SLAs negociados).

> **Estos números son orientativos y se ajustan en el seeder antes del lanzamiento.** La estructura es lo permanente.

---

## 4. Mapeo de Features → Entitlements

### Tabla maestra

| Feature code | Tipo | pilot | starter | professional | business | enterprise |
|---|---|---|---|---|---|---|
| `branches.included` | quota | 1 | 1 | 3 | 10 | custom |
| `branches.max` | quota | 1 | 1 | 10 | 50 | custom |
| `branches.metered` | boolean | ❌ | ❌ | ✅ | ✅ | custom |
| `operators.max` | quota | 2 | 5 | 20 | -1 | -1 |
| `tickets.monthly` | quota | 500 | 5000 | 25000 | -1 | -1 |
| `reports.retention_days` | quota | 7 | 90 | 365 | -1 | -1 |
| `whitelabel.logo` | boolean | ❌ | ✅ | ✅ | ✅ | ✅ |
| `whitelabel.full` | boolean | ❌ | ❌ | ✅ | ✅ | ✅ |
| `whitelabel.custom_domain` | boolean | ❌ | ❌ | ❌ | ✅ | ✅ |
| `announcements.media` | boolean | ❌ | ❌ | ✅ | ✅ | ✅ |
| `api.access` | boolean | ❌ | ❌ | ✅ | ✅ | ✅ |
| `analytics.advanced` | boolean | ❌ | ❌ | ✅ | ✅ | ✅ |
| `alerts.telegram` | boolean | ❌ | ❌ | ✅ | ✅ | ✅ |
| `support.tier` | string | community | email | email-priority | priority | dedicated |
| `auth.2fa_required_admins` | boolean | ❌ | ❌ | ✅ | ✅ | ✅ |
| `audit.advanced` | boolean | ❌ | ❌ | ❌ | ✅ | ✅ |
| `sso.enabled` | boolean | ❌ | ❌ | ❌ | ❌ | ✅ |

> Convención: `-1` significa ilimitado en quotas numéricas.

### Cómo se consulta desde el resto de la app

El código del producto **NUNCA** pregunta por el plan. Pregunta por el entitlement.

```php
// MAL — acopla la app al catálogo comercial
if ($tenant->plan === 'professional') { ... }

// BIEN — desacoplado, sobrevive a cambios de pricing
if (Entitlement::for($tenant)->has('whitelabel.full')) {
    // ...
}

// Quotas numéricas
$max = Entitlement::for($tenant)->quota('branches.max');
$used = $tenant->branches()->where('is_active', true)->count();
abort_if($used >= $max, 403, 'Límite de sucursales alcanzado.');

// Strings
$tier = Entitlement::for($tenant)->string('support.tier');
```

---

## 5. Arquitectura técnica

### Stack

- Laravel 11 + PHP 8.2+
- PostgreSQL 16
- Redis (queues, cache, locks)
- Horizon (queue dashboard, ya en uso)
- Reverb (broadcast, ya en uso)
- CipherSweet (PII, ya en uso)
- Sanctum (auth API, ya en uso)

### Principios

1. **Sub-namespaces, no módulos aparte.** El código de billing vive dentro de la estructura existente del proyecto: `app/Models/Billing`, `app/Services/Billing`, etc.
2. **Pasarela ≠ dominio.** El dominio nunca conoce Stripe, Mercado Pago u otros. Habla con `PaymentGatewayInterface`.
3. **Strategy + Adapter para pasarelas.** Un `GatewayResolver` selecciona la pasarela según país, moneda o preferencia.
4. **Source of truth dual:**
   - Pasarela = verdad para transacciones.
   - BD propia = verdad para entitlements (lo que el usuario puede hacer).
5. **Idempotencia obligatoria** en toda operación que escribe contra una pasarela (`idempotency_key` UNIQUE en BD).
6. **Outbox pattern** para todo evento de dominio que cruza un boundary.
7. **Webhook → persistir → procesar en job.** Nunca lógica inline en el controller.
8. **Reconciliación diaria** BD vs pasarela. Discrepancias = alerta.
9. **Entitlements desacoplados del plan.** El producto consulta entitlements, no planes.
10. **Estados explícitos** mediante enums + máquinas de estado, no flags booleanos.

### Estructura de carpetas

```
app/
  Models/Billing/                  Customer, Plan, Price, Subscription, ...
  Actions/Billing/                 CreateSubscriptionAction, ChangePlanAction, ...
  Services/Billing/
    BillingService.php
    EntitlementService.php
    UsageService.php
    DunningService.php
    Gateways/
      Contracts/PaymentGatewayInterface.php
      Stripe/StripeGateway.php
      MercadoPago/MercadoPagoGateway.php
      GatewayResolver.php
  Repositories/
    Contracts/Billing/
    Eloquent/Billing/
  Events/Billing/                  SubscriptionActivated, PaymentFailed, ...
  Listeners/Billing/
  Jobs/Billing/                    ProcessWebhookEventJob, RetryFailedPaymentJob, ...
  Http/
    Controllers/
      Admin/Billing/               panel del tenant
      SuperAdmin/Billing/          panel global
      Webhooks/                    StripeWebhookController, ...
    Middleware/EnsureBillingActive.php
  Policies/Billing/
  Enums/Billing/                   SubscriptionStatus, InvoiceStatus, ...

config/billing.php
database/migrations/2026_05_*_billing_*.php
tests/Feature/Billing/
tests/Unit/Billing/
docs/billing/
```

---

## 6. Máquinas de estado

### Subscription

```
        ┌──────────┐
        │  pilot   │ ──────────┐
        └────┬─────┘           │
             │ trial_end       │ user upgrades
             ▼                 ▼
        ┌──────────┐     ┌──────────┐
        │ past_due │ ◄── │  active  │ ◄── (renewal succeeded)
        └────┬─────┘     └────┬─────┘
             │ dunning failed │ user pauses
             ▼                ▼
        ┌──────────┐     ┌──────────┐
        │suspended │     │  paused  │
        └────┬─────┘     └────┬─────┘
             │ N días         │ user resumes
             ▼                ▼
        ┌──────────┐     ┌──────────┐
        │ canceled │     │  active  │
        └──────────┘     └──────────┘
```

Estados: `pilot | trialing | active | past_due | suspended | paused | canceled`

Toda transición:
- Se valida contra una tabla de transiciones permitidas.
- Persiste un registro inmutable en `billing_subscription_state_transitions`.
- Dispara un evento de dominio que va al outbox.

### Invoice

`draft → open → paid`

Caminos laterales:
- `open → uncollectible` (tras dunning agotado)
- `open → void` (cancelación administrativa con motivo)

### Payment

`pending → requires_action? → processing → succeeded`
`processing → failed → retrying → failed_final`
`succeeded → refunded`

---

## 7. Flujos críticos

### Alta de suscripción

1. Frontend pide al backend un `checkout intent`.
2. Backend:
   - Crea Customer si no existe.
   - `GatewayResolver` escoge pasarela según país/moneda.
   - Crea sesión de checkout en la pasarela.
   - Devuelve URL/token al frontend.
3. Usuario paga en página hospedada de la pasarela (PCI SAQ-A: tu servidor jamás toca el PAN).
4. Pasarela envía webhook → tu app valida firma → guarda en `billing_webhook_events` → encola job.
5. Job procesa: crea Subscription en `pending`, dispara `SubscriptionPaymentReceived`.
6. Listener materializa Entitlements, transiciona a `active`, dispara `SubscriptionActivated`.
7. Otro listener envía email + notifica vía outbox al resto del sistema.

### Cambio de plan

- **Upgrade**: efecto inmediato. Prorrateo calculado por Olinora (no por la pasarela: cada pasarela calcula distinto y descuadra contabilidad).
- **Downgrade**: agendado al fin del periodo (`pending_change`). Sin pérdida de entitlements en el medio.

### Cobro fallido y dunning

1. Webhook `payment_failed` → Subscription a `past_due`.
2. Se programan reintentos día 1, 3, 7 como jobs con `unique` lock.
3. Cada intento notifica al usuario (email + Telegram si aplica).
4. Si los 3 fallan → `suspended` (entitlements revocados, datos preservados).
5. Tras 30 días en `suspended` → `canceled`.
6. Cada paso reversible si paga.

### Uso medido (sucursal extra)

1. Tenant activa una sucursal nueva por encima de las incluidas.
2. App emite `UsageReported($tenantId, 'branches.metered', 1, $idempotencyKey)`.
3. `UsageService` escribe en `billing_usage_records` (idempotente por key).
4. Job nocturno agrega al cierre de periodo y crea `invoice_lines`.
5. Nunca cobro en tiempo real por unidad: ineficiente y propenso a inconsistencias.

### Reconciliación diaria

Job programado:
- Compara subscriptions activas en BD vs activas en cada pasarela.
- Compara invoices `paid` en BD vs payments succeeded en pasarela.
- Discrepancias → registro en `billing_audit_log` + alerta a Telegram + ticket auto.

---

## 8. Seguridad y cumplimiento

### Obligatorio

- **PCI-DSS SAQ-A:** jamás guardar PAN. Toda captura de tarjeta vía elementos hospedados de la pasarela. La BD solo guarda tokens.
- **Cifrado en reposo (CipherSweet)** para `billing_email`, `billing_address`, `tax_id`.
- **Validación de firma en webhooks**, siempre. Si falla → 401 + log + sin procesar.
- **RBAC** para acciones administrativas:
  - Reembolsos: requiere `billing.refund.approve` + `RefundRequest` con flujo de aprobación.
  - Cambio manual de plan: requiere `billing.subscription.manage` + auditado.
  - Cancelación administrativa: requiere `billing.subscription.cancel` + motivo.
- **Auditoría inmutable** en `billing_audit_log` y `billing_subscription_state_transitions`. Triggers PostgreSQL impiden UPDATE/DELETE.
- **Rate limiting** en endpoints públicos de checkout y webhooks.
- **Secrets en gestor** (AWS Secrets Manager / Doppler / Vault), no en `.env` de prod.
- **Logs sin PII ni montos parciales.**

### En backlog (no MVP)

- CFDI 4.0 vía PAC certificado (Facturama, Solución Factible u otro).
- Facturación fiscal en otros países LATAM (AFIP en AR, DIAN en CO, SII en CL).
- SOC 2 Type II (después de tracción comercial).

---

## 9. Testing

### Pirámide

- **Unit (~60%):** lógica de dominio pura. Máquinas de estado, prorrateos, value objects (`Money`, `Currency`). Sin BD, sin HTTP. Rápidas.
- **Integration (~30%):** flujos completos contra PostgreSQL real. Pasarelas mockeadas con fakes que devuelven payloads reales capturados.
- **Contract (~10%):** contra sandbox real de cada pasarela. Corren en CI nightly, no en cada PR. Validan que el adapter sigue siendo compatible si la pasarela cambia.

### Reglas

- **Test de idempotencia obligatorio** para cada use case que escribe contra pasarela. Ejecutar 2× con misma `idempotency_key` debe dar el mismo estado final.
- **Fixtures de webhook reales:** capturar ejemplos de cada tipo de evento de cada pasarela y usarlos como fixtures.
- **Test de reconciliación:** simular divergencia BD vs pasarela y verificar detección + corrección/alerta.
- **Test de aislamiento de tenant:** ningún query de billing puede leer datos de otro tenant.

### Carpetas

```
tests/
  Feature/Billing/
    SubscriptionLifecycleTest.php
    StripeWebhookTest.php
    EntitlementTest.php
    UsageMeteredTest.php
    DunningTest.php
    RefundFlowTest.php
    GatewayResolverTest.php
    TenantIsolationTest.php
  Unit/Billing/
    StateMachine/SubscriptionStateMachineTest.php
    ValueObjects/MoneyTest.php
    ValueObjects/CurrencyTest.php
    ProrationCalculatorTest.php
```

---

## 10. Observabilidad y métricas

### Técnicas

- Latencia de procesamiento de webhooks (p50, p95, p99).
- Tasa de fallo de jobs (Horizon ya disponible).
- Eventos en DLQ.
- Lag del outbox (eventos no publicados después de N segundos).
- Discrepancias de reconciliación.

### De negocio

- MRR, ARR.
- Churn rate (logo y revenue).
- Dunning recovery rate.
- Failed payment rate por pasarela.
- Conversión `pilot` → paid.
- Tiempo medio de activación.

### Alertas

Vía Telegram (ya configurado en el proyecto) + email a SuperAdmin:
- Webhook DLQ no vacío durante > 5 minutos.
- Reconciliación con discrepancias.
- Fallos > 3% en cualquier pasarela en ventana de 1 hora.
- Outbox lag > 60 segundos.

---

## 11. Plan de fases

| Fase | Entrega | Estimación |
|---|---|---|
| **0 — Fundamentos** | docs, CI, Larastan, feature flags, milestones | 3-5 días |
| **1 — Catálogo y Customers** | Migraciones 1-5, modelos, seeders, panel SuperAdmin | 1 semana |
| **2 — Stripe end-to-end** | Adapter Stripe, checkout, webhooks, máquina de estados, dunning, reconciliación | 3-4 semanas |
| **3 — Entitlements + migración del estado actual** | Servicio Entitlement, backfill de tenants existentes, lectura dual con flag | 2 semanas |
| **4 — Mercado Pago** | Segundo adapter, GatewayResolver, contract tests | 2-3 semanas |
| **5 — Cobro por sucursales extra (metered)** | UsageRecord, agregación, factura con extras | 1-2 semanas |
| **6 — Hardening** | Métricas, dashboards, runbooks, alertas | 1-2 semanas |
| **Backlog** | OpenPay/Conekta, PayPal, CFDI 4.0, SSO empresarial | — |

**MVP en producción: ~3 meses con un dev senior full-time.**

---

## 12. Reglas duras (lo que NO se hace)

- ❌ No usar `laravel/cashier` para multi-pasarela.
- ❌ No mezclar `plan` y `price` en una sola tabla.
- ❌ No guardar montos en float ni sin currency.
- ❌ No procesar webhooks inline en el controller.
- ❌ No usar booleanos para estados (`is_active`, `is_paid`): usar enums + máquina explícita.
- ❌ No consultar el plan para autorizar features: consultar entitlements.
- ❌ No confiar en la pasarela como única fuente de verdad: reconciliar.
- ❌ No hacer reembolsos sin RBAC y auditoría.
- ❌ No desplegar billing sin alertas de DLQ y reconciliación.
- ❌ No implementar varias pasarelas en paralelo: una completa primero, luego replicar.
- ❌ No tocar `main` directamente: PRs contra `epic/billing`.

---

## 13. Glosario

- **Tenant:** entidad cliente de Olinora (dueña de sucursales y usuarios).
- **Customer:** representación del Tenant dentro del contexto Billing.
- **Plan:** definición comercial (pilot, starter, professional, business, enterprise).
- **Price:** precio concreto de un plan en una moneda + intervalo + país.
- **Subscription:** suscripción activa de un Customer a un Plan/Price.
- **Entitlement:** derecho concreto del Tenant a una feature, con su quota actual.
- **Feature:** capacidad del producto identificada por código (ej. `whitelabel.full`).
- **UsageRecord:** registro inmutable de consumo medido.
- **Dunning:** proceso de recuperación de cobros fallidos.
- **Gateway:** pasarela de pago (Stripe, Mercado Pago, etc.).
- **Outbox:** patrón para garantizar entrega de eventos de dominio.
- **Webhook:** notificación HTTP entrante desde una pasarela.
- **Idempotencia:** propiedad de una operación que produce el mismo resultado al ejecutarse N veces.

---

## 14. Mantenimiento de este documento

- Cualquier cambio estructural requiere ADR en `docs/billing/DECISIONS.md`.
- Cambios menores (precios, números, redacción): edición directa + commit `docs(billing): ...`.
- Antes de cada release mayor de billing, revisar este documento como parte del checklist.
