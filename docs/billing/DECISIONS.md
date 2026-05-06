# Olinora Billing — Architecture Decision Records (ADR)

> Cada decisión arquitectónica relevante del módulo Billing queda registrada aquí.
> Formato breve: contexto → decisión → consecuencias.
> Las decisiones NO se borran. Si se cambian, se agrega una nueva ADR que supersede a la anterior.

---

## ADR-001 — Sub-namespaces dentro de la estructura existente, no `app/Modules/Billing`

**Fecha:** 2026-04-30
**Estado:** Aceptada

### Contexto

Olinora ya tiene una estructura consolidada: `app/Models`, `app/Services`, `app/Actions`, `app/Repositories/{Contracts, Eloquent}`, `app/Policies`, `app/Events`, `app/Listeners`. Los tests siguen el patrón `tests/Feature/*Test.php` y `tests/Unit/*Test.php`. El equipo conoce esta convención.

Se evaluó introducir una estructura modular tipo DDD (`app/Modules/Billing/{Domain, Application, Infrastructure}`).

### Decisión

Mantener la estructura existente y añadir sub-namespaces específicos para Billing dentro de cada capa: `app/Models/Billing`, `app/Services/Billing`, etc.

### Consecuencias

- ✅ Cero choque cultural con el equipo y el código existente.
- ✅ Los devs nuevos no aprenden dos formas distintas de organizar código.
- ✅ Se mantiene la coherencia con el resto del proyecto (Branch, Ticket, Operator, etc.).
- ⚠️ Si en el futuro se decide extraer Billing a un microservicio, hará falta una refactorización de namespaces. Aceptable: el coste de extracción siempre es similar y no se gana nada haciéndolo "preventivamente".

---

## ADR-002 — Stripe como pasarela primaria

**Fecha:** 2026-04-30
**Estado:** Aceptada

### Contexto

Olinora opera en LATAM y necesita varias pasarelas (Stripe, Mercado Pago, OpenPay/Conekta, PayPal). Implementar todas en paralelo es la fuente principal de deuda técnica en proyectos similares.

### Decisión

Implementar Stripe primero, completo end-to-end, antes de tocar cualquier otra pasarela. Mercado Pago en Fase 4. OpenPay/Conekta y PayPal quedan en backlog.

### Razones

- Stripe tiene la mejor DX y documentación de webhooks.
- Soporta MXN, USD, COP, ARS, CLP y PEN nativamente.
- Stripe Tax simplifica el cálculo de impuestos en LATAM.
- Mercado Pago es obligado para Argentina y muy fuerte en MX/BR; entra en Fase 4 una vez probado el patrón con Stripe.
- OpenPay/Conekta solo aportan SPEI/OXXO localmente. Stripe ya cubre tarjetas y OXXO en MX vía partners.
- PayPal tiene UX inferior y menor adopción en B2B SaaS.

### Consecuencias

- ✅ Una sola pasarela completa antes de generalizar.
- ✅ El patrón Strategy + Adapter del primer adapter sirve como contrato para los siguientes.
- ⚠️ Tenants que prefieran MP no podrán suscribirse hasta Fase 4. Aceptable durante el MVP.

---

## ADR-003 — Plan `pilot` con expiración de 90 días

**Fecha:** 2026-04-30
**Estado:** Aceptada

### Contexto

El onboarding actual permite crear tenants gratis sin límite de tiempo. Esto:
- Genera tenants zombi que consumen recursos sin generar revenue.
- Dificulta el diseño de un modelo de billing limpio.
- Crea expectativa de "es gratis" en clientes que sí pueden pagar.

### Decisión

Convertir el onboarding gratis en un plan `pilot` con 90 días de duración. Al expirar:
- Día 90: `past_due` + email.
- Día 97: `suspended` (acceso bloqueado, datos preservados).
- Día 127: `canceled` (datos retenidos 90 días).
- Día 217: borrado lógico tras 90 días en `canceled`.

