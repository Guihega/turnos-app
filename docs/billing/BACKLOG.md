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
