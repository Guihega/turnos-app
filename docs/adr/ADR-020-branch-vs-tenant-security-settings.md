# ADR-020: Branch vs Tenant Security Settings Duality

- **Status**: Accepted
- **Date**: 2026-05-28
- **Deciders**: Guillermo Herrera (sole maintainer)
- **Related**: ADR-019 (branch operational limits), `KioskController`, PR-V, PR-W

## Context

ADR-019 estableció que `max_daily_tickets` y `max_concurrent_waiting` son **configuración operativa per-sucursal** (no entitlements, no plan-features). Pero el discovery de PR-V/PR-W reveló que esos mismos nombres viven en **dos lugares distintos** del modelo de datos:

1. **`Branch.max_daily_tickets` / `Branch.max_concurrent_waiting`** — columnas de la tabla `branches`. Configuradas por el admin del tenant vía `Branches/Form.jsx`. Una por sucursal.

2. **`tenant_settings.security.max_daily_tickets` / `.max_concurrent_waiting`** — claves dentro del JSON `settings` del tenant (vía `HasTenantSettings` trait). Una por tenant. Configurada vía `TenantSettings.jsx` con rangos `min:5, max:200` (concurrent) y `min:50, max:5000` (daily).

`KioskController` consulta **ambos lugares** y los combina con `min()`:

```php
$maxConcurrent = $security['max_concurrent_waiting'] ?? 50;  // tenant-wide
$branchMax = $branch->max_concurrent_waiting ?? 50;          // per-branch
$effectiveMax = min($branchMax, $maxConcurrent);
```

Hasta este ADR, la semántica de la combinación no estaba documentada. Un comentario obsoleto en `KioskController:111` afirmaba "branch limit takes precedence, tenant overrides as ceiling" — frase contradictoria que **no describe lo que el código hace** (`min()` no implementa precedencia de ningún lado; siempre gana el más restrictivo).

## Decision

Las dos columnas/claves coexisten **con semánticas diferenciadas y complementarias**:

### `tenant_settings.security.*` — Techo de seguridad tenant-wide

El admin del tenant define un **techo absoluto** que aplica a TODAS las sucursales del tenant. Es una guardrail de seguridad operacional ("ninguna sucursal de esta empresa debería aceptar más de N concurrent waiting"). Valores típicos: `max_concurrent_waiting: 50`, `max_daily_tickets: 500`.

### `Branch.max_*` — Capacidad operativa específica de la sucursal

El admin del tenant define la **capacidad real de cada sucursal** según su realidad física (ventanillas, personal, horarios). Puede ser **más restrictiva** que el techo del tenant, pero **nunca más permisiva**.

### Combinación: `min(branchMax, tenantMax)` — la más restrictiva gana

El `effectiveMax` es siempre el valor menor de los dos. Esto significa:

- Si `tenantMax = 50` y `branchMax = 30` → `effective = 30` (la sucursal pequeña es el cuello de botella).
- Si `tenantMax = 50` y `branchMax = 80` → `effective = 50` (el techo del tenant gana; la sucursal grande no puede exceder la política de seguridad).
- Si `tenantMax = 50` y `branchMax = null` → `effective = 50` (default de branch es `50`, mismo valor; no cambia).

Esto no es "precedence" de ningún lado. Es **principio de privilegio mínimo aplicado a capacidad operativa**: ninguna sucursal puede operar por encima del techo definido a nivel tenant.

## Rationale

Tres razones convergentes para mantener la dualidad en vez de consolidar:

1. **Granularidad operacional**. Un tenant con 50 sucursales no quiere configurar `max_daily_tickets` 50 veces. El setting tenant-wide cubre el caso por defecto. La columna `Branch` cubre las excepciones donde una sucursal específica tiene capacidad atípica (sea más grande o más chica).

2. **Política de seguridad vs realidad física**. El tenant define la política ("no aceptemos más de 200 concurrent waiting bajo ninguna circunstancia") en el nivel tenant. La realidad física de cada local ("esta sucursal solo tiene 2 ventanillas, máximo 20 concurrent") en el nivel branch. Son ejes ortogonales que se combinan.