### Consecuencias

- ✅ Conversión esperada `pilot → paid` medible.
- ✅ Expectativas claras para el cliente desde el día uno.
- ✅ Reducción de tenants zombi.
- ⚠️ Tenants existentes en producción al momento del lanzamiento serán migrados a `pilot` con 90 días de gracia (ver `MIGRATION_PLAN.md`).
- ⚠️ Comunicación clara en la landing: "Prueba 90 días, sin tarjeta".

---

## ADR-004 — Cobro por tenant, no por sucursal, con metered para sucursales adicionales

**Fecha:** 2026-04-30
**Estado:** Aceptada

### Contexto

Tres modelos posibles:
1. Por tenant (un precio fijo, todas las sucursales incluidas).
2. Por sucursal (cada Branch suma al precio).
3. Híbrido (tier base con N sucursales incluidas + extras por uso).

### Decisión

Híbrido. El Tenant es la unidad de cobro. Cada plan incluye N sucursales. Si el Tenant añade más sucursales por encima de su límite incluido, esas sucursales adicionales se cobran como `usage` mensual.

### Consecuencias

- ✅ Pricing simple para tenants pequeños (1 sucursal): pagan tier base.
- ✅ Pricing escalable para clientes grandes: pagan extras solo por lo que usan.
- ✅ Coherencia con el modelo multi-tenant ya establecido.
- ⚠️ Requiere `billing_usage_records` y agregación por periodo (Fase 5).

---

## ADR-005 — Entitlements desacoplados del Plan

**Fecha:** 2026-04-30
**Estado:** Aceptada

### Contexto

La forma fácil es preguntar `if ($tenant->plan === 'professional')` en cualquier parte de la app. Esto crea acoplamiento entre el catálogo comercial y el código del producto: cualquier cambio de naming, lanzamiento, promo o grandfathering rompe código.

### Decisión

El producto consulta entitlements identificados por código (`whitelabel.full`, `branches.max`, etc.), no planes. Los entitlements se materializan en `billing_entitlements` al activar/cambiar suscripción y se reescriben al cambiar de plan.

### Consecuencias

- ✅ Cambiar precios o nombres de planes no requiere refactor de código del producto.
- ✅ Promos, grandfathering y planes custom (enterprise) son posibles sin if-else.
- ✅ El servicio `EntitlementService` es la única dependencia del producto hacia billing.
- ⚠️ Cuesta una tabla extra y un proceso de materialización. Vale la pena.

---

## ADR-006 — ULID en todas las tablas nuevas

**Fecha:** 2026-04-30
**Estado:** Aceptada

### Contexto

El proyecto ya usa ULIDs (`HasUlids` trait) en User, Branch, Ticket, etc. Mezclar ULID y autoincremental crearía inconsistencia.

### Decisión

Todas las tablas de billing usan ULID. Llaves primarias y foráneas como `ulid('id')`.

### Consecuencias

- ✅ Coherencia con el resto del proyecto.
- ✅ Multi-region friendly.
- ✅ No expone cardinalidad en URLs.

---

## ADR-007 — Webhook → persistir → procesar en job (nunca inline)

**Fecha:** 2026-04-30
**Estado:** Aceptada

### Contexto

Es la fuente número uno de bugs en SaaS: procesar lógica compleja dentro del controller del webhook, lo que causa timeouts, reintentos duplicados, estados inconsistentes.

### Decisión

Cada controller de webhook:
1. Valida la firma de la pasarela.
2. Inserta el evento crudo en `billing_webhook_events` (UNIQUE en `gateway_event_id` para deduplicar).
3. Encola un job (`ProcessWebhookEventJob`).
4. Responde 200.

El job lee el evento, procesa, marca como procesado. Falla → reintento exponencial → DLQ + alerta.

### Consecuencias

