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
