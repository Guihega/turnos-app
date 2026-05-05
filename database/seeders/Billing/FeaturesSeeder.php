<?php

declare(strict_types=1);

namespace Database\Seeders\Billing;

use App\Enums\Billing\FeatureType;
use App\Models\Billing\Feature;
use Illuminate\Database\Seeder;

/**
 * Seeds the catalog of billing features (entitlements).
 *
 * Idempotent via Feature::updateOrCreate(code).
 * Re-running this seeder syncs metadata changes without duplicating rows.
 *
 * @see docs/billing/SPEC.md §4
 */
final class FeaturesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->features() as $feature) {
            Feature::updateOrCreate(
                ['code' => $feature['code']],
                [
                    'name' => $feature['name'],
                    'description' => $feature['description'],
                    'type' => $feature['type']->value,
                    'metadata' => $feature['metadata'],
                ]
            );
        }
    }

    /**
     * @return array<int, array{
     *     code: string,
     *     name: string,
     *     description: string,
     *     type: FeatureType,
     *     metadata: array<string, mixed>|null
     * }>
     */
    private function features(): array
    {
        return [
            [
                'code' => 'branches.included',
                'name' => 'Sucursales incluidas',
                'description' => 'Cantidad de sucursales activas incluidas en el plan sin cargo adicional.',
                'type' => FeatureType::Quota,
                'metadata' => ['unit' => 'branches'],
            ],
            [
                'code' => 'branches.max',
                'name' => 'Sucursales máximas',
                'description' => 'Tope de sucursales que puede tener el tenant (incluidas + extras). -1 = ilimitado.',
                'type' => FeatureType::Quota,
                'metadata' => ['unit' => 'branches'],
            ],
            [
                'code' => 'operators.max',
                'name' => 'Operadores máximos',
                'description' => 'Cantidad máxima de usuarios operadores. -1 = ilimitado.',
                'type' => FeatureType::Quota,
                'metadata' => ['unit' => 'operators'],
            ],
            [
                'code' => 'tickets.monthly',
                'name' => 'Tickets mensuales',
                'description' => 'Tickets generados por mes. Se reinicia cada ciclo. -1 = ilimitado.',
                'type' => FeatureType::Quota,
                'metadata' => ['unit' => 'tickets', 'period' => 'monthly'],
            ],
            [
                'code' => 'branding.basic',
                'name' => 'Branding básico',
                'description' => 'Logo y colores propios en la pantalla de turnos.',
                'type' => FeatureType::Boolean,
                'metadata' => null,
            ],
            [
                'code' => 'whitelabel.full',
                'name' => 'White-label completo',
                'description' => 'Marca propia sin referencias visibles a Olinora; dominio personalizado.',
                'type' => FeatureType::Boolean,
                'metadata' => null,
            ],
            [
                'code' => 'api.access',
                'name' => 'Acceso a API',
                'description' => 'API REST y webhooks para integración externa.',
                'type' => FeatureType::Boolean,
                'metadata' => null,
            ],
            [
                'code' => 'analytics.advanced',
                'name' => 'Analítica avanzada',
                'description' => 'Reportes avanzados, exportes y dashboards extendidos.',
                'type' => FeatureType::Boolean,
                'metadata' => null,
            ],
            [
                'code' => 'support.tier',
                'name' => 'Nivel de soporte',
                'description' => 'Canal y SLA de soporte ofrecido al tenant.',
                'type' => FeatureType::StringValue,
                'metadata' => [
                    'allowed_values' => ['community', 'email', 'priority', 'dedicated'],
                ],
            ],
            [
                'code' => 'trial.days',
                'name' => 'Días de prueba',
                'description' => 'Duración de la fase de prueba gratuita en días. 0 = sin trial.',
                'type' => FeatureType::Quota,
                'metadata' => ['unit' => 'days'],
            ],
        ];
    }
}
