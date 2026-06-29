<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * billing_plan_features — Valores de Features por Plan.
 *
 * Pivot enriquecido entre Plans y Features. Define qué entitlements
 * otorga cada Plan al activarse.
 *
 * Ejemplos:
 *   plan=professional, feature=branches.max,    value_numeric=10
 *   plan=professional, feature=whitelabel.full, value_boolean=true
 *   plan=professional, feature=support.tier,    value_string='email-priority'
 *
 * Convención de quotas:
 *   value_numeric = -1  → ilimitado
 *   value_numeric = 0   → cero (no permitido)
 *   value_numeric > 0   → límite exacto
 *
 * @see docs/billing/SPEC.md §4
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_plan_features', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('plan_id');
            $table->ulid('feature_id');

            // Solo se usa el value_* correspondiente al type de la Feature.
            $table->bigInteger('value_numeric')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->string('value_string')->nullable();

            // Para quotas que se reinician: 'monthly' | 'annually' | 'never' | null
            $table->string('reset_period', 20)->nullable();

            $table->timestamps();

            $table->foreign('plan_id')
                ->references('id')->on('billing_plans')
                ->cascadeOnDelete();

            $table->foreign('feature_id')
                ->references('id')->on('billing_features')
                ->cascadeOnDelete();

            // Una Feature solo puede aparecer una vez por Plan.
            $table->unique(['plan_id', 'feature_id'], 'pf_plan_feature_unique');

            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_plan_features');
    }
};