3. **El comportamiento ya estaba implementado**. `KioskController:112-114` ya usa `min()` correctamente. Este ADR documenta lo que el código ya hace, no introduce comportamiento nuevo.

## Consequences

### Positivas

- ✅ Semántica documentada formalmente. Lectores futuros entienden que ambos lugares son intencionales y complementarios, no duplicación accidental.
- ✅ Comentario obsoleto en `KioskController` corregido en el mismo PR (`KioskController:111-113`).
- ✅ Cierra la deuda observacional mencionada en ADR-019 §Negativas.
- ✅ Patrón replicable: cuando llegue otro límite que tenga sentido configurar a dos niveles (tenant default + branch override), aplicar el mismo `min(branchMax, tenantMax)`.

### Negativas / trade-offs aceptados

- ⚠️ Duplicación nominal real (los dos campos comparten nombre exacto). Renombrar uno de los dos para hacer la distinción explícita (`tenant_settings.security.max_concurrent_waiting` → `tenant_security_ceiling.concurrent_waiting` o similar) sería más claro pero implica migración de schema/JSON con costo desproporcionado al beneficio. Aceptado tal cual está.
- ⚠️ El `?? 50` aparece duplicado en ambos lugares. Si en el futuro hay que cambiar el default (a `100` por ejemplo), hay que tocarlo en dos sitios. Aceptado: el default solo aplica cuando ambos lados son null, lo cual no debería pasar en tenants correctamente onboarded.
- ⚠️ Solo `KioskController` aplica el `min()`. `IssueTicketAction::validateBranchCanIssue` y `Branch::canIssueTicket` solo leen `$branch->max_daily_tickets` directamente, ignorando el tenant ceiling. Eso podría ser inconsistencia o decisión deliberada (límite diario es métrica de sucursal pura, sin ceiling tenant). **No se aborda en este ADR** — es operación con coherencia local: cada call site decide qué columna(s) consultar según su semántica. Si en el futuro se descubre que el límite diario también debería respetar el techo tenant, ese es un cambio aparte.

## Alternatives considered

### Alternative A — Consolidar en un solo lugar (rejected)

Eliminar `tenant_settings.security.*` y usar solo `Branch.*`, o viceversa.

**Why rejected**:

- Eliminar `tenant_settings.*`: pierde la capacidad de definir políticas tenant-wide. Cada sucursal queda independiente; un tenant grande no puede imponer policy uniforme sin tocar N sucursales una por una.
- Eliminar `Branch.*`: pierde la granularidad per-sucursal. Forzaría a todos los locales del tenant a operar con el mismo cap, ignorando diferencias físicas reales.
- Ninguna de las consolidaciones reduce la complejidad real del dominio; solo elimina expresividad.

### Alternative B — Renombrar para clarificar (deferred)

Renombrar `tenant_settings.security.max_concurrent_waiting` a algo como `tenant_security_ceiling.concurrent_waiting` para hacer la distinción nominal explícita.

**Why deferred** (not rejected):

- Mejora la legibilidad del código pero requiere migración del JSON `settings` de cada tenant existente, refactor de `TenantSettings.jsx` form, y actualización del trait `HasTenantSettings`.
- Costo/beneficio no justifica hacerlo ahora. Reservado para PR futuro si la duplicación nominal genera fricción medible.

## References

- ADR-019: Branch Operational Limits (`docs/adr/ADR-019-branch-operational-limits.md`)
- `app/Http/Controllers/KioskController.php:108-114` (combinación `min()` implementada)
- `app/Models/Concerns/HasTenantSettings.php:44-45` (defaults de tenant security)
- `resources/js/Pages/Admin/TenantSettings.jsx:760-767` (UI tenant ceiling)
- `resources/js/Pages/Admin/Branches/Form.jsx:558-561` (UI branch capacity)
- PR-V `#38` (resolved Q1 as operational config)
- PR-W (this PR): correct misleading comment + document duality
