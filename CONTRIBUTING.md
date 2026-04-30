# Contribuir a Olinora

Este documento define las convenciones de trabajo del repositorio. Es de lectura obligada antes del primer commit. Si algo no aplica o quieres cambiarlo, abre un PR a este archivo.

---

## 1. Estrategia de ramas

```
main                  ← producción, siempre desplegable
└── epic/billing      ← rama larga de la épica de facturación
    ├── feature/billing-foundations
    ├── feature/billing-catalog
    ├── feature/billing-stripe-adapter
    ├── feature/billing-webhooks
    └── feature/billing-entitlements
```

### Reglas

- **Nunca** se commitea directo a `main`. Siempre PR.
- **Nunca** se commitea directo a `epic/*`. Siempre PR desde una `feature/*`.
- Las features cortas se mergean a la `epic/*` correspondiente, no a `main`.
- La épica completa se mergea a `main` cuando esté detrás de feature flag y aprobada.
- `hotfix/*` puede ramificarse desde `main` y mergea de vuelta a `main` (con backport a las épicas activas).

### Naming convention

| Prefijo | Uso | Ejemplo |
|---|---|---|
| `epic/` | Ramas largas de épica | `epic/billing` |
| `feature/` | Funcionalidad nueva | `feature/billing-stripe-adapter` |
| `fix/` | Bug fix | `fix/ticket-prefix-overflow` |
| `chore/` | Refactor, deps, infra | `chore/upgrade-laravel-11.52` |
| `docs/` | Solo documentación | `docs/billing-runbooks` |
| `hotfix/` | Parche urgente desde main | `hotfix/2fa-bypass-cve` |

Usa `kebab-case`, evita acentos y caracteres especiales.

---

## 2. Convención de commits (Conventional Commits)

Formato:

```
<tipo>(<scope>): <descripción corta>

<cuerpo opcional, en imperativo, qué y por qué>

<footer opcional: BREAKING CHANGE, refs a issues>
```

### Tipos

| Tipo | Uso |
|---|---|
| `feat` | Nueva funcionalidad para el usuario |
| `fix` | Corrección de bug |
| `chore` | Refactor interno, deps, infraestructura |
| `docs` | Solo documentación |
| `test` | Solo tests |
| `refactor` | Cambio de código sin afectar comportamiento |
| `perf` | Mejora de rendimiento |
| `build` | Build system, CI |
| `style` | Formato (Pint), sin cambios funcionales |

### Scope

El módulo o área del proyecto. Ejemplos: `billing`, `auth`, `tickets`, `display`, `kiosk`, `analytics`.

### Ejemplos válidos

```
feat(billing): add subscription state machine
fix(billing): correct prorate calculation on plan change
chore(billing): add migration for invoices table
test(billing): cover webhook idempotency
refactor(tickets): extract notification dispatcher
docs(billing): document dunning flow
build(ci): add larastan to github actions
```

### Reglas

- Imperativo en presente: `add`, no `added` ni `adds`.
- Sin punto al final del subject.
- Subject ≤ 72 caracteres.
- Cuerpo opcional pero recomendado para cambios no triviales.
- Inglés (consistencia con la comunidad PHP/Laravel).

### Breaking changes

```
feat(billing)!: change subscription status to enum

BREAKING CHANGE: Subscription::status now returns SubscriptionStatus enum, not string.
Update all callers accordingly.
```

---

## 3. Pull Requests

### Antes de abrir un PR

- [ ] Ejecutar localmente: `vendor/bin/pint --test && vendor/bin/phpstan analyse && php artisan test`.
- [ ] Rebase sobre la rama destino: `git pull --rebase origin epic/billing`.
- [ ] Commits limpios y agrupados por intención (squash si hace falta).
- [ ] Branch al día con su upstream.

### Tamaño

- **Pequeño es mejor.** PRs entre 100 y 500 líneas son ideales.
- PR de > 1000 líneas: divide en partes lógicas, salvo casos justificados (migraciones grandes, generación automática).

### Plantilla de PR

```markdown
## Qué cambia
Resumen en 2-4 líneas de qué hace este PR.

## Por qué
Contexto del problema o motivación.

## Cómo
Decisiones técnicas relevantes. Trade-offs considerados.

## Cómo se prueba
Pasos para probar manualmente + cobertura de tests.

## Riesgos
Áreas que podrían romperse. Plan de rollback si aplica.

## Checklist
- [ ] Tests añadidos/actualizados
- [ ] Migraciones reversibles
- [ ] Documentación actualizada (si aplica)
- [ ] Pint/PHPStan pasan
- [ ] Sin secrets en el diff
- [ ] Si toca el módulo billing, lista las ADRs afectadas
```

### Reviews

- Mínimo 1 aprobación antes de mergear (configurado en branch protection).
- Quien revisa: lee, prueba si es UI/UX crítica, sugiere mejoras.
- Quien autora: responde a cada comentario, no marca como resuelto si la persona que lo abrió no confirma.
- Si hay desacuerdo técnico no resuelto en 24h, escalar (DM al lead) en lugar de mergear.

