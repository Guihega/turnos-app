# ADR-019: Branch Operational Limits

- **Status**: Accepted
- **Date**: 2026-05-28
- **Deciders**: Guillermo Herrera (sole maintainer)
- **Related**: ADR-014 (state machine), MIGRATION_PLAN.md Fase F + Fase G, PR-T, PR-U

## Context

PR-T discovery surfaced una discrepancia entre `docs/billing/MIGRATION_PLAN.md` y el código actual respecto a los campos `branches.max_daily_tickets` y `branches.max_concurrent_waiting`.

El plan documentado (Fase F + Fase G) los trataba como **límites de plan hardcoded** a ser migrados a entitlements y eventualmente eliminados. El código construido entre PR-Q y PR-T mostró un patrón distinto:

- Los `FeaturesSeeder` y `PlansSeeder` declaran `branches.max`, `operators.max`, `tickets.monthly`, `support.tier`, `branding.basic`, `whitelabel.full`, `api.access`, `analytics.advanced`, `trial.days` — pero **deliberadamente omiten** `branch.max_daily_tickets` y `branch.max_concurrent_waiting`.
- `resources/js/Pages/Admin/Branches/Form.jsx` expone inputs numéricos editables (`min=1`, sin techo derivado de plan) para que el admin del tenant configure estos límites per-sucursal.
- `app/Http/Controllers/Admin/BranchController.php` valida `'max_daily_tickets' => 'nullable|integer|min:1|max:9999'` — rango operacional generoso, no plan-driven.
- `IssueTicketAction::validateBranchCanIssue`, `Branch::canIssueTicket`, y `KioskController` leen estas columnas directamente sin aplicar el patrón dual-read de Fase C que SÍ se usó para los campos que sí son entitlements.

PR-U documentó esta discrepancia como Q1 (en un doc transitorio `OPEN_QUESTIONS.md`, eliminado en PR-V — ver historia en PR-U `#37`) con dos lecturas posibles:

- **Lectura A**: configuración operativa per-sucursal (capacidad real del local). No cobrable. Tenant admin decide.
- **Lectura B**: límite de plan inmutable desde el tenant. Pilot=50/día, Enterprise=10k/día. Cobrable.

La decisión es de producto/negocio, no técnica.

## Decision

**Estos campos son configuración operativa per-sucursal (Lectura A).**

`branches.max_daily_tickets` y `branches.max_concurrent_waiting` representan la **capacidad operativa real de cada sucursal** — cuántos turnos físicamente puede atender un local con N ventanillas y M operadores. No son límites cobrables. El admin del tenant los configura libremente desde UI según su realidad operacional.

Esto es **distinto** de los límites de plan modelados como entitlements:

- `branches.max` (cuántas sucursales puede tener el tenant) — entitlement, cobrable.
- `operators.max` (cuántos operadores en total) — entitlement, cobrable.
- `tickets.monthly` (volumen mensual agregado por tenant) — entitlement, cobrable.
- `branch.max_daily_tickets` / `branch.max_concurrent_waiting` (capacidad operativa de cada sucursal) — **NO entitlement, NO cobrable, columnas de `branches`**.

## Rationale

Cinco argumentos convergentes:

1. **El catálogo de features se construyó con intención clara**. Modeló todos los límites cobrables y deliberadamente omitió estos dos. Si la intención hubiera sido B, se habrían declarado en `FeaturesSeeder`.

2. **La UI editable refleja la intención de diseño**. `Form.jsx` con inputs numéricos abiertos (`min=1, max=9999`) no es código transitorio. Es código construido bajo el supuesto de que el admin configura libremente estos valores.

3. **Semántica del nombre**. Los entitlements usan namespace de feature (`branches.max`, `tickets.monthly`). Estos campos usan namespace de Branch (`max_daily_tickets` como columna directa). El proyecto distingue ambos espacios.

4. **Modelo comercial B2B SaaS de turnos/colas**. Los planes cobran por número de sucursales, operadores, y volumen agregado mensual — métricas que se correlacionan con valor entregado al tenant. La capacidad operativa de cada sucursal depende de la realidad física del local (ventanillas, personal), no del tier de plan. Cobrarle a Enterprise "más capacidad por sucursal" no tiene sentido comercial: si el local físico solo tiene 2 ventanillas, un Enterprise no atiende más turnos que un Pilot.

5. **Falta de impacto operacional durante PR-Q a PR-T**. La maquinaria completa de entitlements se construyó sin tocar estos call sites y nada se rompió. Eso indica que el sistema de entitlements y estos campos son **conceptualmente independientes** — no que el wiring está pendiente.

## Consequences

### Positivas

- ✅ Las columnas `max_daily_tickets` y `max_concurrent_waiting` se mantienen indefinidamente. No son legacy.
- ✅ La UI `Branches/Form.jsx` se mantiene tal cual. El admin sigue configurando libremente.
- ✅ Los call sites (`IssueTicketAction`, `Branch::canIssueTicket`, `KioskController`) consultan las columnas directamente — patrón apropiado.
- ✅ El catálogo de features queda alineado con los entitlements cobrables, sin features sintéticas.
- ✅ Decisión cerrada para futuros desarrolladores: estos campos son operacionales, no plan-features.

### Negativas / trade-offs aceptados

- ⚠️ `MIGRATION_PLAN.md` Fase F + Fase G requieren reescritura parcial (PR-V mismo se ocupa). La frase "los límites de Branch hardcoded dejan de leerse" se reformula para acotar a los límites que sí son entitlements.
- ⚠️ Persiste duplicación nominal con `tenant_settings.security.max_concurrent_waiting` y `.max_daily_tickets` (JSON). No se aborda en este ADR — es tarea separada (potencial ADR futuro o issue) que decide cuál de los dos lugares es source of truth o cómo se relacionan (ceiling vs default, ya parcialmente implementado en `KioskController:112-114`).

## Alternatives considered

### Alternative B — Plan-feature (rejected)

Modelar estos campos como entitlements: `branch.max_daily_tickets` y `branch.max_concurrent_waiting` en el catálogo, con valores per-plan (ej. Pilot=50, Starter=200, Professional=500, Business=2000, Enterprise=unlimited).

**Why rejected**:

- Requiere construir features que el catálogo deliberadamente no construyó.
- Convierte configuración operativa en límite cobrable, lo cual no se corresponde con el modelo comercial.
- Requiere refactorizar UI para limitar el rango al techo del entitlement, perdiendo la flexibilidad operacional del admin.
- Data migration para tenants existentes, con riesgo de bloquear sucursales que hoy operan por encima del nuevo techo.
- Sin business case claro que justifique cobrar por esto.

## References

- PR-T: Make Fase F activatable (`#36`)
- PR-U: Document Q1 open question on branch operational limits (`#37`)
- ADR-014: Subscription State Machine
- `docs/billing/MIGRATION_PLAN.md` Fase F + Fase G (post PR-V reescritura)
