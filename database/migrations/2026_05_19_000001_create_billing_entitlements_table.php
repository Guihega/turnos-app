<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * billing_entitlements — instancias materializadas de Feature por Subscription.
 *
 * Una fila por (subscription_id, feature_id) que copia los valores efectivos
 * del catálogo (billing_plan_features) al momento de activar la subscription.
 * Esto desacopla las subs activas del catálogo: si el plan cambia su lista
 * de features o sus límites, las subs existentes conservan su snapshot.
 *
 * El servicio Entitlement (PR-R) leerá esta tabla en O(1) sin joins al
 * catálogo. Para overrides operativos puntuales (extender un límite a un
 * tenant específico), ver billing_entitlement_grants.
 *
 * @see docs/billing/SPEC.md §4 (Features → Entitlements)
 * @see docs/billing/MIGRATION_PLAN.md Fase B (materialización en backfill)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_entitlements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('subscription_id');
            $table->ulid('feature_id');

            // Valores materializados (espejo de billing_plan_features).
            $table->bigInteger('value_numeric')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->string('value_string', 255)->nullable();

            // Ventana de reset para quotas (e.g. 'monthly', 'yearly', 'never').
            $table->string('reset_period', 20)->nullable();

            // Origen: 'plan' (heredado del catálogo) | 'grant' (override aplicado).
            $table->string('source', 20)->default('plan');

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->foreign('subscription_id')
                ->references('id')->on('billing_subscriptions')
                ->cascadeOnDelete();

            $table->foreign('feature_id')
                ->references('id')->on('billing_features')
                ->restrictOnDelete();

            $table->unique(['subscription_id', 'feature_id'], 'entitlements_subscription_feature_unique');
            $table->index('feature_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_entitlements');
    }
};