- ✅ Endpoints rápidos (< 200 ms).
- ✅ Reintentos seguros: la deduplicación evita procesar 2 veces el mismo evento.
- ✅ Replay de eventos posible vía comando artisan (`billing:webhook:replay`).
- ⚠️ Latencia entre recibir el webhook y reflejar el cambio (segundos). Aceptable.

> **Operational details:** ver ADR-012 para queue, retries, signature handling, retention y replay.

---

## ADR-008 — Reconciliación diaria BD vs pasarela

**Fecha:** 2026-04-30
**Estado:** Aceptada

### Contexto

Aun con webhooks, ocurren divergencias: webhooks perdidos, jobs fallidos en DLQ, errores manuales en la pasarela. Sin reconciliación, los problemas se descubren cuando un cliente reclama.

### Decisión

Job programado nocturno por pasarela. Compara:
- Subscriptions activas en BD vs en pasarela.
- Invoices `paid` en BD vs payments succeeded en pasarela.
- Discrepancias → registro en `billing_audit_log` + alerta Telegram + ticket auto.

### Consecuencias

- ✅ Detección proactiva de problemas.
- ✅ Trazabilidad completa.
- ⚠️ Coste computacional moderado (consultas a la pasarela). Aceptable.

---

## ADR-009 — CFDI 4.0 fuera del MVP

**Fecha:** 2026-04-30
**Estado:** Aceptada

### Contexto

CFDI 4.0 (factura fiscal MX) requiere integración con un PAC certificado y manejo de cancelaciones SAT. No es trivial.

### Decisión

El MVP emite **solo factura comercial en PDF**, sin valor fiscal. CFDI queda en backlog (Fase 6+ opcional).

### Consecuencias

- ✅ MVP llega a producción más rápido.
- ⚠️ Tenants en MX que requieran factura fiscal deberán esperar o pedirla manualmente.
- ⚠️ Riesgo comercial bajo durante el piloto y primeros meses.
- 📌 Cuando se implemente: usar Facturama o Solución Factible. Diseñar la generación CFDI como evento que se dispara al pasar Invoice a `paid`.

---

## ADR-010 — Outbox pattern para eventos de dominio

**Fecha:** 2026-04-30
**Estado:** Aceptada

### Contexto

Cuando un cambio en una entidad debe disparar efectos colaterales (notificaciones, sincronización, reportes), publicarlos directamente desde el código de aplicación introduce dos problemas:
1. Si la BD commit ok pero la publicación falla, se pierde el evento.
2. Si se publica antes del commit y el commit falla, hay efectos sin estado real.

### Decisión

Toda transición de estado relevante en Billing (Subscription activated, Payment failed, etc.) escribe en `billing_outbox_events` dentro de la misma transacción que escribe el cambio. Un job separado lee del outbox y publica eventos a sus consumidores. Marca como publicado al confirmar entrega.

### Consecuencias

- ✅ Consistencia at-least-once garantizada.
- ✅ Replay posible.
- ⚠️ Latencia de segundos entre cambio y notificación. Aceptable.
- ⚠️ Consumidores deben ser idempotentes.

> **Operational details:** ver ADR-013 para queue, scheduler, retries, retention y consumer model.

---

## ADR-011 — Auditoría inmutable por trigger PostgreSQL

**Fecha:** 2026-04-30
**Estado:** Aceptada

### Contexto

`billing_subscription_state_transitions` y `billing_audit_log` deben ser inmutables: un atacante o un bug nunca debe poder borrar/modificar registros de auditoría.

### Decisión

Aplicar trigger PostgreSQL `BEFORE UPDATE OR DELETE` que lanza excepción. Solo se permite INSERT.

### Consecuencias

- ✅ Garantía a nivel de motor de BD, no del código de aplicación.
- ✅ Resistente a bugs y a accesos administrativos.
- ⚠️ Cualquier corrección requiere disable temporal del trigger por un DBA. Procedimiento documentado en runbooks.

---

## ADR-012 — Operational defaults para webhook inbox (extiende ADR-007)

