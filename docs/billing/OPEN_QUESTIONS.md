# Billing — Open Questions

Preguntas técnicas con decisión pendiente. Cuando una pregunta se resuelva,
nace el ADR correspondiente y la entrada se elimina de este doc.

---

## Q1: Branch operational limits — scope (entitlement vs operational config)

**Estado**: Open
**Surgida en**: PR-U discovery (post PR-T)
**Bloquea**: `MIGRATION_PLAN.md` Fase G cleanup

### Pregunta

¿Los campos `branches.max_daily_tickets` y `branches.max_concurrent_waiting` son:

- **(A)** Configuración operativa per-sucursal que el admin del tenant ajusta
  libremente desde UI (capacidad real del local), o
- **(B)** Límites de plan que deben vivir en el catálogo de entitlements
  (Pilot = 100/día, Enterprise = 10k/día) y ser inmutables desde el lado del
  tenant?

### Contexto: deriva entre plan documentado y código actual

`MIGRATION_PLAN.md` (Fase F + Fase G) asume implícitamente **(B)**:
"Los entitlements pasan a ser autoritativos. Los límites de Branch hardcoded
dejan de leerse. Las columnas quedan deprecated y se eliminan en Fase G."

El código actual indica **(A)**:

- `app/Models/Branch.php` declara `max_daily_tickets` y `max_concurrent_waiting`
  como columnas fillable, consultadas directamente desde
  `Branch::canIssueTicket()` y `IssueTicketAction::validateBranchCanIssue()`.
  No hay dual-read con entitlements (patrón Fase C no aplicado a estos campos).
- `resources/js/Pages/Admin/Branches/Form.jsx` expone inputs numéricos
  editables (`min=1`, sin techo dependiente de plan) para que el admin
  configure estos límites por sucursal.
- `app/Http/Controllers/Admin/BranchController.php` valida
  `'max_daily_tickets' => 'nullable|integer|min:1|max:9999'` — rango operativo
  generoso, no derivado de plan.
- `database/seeders/Billing/{Features,Plans}Seeder.php` **NO declaran**
  features `branch.max_daily_tickets` ni `branch.max_concurrent_waiting`.
  El catálogo SÍ modeló `branches.max`, `operators.max`, `tickets.monthly`,
  pero deliberadamente omitió estos dos.
- PR-Q hasta PR-T construyeron la maquinaria completa de entitlements sin
  tocar ningún call site que consulte estas columnas.

### Lectura A — Operational config (consistente con código actual)

Estos campos son configuración operativa per-sucursal: la capacidad real del
local. Un negocio con 6 ventanillas configura 800 tickets/día; otro con 2
configura 200. **No son cobrables**, son operacionales.

Implicaciones:

- Las columnas se quedan, no son legacy.
- `MIGRATION_PLAN.md` Fase F está mal redactado en lo referente a estas
  columnas. Fase G debe reescribirse: el cleanup era solo para los campos
  que SÍ son entitlements (`branches.max`, `operators.max`, etc.).
- Existe duplicación a investigar: `tenant_settings.security.max_daily_tickets`
  (JSON) y `Branch.max_daily_tickets` (columna) comparten nombre. Resolver
  cuál es source of truth podría ser tarea separada.

### Lectura B — Plan-feature (consistente con plan documentado)

Estos campos eventualmente serán entitlements: el plan Enterprise dice
"hasta 10k tickets/día por branch", Pilot "hasta 50/día".

Implicaciones:

- Hay que agregar `branch.max_daily_tickets` y `branch.max_concurrent_waiting`
  al catálogo (`FeaturesSeeder` + `PlansSeeder` con valores per-plan).
- Hay que materializar entitlements para tenants existentes (data migration).
- Hay que wirearlo a `IssueTicketAction` / `Branch::canIssueTicket` /
  `KioskController` con dual-read pattern (Fase C), luego con enforcement
  (Fase F).
- La UI editable (`Branches/Form.jsx`) debe limitar el rango al valor del
  entitlement, o eliminarse.
- Fase G cleanup se ejecuta tal cual está escrita: drop columns post-60-días.

### Qué se necesita para decidir

Pregunta de negocio: **¿el plan Enterprise tiene como diferenciador
comercial "mayor capacidad de tickets por sucursal" respecto a Pilot?**

- Si sí → Lectura B.
- Si no → Lectura A.

No es una pregunta técnica. Solo el dueño del producto puede responderla.

### Mientras Q1 esté abierta

- **NO ejecutar `MIGRATION_PLAN.md` Fase G** en lo referente a estas dos
  columnas.
- Tratar los call sites afectados con cuidado: cualquier cambio en
  `IssueTicketAction`, `Branch::canIssueTicket`, o `KioskController` que
  toque estos campos debe primero resolver Q1.
- Los TODO comments en código apuntan a este documento.
