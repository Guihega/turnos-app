<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * billing_outbox_events — outbox transaccional para eventos de dominio salientes.
 *
 * Cuando una entidad de Billing cambia de estado relevante (Subscription
 * activada, Payment fallido, etc.), se escribe un OutboxEvent en la
 * MISMA transacción que el cambio de estado. Esto garantiza:
 *
 *   - Si el commit BD tiene éxito, el evento queda persistido pendiente.
 *   - Si el commit falla, el evento NUNCA existe.
 *
 * Un job 'PublishOutboxEventsJob' corre cada 30s vía scheduler:
 *   - Lee eventos WHERE published_at IS NULL AND failed_at IS NULL ORDER BY created_at.
 *   - Despacha cada uno a su consumidor (jobs internos de la app).
 *   - Marca published_at al éxito.
 *   - Tras 3 fallos, marca failed_at + alerta Telegram.
 *
 * @see docs/billing/DECISIONS.md ADR-010 (pattern), ADR-013 (operational defaults)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_outbox_events', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Aggregate origen del evento. Tipo (clase) y id (ULID).
            // Permite consumidores filtrar por tipo de aggregate sin
            // tener que parsear el payload.
            $table->string('aggregate_type', 100);  // 'Subscription', 'Invoice', ...
            $table->string('aggregate_id', 26);     // ULID del aggregate

            // Tipo del evento de dominio.
            $table->string('event_type', 100);  // 'SubscriptionActivated', 'PaymentFailed', ...

            // Datos del evento. Serializable, idempotente al consumidor.
            $table->jsonb('payload');

            // NULL = pendiente. timestamp = publicado OK.
            $table->timestamp('published_at')->nullable();

            // NULL = retryable. timestamp = falló terminal tras N reintentos.
            // Eventos con failed_at NUNCA se purgan automáticamente.
            $table->timestamp('failed_at')->nullable();

            $table->integer('attempts')->default(0);

            // Mensaje de la última excepción (truncado a 1000 chars).
            $table->text('last_error')->nullable();

            $table->timestamps();
            // SIN softDeletes. Purga vía job semanal (30 días para published).

            // El index combinado published_at + failed_at hace el query del
            // publisher 'WHERE published_at IS NULL AND failed_at IS NULL' rápido.
            $table->index(['published_at', 'failed_at']);
            $table->index('event_type');
            $table->index('aggregate_type');
            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_outbox_events');
    }
};