**Fecha:** 2026-05-05
**Estado:** Aceptada

### Contexto

ADR-007 firma el patrón **inbox transaccional** para webhooks (validar firma → persistir → encolar job). Sin embargo, deja sin firmar los detalles operativos: qué cola usar, cuántos reintentos, qué hacer cuando un evento falla repetidamente, cómo se purga el historial. Sin estos defaults, cada PR de Fase 2 tomaría decisiones distintas y el resultado sería inconsistente.

### Decisión

Los siguientes defaults aplican a TODOS los webhooks entrantes (Stripe ahora, Mercado Pago en Fase 4, etc.).

#### Cola dedicada

- **Cola:** `billing-webhooks`
- **Conexión:** `redis` (la default del proyecto en `.env`)
- **Prioridad:** primer lugar en el array de Horizon (procesamiento prioritario sobre `default` y `billing-outbox`).

#### Verificación de firma

- La firma de la pasarela se valida **ANTES** de persistir el evento.
- Si la firma es inválida: responde HTTP 400 sin tocar BD.
- Razón: evitar SQL bloat por requests forjados (ataque DoS).

#### Reintentos del job

- **Tries:** 5 (declarado en el Job vía `public int $tries = 5`).
- **Backoff:** `[3, 10, 30, 300]` segundos (3 inmediatos cortos, luego 30s, luego 5 min).
  Razón: la mayoría de fallos transitorios resuelven en segundos. Si el problema persiste 5 minutos, probablemente requiere intervención humana.
- **Timeout por intento:** 60 segundos (el default del worker en `horizon.php`).

#### Tras agotar reintentos

- El registro en `billing_webhook_events` recibe:
  - `processed_at` permanece `NULL`
  - `needs_review` = `true`
  - `last_error` = mensaje de la última excepción (truncado a 1000 chars)
- El job entra a `failed_jobs` (Horizon UI lo muestra).
- Se dispara una alerta vía Telegram (`TELEGRAM_ALERTS_ENABLED=true` en producción).

Razón para `needs_review` columna en lugar de DLQ formal: permite construir un panel de admin custom donde el ops team gestiona los eventos atascados sin tener que entender Horizon internamente. Trade-off: doble fuente parcial (Horizon failed_jobs + columna). Aceptable porque Horizon failed_jobs es transitorio (10080 min de retención), mientras que `needs_review` persiste.

#### Retención de eventos procesados

- Job nocturno (`billing:webhook:purge`) elimina `billing_webhook_events` con:
  - `processed_at IS NOT NULL`
  - `processed_at < now() - interval '90 days'`
- Eventos `needs_review = true` NUNCA se purgan automáticamente.
- Eventos sin procesar (`processed_at IS NULL` y `needs_review = false`) NUNCA se purgan automáticamente — indican un problema activo.

#### Replay

- Comando: `php artisan billing:webhook:replay --gateway=stripe --since=2026-05-01 [--event-id=evt_xxx]`
- Reprocesa eventos desde la fecha indicada (o un evento específico).
- Marca `replayed_at` en cada registro replayado para auditoría.
- Solo accesible vía CLI (no expuesto en UI), corre como SuperAdmin.

### Consecuencias

- ✅ Todos los webhooks futuros heredan los mismos defaults sin re-discutir.
- ✅ El admin panel custom es trivial de construir (lee `billing_webhook_events WHERE needs_review`).
- ✅ Replay es seguro y auditable.
- ⚠️ La cola `billing-webhooks` debe estar declarada en `horizon.php` para que los workers la procesen. Cubierto en este mismo PR.
- ⚠️ Las alertas Telegram requieren `TELEGRAM_BOT_TOKEN` y `TELEGRAM_CHAT_ID` configurados en producción (ya documentados en `.env.example`).

---

## ADR-013 — Operational defaults para domain outbox (extiende ADR-010)

**Fecha:** 2026-05-05
**Estado:** Aceptada

### Contexto

