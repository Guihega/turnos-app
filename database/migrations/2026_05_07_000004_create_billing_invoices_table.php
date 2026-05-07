<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * billing_invoices — factura emitida.
 *
 * Inmutables: una factura no se borra ni se "edita" en sentido fuerte.
 * Anular = cambiar status a 'void' con motivo.
 *
 * MVP emite solo factura comercial (PDF), sin valor fiscal. CFDI 4.0
 * queda en backlog (ADR-009).
 *
 * Estados controlados por App\Enums\Billing\InvoiceStatus:
 *   draft | open | paid | void | uncollectible
 *
 * Montos en centavos (smallest currency unit), nunca floats.
 *
 * @see docs/billing/SPEC.md §6 (state machines)
 * @see docs/billing/DECISIONS.md ADR-009 (CFDI fuera del MVP)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // NULL para facturas ad-hoc (no asociadas a una subscription).
            $table->ulid('subscription_id')->nullable();
            $table->ulid('customer_id');

            // App\Enums\Billing\InvoiceStatus
            $table->string('status', 20);

            // Número humano legible: 'INV-2026-001234'. Generado por counter.
            $table->string('invoice_number', 32)->unique();

            $table->string('currency', 3);

            // Montos en centavos.
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('tax_cents')->default(0);
            $table->bigInteger('total_cents');
            $table->bigInteger('amount_paid_cents')->default(0);
            // total - amount_paid. Útil para queries de "qué se debe".
            $table->bigInteger('amount_due_cents');

            $table->date('issued_at');
            $table->date('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();

            // Stripe Invoice ID (in_xxx)
            $table->string('stripe_invoice_id')->nullable()->unique();

            // Path al PDF generado (storage local o S3 según FILESYSTEM_DISK).
            $table->string('pdf_path')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            // SIN softDeletes — invoices son inmutables.

            $table->foreign('subscription_id')
                ->references('id')->on('billing_subscriptions')
                ->restrictOnDelete();
            $table->foreign('customer_id')
                ->references('id')->on('billing_customers')
                ->restrictOnDelete();

            $table->index('status');
            $table->index(['customer_id', 'status']);
            $table->index('issued_at');
            $table->index('due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
    }
};
