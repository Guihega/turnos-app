<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * billing_features — Catálogo de capacidades del producto.
 *
 * Una Feature representa una capacidad identificable por código:
 *   - branches.included         (quota)
 *   - branches.max              (quota)
 *   - whitelabel.full           (boolean)
 *   - support.tier              (string)
 *   - branches.metered          (metered, billable per use)
 *
 * Las features NO tienen valor en sí mismas. Su valor lo asigna
 * cada Plan en billing_plan_features (entitlements del plan)
 * y se materializa al activar la suscripción en billing_entitlements.
 *
 * @see docs/billing/SPEC.md §4
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_features', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Código estable (whitelabel.full, branches.max, ...).
            // Convención: <area>.<aspecto> en snake/dot case.
            $table->string('code', 80)->unique();

            // Nombre y descripción.
            $table->string('name');
            $table->text('description')->nullable();

            // Tipo de feature: boolean | quota | metered | string
            // (App\Enums\Billing\FeatureType)
            $table->string('type', 20);

            // Metadata libre: unidad (tickets/mes), badge, etc.
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_features');
    }
};