ADR-010 firma el patrón **outbox transaccional** para eventos de dominio salientes (Subscription activated, Payment failed). Sin embargo, no especifica frecuencia del publisher, manejo de fallos repetidos, ni quiénes son los "consumidores". Para evitar que cada PR de Fase 2 invente convenciones distintas, este ADR firma los defaults operativos.

### Decisión

#### Cola dedicada

- **Cola:** `billing-outbox`
- **Conexión:** `redis`
- **Prioridad:** segundo lugar en Horizon (después de `billing-webhooks`, antes de `default`).

#### Publisher schedule

- Job `PublishOutboxEventsJob` corre cada 30 segundos vía Laravel Scheduler:
  ```php
  Schedule::job(new PublishOutboxEventsJob)->everyThirtySeconds();
  ```
- Lee `billing_outbox_events WHERE published_at IS NULL AND failed_at IS NULL ORDER BY created_at LIMIT 100`.
- Razón: 30s da reactividad razonable sin sobrecargar Horizon. Stripe acepta latencias mucho mayores en confirmaciones a clientes externos.

#### Marcado de eventos

- **`published_at`:** `NULL` cuando el evento está pendiente; timestamp UTC al confirmar entrega exitosa.
- **`failed_at`:** `NULL` mientras el evento sea reintentable; timestamp al exceder los reintentos.
- **`attempts`:** contador incremental por cada intento de publicación.
- **`last_error`:** mensaje de la última excepción (truncado a 1000 chars).

#### Reintentos

- **Tries:** 3 (declarado en el Job).
- **Backoff:** `[60, 300, 1800]` segundos (1 min, 5 min, 30 min).
- Razón para 3 (vs 5 en webhooks): el outbox es interno (BD + Redis + jobs internos). Si falla 3 veces, es más probable un bug que un blip transitorio.

#### Tras agotar reintentos

- `failed_at` = timestamp UTC.
- Alerta Telegram con event ID y razón del fallo.
- El evento permanece en la tabla; un admin debe intervenir manualmente (re-encolar, corregir bug, descartar).

#### Consumidores

- Los consumidores son **jobs internos del módulo Billing** (otros jobs Laravel del mismo proyecto).
- **NO** se exponen eventos a servicios externos directamente vía outbox (eso sería una integración de webhooks salientes, fuera de scope del MVP).
- Cada consumidor debe ser idempotente: el outbox garantiza at-least-once delivery, no exactly-once.

#### Retención

- Job semanal (`billing:outbox:purge`) elimina eventos con:
  - `published_at IS NOT NULL`
  - `published_at < now() - interval '30 days'`
- Eventos `failed_at IS NOT NULL` NUNCA se purgan automáticamente — requieren resolución manual.

### Consecuencias

- ✅ Latencia máxima esperada entre commit en BD y entrega al consumidor: ~30 segundos.
- ✅ Replay manual es trivial: bastará con `UPDATE billing_outbox_events SET published_at = NULL, attempts = 0 WHERE id = ?`.
- ⚠️ El outbox NO es un sistema de pub/sub general del proyecto — solo aplica a eventos de Billing por ahora. Si en el futuro otros módulos lo necesitan, evaluar generalizar (`outbox_events` sin prefijo) en un ADR posterior.
- ⚠️ Los consumidores deben respetar idempotencia. Cualquier consumidor con efectos no idempotentes (ej. `SendEmailJob`) debe usar un mecanismo de dedupe propio (claves de idempotencia en Mailgun, etc.).

---

## Plantilla para nuevas ADRs

```
## ADR-NNN — Título corto

**Fecha:** YYYY-MM-DD
**Estado:** Propuesta | Aceptada | Superseded by ADR-XXX

### Contexto
(qué problema o pregunta motivó la decisión)

### Decisión
(qué se decidió, en una o dos frases)

### Consecuencias
(qué implica esto, ✅ positivas, ⚠️ negativas/trade-offs)
```