### Merge strategy

Por defecto **squash and merge** para mantener un historial lineal en `main` y `epic/*`. Excepciones:

- Migraciones que necesitan estar en commits separados por orden cronológico → **merge commit**.
- PRs muy pequeños (un fix de typo) → **rebase and merge**.

---

## 4. Política de migraciones de BD

Las migraciones son la fuente número uno de bugs en producción. Reglas duras:

1. **Una migración por PR.** Salvo el primer drop de tablas de un módulo nuevo.
2. **Reversibles siempre.** `up` y `down` ambas funcionando. Probar `migrate:refresh`.
3. **Idempotentes.** No asumir estado: usar `Schema::hasColumn`, `Schema::hasTable` cuando aplique.
4. **Naming explícito y cronológico:** `YYYY_MM_DD_HHMMSS_descripcion_clara_en_snake_case.php`.
5. **Expand-contract** para cambios destructivos en tablas en uso:
   1. Agregar nueva columna/tabla.
   2. Escribir a las dos.
   3. Backfill.
   4. Leer de la nueva.
   5. Borrar la vieja.

   Cinco PRs separados, cada uno seguro de revertir.
6. **No editar migraciones ya mergeadas a `main`.** Crear una nueva.
7. **Probar en staging** con datos similares a producción antes de cualquier migración no trivial.

---

## 5. Política de feature flags

- Toda funcionalidad del módulo billing detrás de un flag durante el desarrollo.
- Flags definidos en `config/billing.php` o vía `laravel/pennant`.
- Naming: `<modulo>.<area>.<accion>`. Ejemplos: `billing.enabled`, `billing.gateway.stripe`, `billing.enforcement.enabled`.
- Documentar cada flag nuevo en el SPEC del módulo afectado.
- Eliminar flags obsoletos en cada limpieza trimestral.

---

## 6. Tests

### Mínimos por PR

- **Feature** que toca lógica nueva: tests Feature que cubran el camino feliz + al menos un error path.
- **Bug fix:** test que reproduce el bug (que falla antes del fix y pasa después).
- **Refactor:** los tests existentes deben seguir pasando sin modificarse.

### Estructura

Seguir el patrón existente del proyecto:
- `tests/Feature/<Area>/<Caso>Test.php` para tests de integración.
- `tests/Unit/<Area>/<Clase>Test.php` para tests unitarios puros.

Para billing: `tests/Feature/Billing/`, `tests/Unit/Billing/`.

### CI

El CI corre Pint + PHPStan + PHPUnit en cada PR a `main` y `epic/*`. Ningún PR mergea sin ✅ verde, salvo override explícito del lead.

---

## 7. Política de secretos

- **Nunca** commitear secretos. Si pasa, rotarlos inmediatamente y abrir PR de remoción.
- `.env.example` actualizado con los nombres (sin valores) cuando se agreguen variables nuevas.
- Secretos de producción en gestor (AWS Secrets Manager / Doppler / Vault), no en `.env`.
- Local development: `.env.local` (gitignored).

---

## 8. Estilo de código

### PHP

- `declare(strict_types=1);` obligatorio en archivos nuevos.
- Type hints en parámetros, return types y propiedades.
- Pint con la configuración del proyecto (correr `vendor/bin/pint` antes del commit).
- PHPStan/Larastan nivel definido en `phpstan.neon`. No bajar el nivel; en su lugar, refactorizar o usar `@phpstan-ignore-line` con justificación en el commit.

### Naming

- Clases: `PascalCase`.
- Métodos y variables: `camelCase`.
- Constantes y enums: `SCREAMING_SNAKE_CASE` o `PascalCase` según contexto.
- Tablas y columnas: `snake_case`.
- Rutas: `kebab-case` con namespacing claro.

### Comentarios

- Código que explica *qué* hace: que se lea solo. Sin comentarios.
- Código que explica *por qué*: comentario obligatorio.
- Decisiones de diseño no obvias: ADR en `docs/<modulo>/DECISIONS.md`, no comentario.

---

## 9. Cómo arrancar localmente

```bash
git clone git@github.com:Guihega/turnos-app.git
cd turnos-app
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run dev   # en otra terminal
php artisan serve
```

Para correr el stack completo de desarrollo:

```bash
composer dev   # arranca server, queue, logs y vite en paralelo
```

---

## 10. Recursos

- `docs/billing/SPEC.md` — especificación del módulo de facturación.
- `docs/billing/DECISIONS.md` — ADRs del módulo.
- `docs/billing/MIGRATION_PLAN.md` — plan de migración de tenants existentes.
- Documentación de Laravel: https://laravel.com/docs/11.x
- Documentación de Stripe: https://stripe.com/docs/api
- Documentación de Mercado Pago: https://www.mercadopago.com.mx/developers
