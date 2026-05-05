<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * billing_plans — Catálogo comercial.
 *
 * Un Plan es una etiqueta + descripción. NO contiene precio.
 * Los precios viven en billing_prices (un Plan tiene N Prices,
 * uno por cada combinación de moneda × intervalo).
 *
 * Plans iniciales (ver docs/billing/SPEC.md §3):
 *   - pilot         (gratis 90 días, no público en checkout)
 *   - starter
 *   - professional
 *   - business
 *   - enterprise    (is_public=false, solo SuperAdmin)
 *
 * @see docs/billing/SPEC.md §3
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Identificador estable usado en código (pilot, starter, ...).
            $table->string('code', 64)->unique();

            // Nombre y descripción para mostrar al usuario.
            $table->string('name');
            $table->text('description')->nullable();

            // Si false → no aparece en checkout público (enterprise).
            $table->boolean('is_public')->default(true);

            // Si false → no se puede suscribir nuevos tenants. Existente sigue.
            $table->boolean('is_active')->default(true);

            // Orden de presentación en la UI.
            $table->integer('sort_order')->default(0);

            // Metadata libre (color, icono, badges, etc.)
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'is_public']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_plans');
    }
};
