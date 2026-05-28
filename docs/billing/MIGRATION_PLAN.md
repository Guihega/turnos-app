# Olinora Billing — Plan de Migración del Estado Actual

> **Objetivo:** introducir el módulo Billing en producción sin romper a los tenants existentes ni al onboarding actual.
> **Estrategia:** expand-contract con feature flag.
> **Garantías:** cero downtime, cero pérdida de datos, posibilidad de rollback en cualquier fase.

---

## 1. Estado actual

- N tenants existentes en producción (todos sin suscripción asociada).
- El onboarding público crea tenants gratis sin límite temporal.
- Modelos `Tenant`, `Branch`, `User` con campos como `max_concurrent_waiting`, `max_daily_tickets` hardcoded en `Branch` (ver `OPEN_QUESTIONS.md` Q1 — la naturaleza "hardcoded" de estos dos campos en particular está bajo revisión).
- Sin tabla de suscripciones, sin tabla de planes, sin pasarelas integradas.

## 2. Estado deseado

- Cada Tenant existente tiene un Customer + Subscription en `billing_subscriptions` (todos en plan `pilot` con 90 días desde la migración).
- Los nuevos tenants entran al mismo plan `pilot` automáticamente.
- Los límites por sucursal vienen de Entitlements del Plan, no del modelo Branch.
- El producto consulta entitlements para autorizar features.
- Existe un proceso documentado y automatizado para upgrade/downgrade.

## 3. Principios de migración

1. **Cero downtime.** Nada de "ventanas de mantenimiento". Las migraciones agregan tablas, no rompen las existentes.
2. **Backward compatible.** Mientras `billing.enforcement.enabled = false`, la app se comporta exactamente como hoy.
3. **Reversible.** Cada fase tiene un plan de rollback.
4. **Observable.** Cada fase tiene métricas y alertas que confirman que no rompió nada.
5. **Comunicado.** Los tenants existentes reciben emails informativos antes de cada cambio.

## 4. Feature flag maestro

```
billing.enabled               → activa el módulo (rutas, controllers, jobs)
billing.enforcement.enabled   → activa los entitlements como autoritativos
billing.gateway.stripe        → permite checkout vía Stripe
billing.gateway.mercadopago   → permite checkout vía Mercado Pago
billing.notifications.enabled → emails de aviso de expiración del pilot
```

Mientras `billing.enforcement.enabled = false`:
- La app sigue usando los límites hardcoded de Branch.
  - **Nota (PR-U):** la categorización de `max_daily_tickets` y `max_concurrent_waiting` como "hardcoded a deprecar" está sujeta a `OPEN_QUESTIONS.md` Q1. El resto de los límites hardcoded (`branches.max`, `operators.max`, `tickets.monthly`) no están en duda.
- Los entitlements se materializan pero no se aplican.
- Útil para verificar que la materialización es correcta antes de activarla.

## 5. Fases de migración

### Fase A — No-op (semana 1)

**Cambios:**
- Crear todas las tablas `billing_*` vacías.
- Instalar Pennant. Definir flags. Todos en `false`.
- Crear seeders de Plans, Features, PlanFeatures, Prices.

**Impacto en producción:** ninguno. Ningún flag activado, ningún proceso nuevo corriendo.

**Verificación:**
- Las migraciones corren sin error.
- Las tablas existen pero vacías (excepto catálogo seedeado).
- Los tests existentes siguen pasando.

**Rollback:**
- `php artisan migrate:rollback --step=N`.
- Borrar el código deployado. Sin afectación a usuarios.

---

### Fase B — Backfill silencioso (semana 2)

**Cambios:**
- Comando artisan `php artisan billing:backfill-existing-tenants`.
- Para cada Tenant existente:
  - Crea `billing_customers` con datos del Tenant.
  - Crea `billing_subscriptions` con plan `pilot`, status `pilot`, `trial_end = now() + 90 days`.
  - Materializa `billing_entitlements` con los valores del plan `pilot`.
- Hook en el flujo de Onboarding actual: tras crear el Tenant, dispara el mismo proceso (Customer + Subscription `pilot`).

**Impacto en producción:** ninguno visible para los tenants. Los nuevos tenants reciben Subscription `pilot` automáticamente.

**Verificación:**
- Cada Tenant en BD tiene exactamente un Customer y una Subscription activa.
- Los entitlements coinciden con la tabla maestra del SPEC.
- Crear un tenant nuevo desde la landing → verificar que entra como `pilot`.
- `billing.enforcement.enabled` sigue en `false`.

**Rollback:**
- Comando inverso: `php artisan billing:rollback-backfill`.
- Borra registros de `billing_*` (excepto catálogo). Tenants intactos.

