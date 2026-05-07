<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * billing_subscription_state_transitions — auditoría inmutable de cambios
 * de estado de cada Subscription.
 *
 * Tabla append-only:
 *   - Sin softDeletes.
 *   - Sin updated_at (el registro nunca se modifica).
 *   - Trigger PostgreSQL en la migración 000008 RECHAZA UPDATE y DELETE.
 *
 * Cada transición captura:
 *   - estados from/to
 *   - reason: identificador legible ('user_upgrade', 'dunning_failed', ...)
 *   - context: payload completo del evento que disparó (webhook, action, etc.)
 *   - transitioned_at: timestamp UTC del cambio
 *
 * @see docs/billing/DECISIONS.md ADR-011 (auditoría inmutable por trigger)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_subscription_state_transitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('subscription_id');

            // Estados según App\Enums\Billing\SubscriptionStatus.
            // Almacenados como string porque la enum puede crecer y un FK al enum
            // fuerza migraciones al agregar valores.
            $table->string('from_status', 30);
            $table->string('to_status', 30);

            // Motivo legible: 'user_upgrade', 'dunning_failed', 'trial_expired',
            // 'admin_canceled', 'webhook_received', etc.
            $table->string('reason', 255)->nullable();

            // Payload del evento que motivó la transición (para forensics).
            $table->jsonb('context')->nullable();

            $table->timestamp('transitioned_at');
            // SIN created_at/updated_at — append-only. transitioned_at es la verdad.

            $table->foreign('subscription_id')
                ->references('id')->on('billing_subscriptions')
                ->restrictOnDelete();

            $table->index(['subscription_id', 'transitioned_at']);
            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_subscription_state_transitions');
    }
};
