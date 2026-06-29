<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * billing_payments — intento de cobro contra una Invoice.
 *
 * Audit trail crítico: cada intento (exitoso o fallido) deja registro.
 * NO se borra (sin softDeletes). Refunds son registros separados con
 * status='refunded' linkeados al payment original via metadata.
 *
 * Estados controlados por App\Enums\Billing\PaymentStatus:
 *   pending | requires_action | processing | succeeded | failed | refunded
 *
 * idempotency_key UNIQUE es OBLIGATORIO — garantiza que reintentar la
 * misma operación no genera 2 cobros (ADR de idempotencia).
 *
 * @see docs/billing/SPEC.md §6 (Payment state machine)
 * @see docs/billing/DECISIONS.md (idempotencia obligatoria)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('invoice_id');
            $table->ulid('payment_method_id')->nullable();

            // App\Enums\Billing\PaymentStatus
            $table->string('status', 30);

            $table->string('currency', 3);
            $table->bigInteger('amount_cents');

            // Stripe IDs
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_charge_id')->nullable();

            // Detalle de fallo (si aplica)
            $table->string('failure_code', 50)->nullable();
            $table->string('failure_message', 500)->nullable();

            // OBLIGATORIO. Garantiza que reintentos no dupliquen cobros.
            $table->string('idempotency_key', 100)->unique();

            $table->timestamp('processed_at')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            // SIN softDeletes — audit trail.

            $table->foreign('invoice_id')
                ->references('id')->on('billing_invoices')
                ->restrictOnDelete();
            $table->foreign('payment_method_id')
                ->references('id')->on('billing_payment_methods')
                ->nullOnDelete();

            $table->index('status');
            $table->index(['invoice_id', 'status']);
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payments');
    }
};
