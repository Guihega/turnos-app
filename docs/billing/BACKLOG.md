# Billing Backlog

Decisiones de scope diferidas. Cada entrada documenta qué se pospuso, por qué,
y qué se necesita para implementarlo cuando llegue su fase.

---

## Fase 5 — Metered billing: extra-branch

**Estado:** diferido en PR 6 (Fase 1).

**Decisión:** los seeders del catálogo (PR 6) NO incluyen el precio metered de
$9 USD por sucursal adicional sobre la cuota incluida del plan `professional`
(y `business`).

### Razón

1. **No existe consumidor todavía.** No hay motor que cobre por uso, ni
   contador de sucursales activas, ni reporte a Stripe via
   `usage_record.create`. Sembrar el precio sin motor es decoración.
2. **Schema actual no lo soporta limpio.** `billing_prices` tiene un
   `unique(plan_id, currency, country, interval, interval_count)` que
   colisiona con add-ons del mismo plan/moneda/intervalo, y carece de
   discriminador (`kind`, `nickname`) para distinguir suscripción base de
   add-on.
3. **Alcance correcto.** PR 6 es "seeders del catálogo base". Add-ons
   metered son un subsistema completo: tabla de uso, proration, reporte
   de uso a pasarelas. Mejor construirlos juntos en Fase 5.

### Modelo comercial documentado en SPEC

- Plan `professional`: 3 sucursales incluidas, hasta 10 totales,
  cada sucursal extra a **$9 USD/mes**.
- Plan `business`: 10 incluidas, hasta 50, precio extra a definir.

### Migración requerida en Fase 5

Dos cambios sobre `billing_prices`:

```php
Schema::table('billing_prices', function (Blueprint $table): void {
    $table->string('kind', 20)->default('subscription')->after('plan_id');
    // 'subscription' | 'addon'
    $table->string('nickname', 80)->nullable()->after('kind');
    // 'extra_branch', 'extra_operator', etc.
    $table->jsonb('metadata')->nullable()->after('gateway_refs');

    $table->dropUnique('price_unique_combination');
    $table->unique(
        ['plan_id', 'kind', 'nickname', 'currency', 'country', 'interval', 'interval_count'],
        'price_unique_combination',
    );
});
```

Backfill: todas las filas existentes quedan con `kind='subscription'`,
`nickname=null`, `metadata=null`. Sin cambios de comportamiento.

### Seeder a actualizar en Fase 5

`PricesSeeder` debe agregar las filas de add-on después de las filas
de suscripción base:

```php
Price::updateOrCreate(
    [
        'plan_id'        => $professional->id,
        'kind'           => 'addon',
        'nickname'       => 'extra_branch',
        'currency'       => 'USD',
        'country'        => null,
        'interval'       => BillingInterval::Month->value,
        'interval_count' => 1,
    ],
    [
        'amount_cents'   => 900,
        'tax_behavior'   => 'exclusive',
        'metadata'       => [
            'usage_type' => 'metered',
            'unit'       => 'branch',
            'description' => 'Sucursal adicional sobre las 3 incluidas (hasta 10).',
        ],
        'is_active'      => true,
    ],
);
```

Equivalente para `business` cuando se decida el precio.

### Otras dependencias de Fase 5

- Tabla `billing_usage_records` (event-sourced de uso por tenant/feature).
- Job de reconciliación diario de uso vs límites.
- Webhook handler para `invoice.created` que adjunte líneas metered.
- Tests de integración con Stripe Test Clock.

---

## Features documentadas en SPEC.md §4 pero NO sembradas en PR #6

**Estado:** la tabla maestra del SPEC define 17 features. PR #6 sembró 10.
Las 7 restantes quedan documentadas y se sembrarán en PRs futuros conforme
el producto las requiera.

### Listado y plan tentativo

| Feature code | Tipo | Razón de aplazamiento | Fase tentativa |
|---|---|---|---|
| `branches.metered` | boolean | Requiere infra de metered billing (ver sección Fase 5 arriba). | Fase 5 |
| `reports.retention_days` | quota | Producto aún no expone reportes con retención variable. Sembrar cuando exista la infra de retention en módulo Reports. | Post-MVP |
| `whitelabel.custom_domain` | boolean | Requiere DNS automation y validación TLS. Subsistema separado. | Fase 6+ |
| `announcements.media` | boolean | Feature de producto (anuncios multimedia en pantallas), no de billing. Sembrar cuando el módulo Display soporte media. | Post-MVP |
| `alerts.telegram` | boolean | **Decisión PR-Y (2026-05-28):** no gatear. Telegram permanece disponible globalmente, sin diferenciación por plan. | Cerrado |
| `auth.2fa_required_admins` | boolean | **Decisión PR-Y (2026-05-28):** no gatear. 2FA permanece forzado para admins en todos los planes (status quo). | Cerrado |
| `audit.advanced` | boolean | Requiere panel de auditoría avanzada (no MVP). | Fase 6+ |
| `sso.enabled` | boolean | Solo plan `enterprise`. Implementar cuando llegue el primer cliente enterprise. | Pull-driven |

