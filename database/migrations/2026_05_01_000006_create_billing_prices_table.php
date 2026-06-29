<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * billing_prices — Precio concreto de un Plan.
 *
 * Un Plan tiene N Prices: uno por cada combinación de:
 *   - moneda (MXN, USD, COP, ARS, CLP, PEN)
 *   - país (opcional, null = aplica a cualquier país que use esa moneda)
 *   - intervalo (month | year)
 *   - interval_count (1 mensual, 3 trimestral, 12 anual)
 *
 * Montos siempre en CENTAVOS para evitar floats.
 * Ejemplo: $79.00 USD = amount_cents 7900 con currency 'USD'
 *
 * gateway_refs guarda el ID del Price equivalente en cada pasarela.
 *   { "stripe": "price_NA12345", "mercadopago": "plan_xxx" }
 *
 * @see docs/billing/SPEC.md §5
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_prices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('plan_id');

            // ISO 4217
            $table->string('currency', 3);

            // ISO 3166-1 alpha-2. null = cualquier país que use esta moneda.
            $table->string('country', 2)->nullable();

            // 'month' | 'year' (ver App\Enums\Billing\BillingInterval)
            $table->string('interval', 20);

            // Cuántos intervalos. interval=month + interval_count=3 = trimestral.
            $table->integer('interval_count')->default(1);

            // Monto en CENTAVOS. NUNCA float.
            $table->bigInteger('amount_cents');

            // 'inclusive' (precio ya incluye impuestos) | 'exclusive'
            $table->string('tax_behavior', 20)->default('exclusive');

            // IDs externos en cada pasarela. {"stripe":"price_xxx", "mercadopago":"yyy"}
            $table->jsonb('gateway_refs')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('plan_id')
                ->references('id')->on('billing_plans')
                ->cascadeOnDelete();

            // Una sola combinación plan/currency/country/interval/count.
            $table->unique(
                ['plan_id', 'currency', 'country', 'interval', 'interval_count'],
                'price_unique_combination'
            );

            $table->index(['currency', 'is_active']);
            $table->index(['plan_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_prices');
    }
};
