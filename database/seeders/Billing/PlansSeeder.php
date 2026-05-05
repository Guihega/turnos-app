<?php

declare(strict_types=1);

namespace Database\Seeders\Billing;

use App\Models\Billing\Feature;
use App\Models\Billing\Plan;
use App\Models\Billing\PlanFeature;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds the 5 commercial plans and their entitlement matrix.
 *
 * Idempotent:
 *   - Plan::updateOrCreate(code) for plans.
 *   - PlanFeature::updateOrCreate(plan_id+feature_id) for the matrix.
 *
 * Boolean features absent from a plan are NOT seeded as false; absence
 * means "not granted" by entitlement convention.
 *
 * @see docs/billing/SPEC.md §3 §4
 */
final class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPlans();
        $this->seedPlanFeatures();
    }

    private function seedPlans(): void
    {
        foreach ($this->plans() as $plan) {
            Plan::updateOrCreate(
                ['code' => $plan['code']],
                [
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'is_public' => $plan['is_public'],
                    'is_active' => $plan['is_active'],
                    'sort_order' => $plan['sort_order'],
                    'metadata' => $plan['metadata'],
                ]
            );
        }
    }

    private function seedPlanFeatures(): void
    {
        $plans = Plan::query()->whereIn('code', ['pilot', 'starter', 'professional', 'business'])
            ->get()
            ->keyBy('code');

        $features = Feature::query()->get()->keyBy('code');

        foreach ($this->planFeatureMatrix() as $planCode => $rows) {
            $plan = $plans->get($planCode);

            if ($plan === null) {
                throw new RuntimeException("Plan '{$planCode}' not found. Did the plans seed run?");
            }

            foreach ($rows as $row) {
                $feature = $features->get($row['code']);

                if ($feature === null) {
                    throw new RuntimeException(
                        "Feature '{$row['code']}' not found. Did the features seed run?"
                    );
                }

                PlanFeature::updateOrCreate(
                    [
                        'plan_id' => $plan->id,
                        'feature_id' => $feature->id,
                    ],
                    [
                        'value_numeric' => $row['value_numeric'] ?? null,
                        'value_boolean' => $row['value_boolean'] ?? null,
                        'value_string' => $row['value_string'] ?? null,
                        'reset_period' => $row['reset_period'] ?? null,
                    ]
                );
            }
        }
    }

    /**
     * @return array<int, array{
     *     code: string,
     *     name: string,
     *     description: string,
     *     is_public: bool,
     *     is_active: bool,
     *     sort_order: int,
     *     metadata: array<string, mixed>|null
     * }>
     */
    private function plans(): array
    {
        return [
            [
                'code' => 'pilot',
                'name' => 'Piloto',
                'description' => 'Prueba gratuita de 90 días con 1 sucursal y funcionalidad esencial.',
                'is_public' => true,
                'is_active' => true,
                'sort_order' => 10,
                'metadata' => ['badge' => 'free-trial', 'color' => '#6B7280'],
            ],
            [
                'code' => 'starter',
                'name' => 'Starter',
                'description' => '1 sucursal con branding básico. Ideal para negocios que arrancan.',
                'is_public' => true,
                'is_active' => true,
                'sort_order' => 20,
                'metadata' => ['color' => '#3B82F6'],
            ],
            [
                'code' => 'professional',
                'name' => 'Professional',
                'description' => 'Hasta 10 sucursales, white-label, API y analítica avanzada.',
                'is_public' => true,
                'is_active' => true,
                'sort_order' => 30,
                'metadata' => ['badge' => 'most-popular', 'color' => '#10B981'],
            ],
            [
                'code' => 'business',
                'name' => 'Business',
                'description' => 'Hasta 50 sucursales, operadores y tickets ilimitados, soporte prioritario.',
                'is_public' => true,
                'is_active' => true,
                'sort_order' => 40,
                'metadata' => ['color' => '#8B5CF6'],
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'Implementación a medida. Contactar al equipo comercial.',
                'is_public' => false,
                'is_active' => true,
                'sort_order' => 50,
                'metadata' => ['contact_sales' => true],
            ],
        ];
    }

    /**
     * Plan → feature value matrix.
     *
     * Booleans only seeded when true. Absent rows = not granted.
     * Quota -1 = unlimited.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function planFeatureMatrix(): array
    {
        return [
            'pilot' => [
                ['code' => 'branches.included', 'value_numeric' => 1],
                ['code' => 'branches.max', 'value_numeric' => 1],
                ['code' => 'operators.max', 'value_numeric' => 2],
                ['code' => 'tickets.monthly', 'value_numeric' => 500, 'reset_period' => 'monthly'],
                ['code' => 'support.tier', 'value_string' => 'community'],
                ['code' => 'trial.days', 'value_numeric' => 90, 'reset_period' => 'never'],
            ],

            'starter' => [
                ['code' => 'branches.included', 'value_numeric' => 1],
                ['code' => 'branches.max', 'value_numeric' => 1],
                ['code' => 'operators.max', 'value_numeric' => 5],
                ['code' => 'tickets.monthly', 'value_numeric' => 5000, 'reset_period' => 'monthly'],
                ['code' => 'branding.basic', 'value_boolean' => true],
                ['code' => 'support.tier', 'value_string' => 'email'],
            ],

            'professional' => [
                ['code' => 'branches.included', 'value_numeric' => 3],
                ['code' => 'branches.max', 'value_numeric' => 10],
                ['code' => 'operators.max', 'value_numeric' => 20],
                ['code' => 'tickets.monthly', 'value_numeric' => 25000, 'reset_period' => 'monthly'],
                ['code' => 'branding.basic', 'value_boolean' => true],
                ['code' => 'whitelabel.full', 'value_boolean' => true],
                ['code' => 'api.access', 'value_boolean' => true],
                ['code' => 'analytics.advanced', 'value_boolean' => true],
                ['code' => 'support.tier', 'value_string' => 'email'],
            ],

            'business' => [
                ['code' => 'branches.included', 'value_numeric' => 10],
                ['code' => 'branches.max', 'value_numeric' => 50],
                ['code' => 'operators.max', 'value_numeric' => -1],
                ['code' => 'tickets.monthly', 'value_numeric' => -1, 'reset_period' => 'monthly'],
                ['code' => 'branding.basic', 'value_boolean' => true],
                ['code' => 'whitelabel.full', 'value_boolean' => true],
                ['code' => 'api.access', 'value_boolean' => true],
                ['code' => 'analytics.advanced', 'value_boolean' => true],
                ['code' => 'support.tier', 'value_string' => 'priority'],
            ],
        ];
    }
}