---

### Fase C — Lectura dual (semanas 3-4)

**Cambios:**
- `EntitlementService` se invoca en los puntos de control:
  - Crear sucursal: chequea `branches.max`.
  - Crear usuario: chequea `operators.max`.
  - Emitir ticket: chequea `tickets.monthly`.
  - Mostrar logo personalizado: chequea `whitelabel.logo`.
  - Etc.
- Modo dual: si el entitlement existe **y** `billing.enforcement.enabled = true`, se aplica. Si no, fallback al comportamiento actual.
- Logs de "entitlement check" para cada llamada → permite ver qué pasaría si activaras enforcement, sin activarlo.

**Impacto en producción:** ninguno (flag en `false`). Solo se generan logs.

**Verificación:**
- Revisar logs durante una semana. Identificar tenants que excederían sus límites si enforcement estuviera activo.
- Ajustar plan de comunicación a esos tenants (oferta de upgrade, ampliación de pilot, etc.).
- Tests Feature: con flag activado, los límites bloquean correctamente.

**Rollback:**
- Bajar el flag (ya está en false, así que no hace falta).

---

### Fase D — Notificaciones del pilot (semana 5)

**Cambios:**
- Job programado diario: `NotifyPilotExpirationJob`.
- Envía email a:
  - `trial_end - 30 días`: aviso suave.
  - `trial_end - 15 días`: oferta + CTA upgrade.
  - `trial_end - 7 días`: aviso de proximidad.
  - `trial_end - 1 día`: último aviso.
- Activar `billing.notifications.enabled = true`.

**Impacto en producción:** los tenants empiezan a recibir emails informativos. Comunicación previa en la landing y en el dashboard del tenant.

**Verificación:**
- Métricas de open/click rate de los emails.
- Tickets de soporte abiertos por confusión sobre el pilot.

**Rollback:**
- Bajar `billing.notifications.enabled`.

---

### Fase E — Stripe en producción para nuevos pagos (semanas 6-9)

**Cambios:**
- `billing.gateway.stripe = true` (en producción).
- Endpoint de checkout activo en el dashboard.
- Webhooks de Stripe configurados en el dashboard de Stripe apuntando a producción.
- Reconciliación nocturna activa.

**Impacto en producción:** los tenants pueden hacer upgrade de `pilot` a un plan pagado. Los que no upgradean siguen en `pilot`.

**Verificación:**
- Primer pago real: hacerlo internamente con un tenant de staging cualquiera.
- Métricas de webhook latency, fallos, DLQ.
- Reconciliación de noche 1: discrepancias en cero.

**Rollback:**
- Bajar `billing.gateway.stripe`. Los pagos en curso quedan en su estado actual.
- Soporte manual para tenants ya pagados.

---

### Fase F — Enforcement activo (semana 10)

**Cambios:**
- `billing.enforcement.enabled = true`.
- Los entitlements pasan a ser autoritativos. Los límites de Branch hardcoded dejan de leerse.
- La columna `max_concurrent_waiting` y `max_daily_tickets` de Branch se mantienen pero ya no se consultan; quedan deprecated y se eliminarán en una migración posterior.
  - **Nota (PR-U):** este bullet asume que ambas columnas son legacy a migrar. El descubrimiento durante PR-U mostró que el código actual las trata como configuración operativa per-sucursal (UI editable en `Branches/Form.jsx`, sin features análogas en el catálogo de entitlements). Resolución pendiente en `OPEN_QUESTIONS.md` Q1.

**Impacto en producción:**
- Tenants con uso por encima de sus entitlements: bloqueo en próxima acción.
- Tenants en `pilot` cuya `trial_end` ya pasó: pasan a `canceled` en el próximo run del job.
  - **Nota (PR-T):** la versión original de este plan decía `past_due`. Se rectifica a `canceled` para alinear con **ADR-014 §4**: un `pilot` no tiene método de pago ni plan comprometido, por lo que `past_due` no aplica (no hay nada que reclamar). La matriz `ALLOWED` del state machine ya contempla `pilot → canceled` con actor `(J)` (job). El job que materializa esta transición es `CancelExpiredPilotsJob` (gated por `billing.trial_expiration.enabled`).

**Pre-requisitos para activar:**
- Fase D corriendo > 30 días: todos los tenants tuvieron al menos un mes de aviso.
- Métricas de Fase C: ningún tenant con uso "imposible de cumplir" sin acción tomada.
- Plan de soporte listo: contactos directos para tenants grandes.

**Verificación:**
- Métricas de bloqueos por entitlement.
- Tickets de soporte: si suben demasiado, considerar suavizar.
- MRR comienza a generarse.

