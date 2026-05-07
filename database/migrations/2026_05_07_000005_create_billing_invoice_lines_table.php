<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * billing_invoice_lines — líneas de una factura.
 *
 * Cada línea representa un cargo concreto: subscription base, prorrateo,
 * addon metered (Fase 5), descuento, tax, etc.
 *
 * amount_cents = quantity × unit_amount_cents (validado en aplicación,
 * no en BD para flexibilidad de descuentos line-level).
 *
 * Cascade desde invoice: si la invoice se borra (raro, solo via rollback
 * de migración), las líneas también. Inmutabilidad la enforça la app, no PG.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_invoice_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('invoice_id');

            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->bigInteger('unit_amount_cents');
            $table->bigInteger('amount_cents');  // quantity × unit_amount, calculado en app

            // Referencia al Price si la línea derivó de un Price del catálogo.
            // NULL para líneas ad-hoc (descuentos manuales, etc.).
            $table->ulid('price_id')->nullable();

            // Para líneas prorrateadas (cambio de plan a mitad de periodo).
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->foreign('invoice_id')
                ->references('id')->on('billing_invoices')
                ->cascadeOnDelete();
            $table->foreign('price_id')
                ->references('id')->on('billing_prices')
                ->nullOnDelete();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoice_lines');
    }
};
