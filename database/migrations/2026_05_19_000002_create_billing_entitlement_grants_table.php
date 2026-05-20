<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * billing_entitlement_grants — overrides operativos atados al Tenant.
 *
 * Permite extender o modificar entitlements de un tenant específico sin
 * tocar el plan ni la subscription. Casos de uso:
 *   - "Le damos 5 sucursales en vez de 3 al cliente X mientras evalúa upgrade."
 *   - "Acceso a feature avanzada por un trimestre como cortesía comercial."
 *
 * A diferencia de billing_entitlements, los grants:
 *   - Se atan al Tenant (sobreviven cambios de plan o de subscription).
 *   - Son históricos: una misma (tenant, feature) puede tener varios grants
 *     a lo largo del tiempo. El "vigente" se filtra por expires_at + revoked_at.
 *   - Tienen autoría (granted_by) y razón (reason) para auditoría.
 *
 * El EntitlementService (PR-R) leerá grants vigentes y aplicará override
 * sobre los entitlements heredados del plan antes de devolver el efectivo.
 *
 * @see docs/billing/SPEC.md §4 (Features → Entitlements)
 * @see docs/billing/MIGRATION_PLAN.md Fase C (lectura dual)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_entitlement_grants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('feature_id');

            // Valores override (mismos shapes que billing_entitlements).
            $table->bigInteger('value_numeric')->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->string('value_string', 255)->nullable();

            // Auditoría operativa.
            $table->ulid('granted_by')->nullable();
            $table->string('reason', 500);

            // Ventana de vigencia. NULL en expires_at = grant perpetuo.
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('feature_id')
                ->references('id')->on('billing_features')
                ->restrictOnDelete();

            $table->foreign('granted_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->index(['tenant_id', 'feature_id']);
            $table->index('expires_at');
        });

        // Partial index: lookup eficiente de grants no-revocados por tenant.
        // Nota: el filtro por expires_at NO se incluye porque NOW() no es
        // IMMUTABLE en Postgres y no puede usarse en index predicates.
        // EntitlementService filtra expires_at en runtime sobre este subset.
        DB::statement('
            CREATE INDEX entitlement_grants_active
            ON billing_entitlement_grants (tenant_id, feature_id)
            WHERE revoked_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_entitlement_grants');
    }
};