**Rollback:**
- Bajar el flag. Todos vuelven a la lógica antigua. Cero pérdida de datos.

---

### Fase G — Cleanup (mes 4+)

**Cambios:**
- Eliminar columnas deprecated en `branches` (`max_concurrent_waiting`, `max_daily_tickets`) en una migración expand-contract.
  - **⛔ BLOQUEADO por PR-U / `OPEN_QUESTIONS.md` Q1.** No ejecutar este punto hasta que Q1 se resuelva. Si la resolución es Lectura A (campos son configuración operativa), este bullet se elimina del plan; si es Lectura B (campos son entitlements), se mantiene tal cual.
- Eliminar lógica de fallback en EntitlementService.
- Refactorizar tests para que dependan solo de entitlements.

**Pre-requisitos:**
- Enforcement activo > 60 días sin rollback.
- Cero código que aún consulte las columnas deprecated.
- `OPEN_QUESTIONS.md` Q1 resuelto. Si la resolución fue Lectura B, código de wiring entitlements (dual-read en `IssueTicketAction` / `Branch::canIssueTicket` / `KioskController`) ya en producción > 60 días.

---

## 6. Riesgos y mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| Backfill marca mal el `trial_end` | Baja | Alto | Tests del comando + revisión manual de N tenants tras correr |
| Tenant antiguo con uso muy por encima del pilot | Media | Medio | Fase C con logs; comunicación temprana; ofertas |
| Webhooks de Stripe perdidos durante migración | Baja | Alto | Reconciliación diaria detecta y avisa |
| Confusión de soporte por emails masivos | Alta | Bajo | Plantillas claras; canal dedicado durante el rollout |
| Cambio en pricing antes del lanzamiento | Alta | Bajo | Catálogo en BD, no en código; ajustable sin deploy |
| Bug en EntitlementService que bloquea acciones legítimas | Media | Alto | Lectura dual durante Fase C/D, telemetría, rollback con flag |

## 7. Métricas de éxito de la migración

- **Cero downtime** durante la migración.
- **Cero tickets de soporte** por bug del backfill.
- **>= 95%** de tenants existentes con backfill correcto al primer intento.
- **<= 1 hora** de latencia entre cambio en pasarela y reflejo en BD (tras Fase E).
- **0 discrepancias** en reconciliación nocturna durante la primera semana de Fase E.
- **>= 15%** de conversión `pilot → paid` en los primeros 90 días tras Fase F.

## 8. Comunicación con clientes

### Email 1 — Anuncio (1 semana antes de Fase D)

> Hola [nombre], queremos contarte que estamos formalizando los planes de Olinora. Tu cuenta actual entrará en un periodo de prueba de 90 días con todas las funciones de tu plan actual. Antes de que termine, podrás elegir el plan que mejor se ajuste. Sin sorpresas.

### Email 2 — 30 días antes de expirar pilot

> Hola [nombre], tu prueba de Olinora termina el [fecha]. Hemos preparado planes desde [precio] al mes. [CTA: Ver planes].

### Email 3 — 7 días antes

> Hola [nombre], te quedan 7 días de prueba. Si no eliges un plan antes del [fecha], tu cuenta quedará suspendida. Tus datos quedan a salvo y podrás reactivarla cuando quieras.

### Email 4 — Suspensión

> Hola [nombre], tu cuenta está suspendida. Reactívala eligiendo un plan: [CTA].

## 9. Runbook del backfill (operación)

```bash
# 1. Backup
pg_dump olinora_prod > backup_pre_billing_backfill_$(date +%Y%m%d).sql

# 2. Verificar conteo de tenants antes
php artisan tinker --execute="echo App\Models\Tenant::count();"

# 3. Dry run del backfill (sin escribir)
php artisan billing:backfill-existing-tenants --dry-run

# 4. Revisar el output. Confirmar.

# 5. Ejecutar real
php artisan billing:backfill-existing-tenants

# 6. Verificar resultados
php artisan billing:verify-backfill

# 7. Si algo falló:
php artisan billing:rollback-backfill
# Restaurar backup si es estrictamente necesario.
```

## 10. Checklist final por fase

Antes de pasar a la siguiente fase:

- [ ] Tests de la fase actual: 100% pasando.
- [ ] Métricas de la fase actual: dentro de rangos esperados.
- [ ] Tickets de soporte: ninguno crítico abierto.
- [ ] Backup de BD: hecho en las últimas 24h.
- [ ] Aprobación: confirmar con el equipo antes de avanzar.
- [ ] Comunicación: tenants informados con la antelación que la fase requiere.
- [ ] Rollback probado: en staging, antes de tocar producción.