### Cómo sembrar una de éstas en el futuro

`FeaturesSeeder` y `PlansSeeder` son idempotentes. Para incorporar una feature
del backlog:

1. Agregar entrada en `FeaturesSeeder::features()` con su `code`, `name`,
   `type` y `metadata`.
2. Agregar las filas correspondientes en `PlansSeeder::planFeatureMatrix()`
   para cada plan que la incluya.
3. Re-correr `php artisan db:seed --class=Database\\Seeders\\Billing\\BillingCatalogSeeder`.
   Idempotencia garantizada — no toca lo ya sembrado.
4. Agregar test en `CatalogSeedingTest` que valide el nuevo conteo
   esperado de features.
5. Eventualmente, actualizar la tabla maestra en `SPEC.md` §4 cambiando
   el estado de 📋 backlog a ✅ sembrada.

---

## Migración de secrets a gestor de secretos

**Estado:** diferido en PR #8 (Fase 1b). El proyecto usa `.env` plano por entorno.

### Razón

1. **Tamaño actual del equipo (1 dev solo).** Un secret manager agrega
   complejidad operativa sin beneficio en proyectos uni-personales.
2. **No hay multi-environment exigente.** Solo `local` y `production` por
   ahora. Staging entraría en Fase 2.
3. **El `.env` plano funciona bien con SSH en VPS dedicado.**

### Cuándo migrar

Trigger principal:
- Cuando el equipo crezca a 2+ devs con acceso a producción.
- Cuando se necesite rotación automática de secrets (auditorías SOC 2 lo exigen).
- Cuando entren ambientes adicionales (staging, preview).

### Opciones evaluadas

| Servicio | Pros | Contras |
|---|---|---|
| AWS Secrets Manager | Integración nativa con SDK; rotación automática; audit log; pricing low | Lock-in AWS; requiere IAM bien configurado |
| Doppler | Free tier generoso; UX excelente; agnóstico de cloud | Dependencia de tercero |
| HashiCorp Vault | OSS, self-hosted, full-featured | Operativa pesada |
| 1Password Connect | Integra con manager personal; rotación humana asistida | Pricing por seat |

**Recomendación tentativa:** Doppler para arrancar (free tier suficiente),
migrar a AWS Secrets Manager si se requiere SOC 2.

### Migración esperada

1. Provisionar el secret manager elegido.
2. Cargar todos los secrets actuales (Stripe, DB password, Reverb, etc.).
3. Actualizar `config/billing.php` y demás config files para leer desde
   el secret manager (vía package oficial o env var dinámica).
4. Eliminar los secrets de `.env`.
5. Documentar el procedimiento de rotación en `SECRETS.md`.

---

## CipherSweet de campos PII de billing

**Estado:** mencionado en SPEC §8 como "pendiente de migración follow-up".

### Razón de aplazamiento

CipherSweet ya está instalado en el proyecto y aplicado a campos PII de `User`
(email, phone, last_login_ip). Aplicarlo a `billing_customers.billing_email`,
`billing_customers.tax_id` y `billing_customers.billing_address` requiere:

1. Migración aditiva: agregar columnas blind-index correspondientes.
2. Casts en el modelo `Customer`.
3. Backfill de registros existentes (cifrar valores en plain).
4. Tests de roundtrip + búsqueda por blind-index.

Esto es ~1-2 días de trabajo. No bloqueante para Fase 2 — la integración con
Stripe puede hacerse con los campos en plaintext y después aplicar el cifrado.

### Cuándo aplicar

Al final de Fase 2 (cuando haya datos reales de billing en producción) o al
inicio de Fase 3 (junto con el backfill de tenants existentes).

### PR esperado

Branch: `feature/billing-pii-encryption`
Archivos:
- 1 migración aditiva (blind-index columns).
- Modificación de `Customer` model (casts).
- 1 comando artisan `billing:encrypt-existing-customers --dry-run`.
- Tests.
