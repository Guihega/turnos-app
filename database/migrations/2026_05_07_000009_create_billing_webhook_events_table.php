<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * billing_webhook_events — inbox transaccional para webhooks entrantes.
 *
 * Cada webhook que llega a la app:
 *   1. Tiene su firma validada.
 *   2. Se persiste aquí con UNIQUE(gateway, gateway_event_id) para deduplicar.
 *   3. Se encola un job que lo procesa.
 *   4. El controller responde 200.
 *
 * Si el job falla, se reintenta hasta 5 veces con backoff [3, 10, 30, 300]s.
 * Tras agotar reintentos: needs_review=true + alerta Telegram.
 *
 * @see docs/billing/DECISIONS.md ADR-007 (pattern), ADR-012 (operational defaults)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_webhook_events', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Pasarela origen ('stripe', 'mercadopago', etc.). String para no acoplar
            // a un enum que cambiaría con cada nueva pasarela soportada.
            $table->string('gateway', 30);

            // ID del evento dado por la pasarela ('evt_xxx' en Stripe).
            // UNIQUE(gateway, gateway_event_id) garantiza idempotencia: si el mismo
            // evento llega 2 veces (común con Stripe retries), el segundo INSERT falla.
            $table->string('gateway_event_id');

            // Tipo de evento ('invoice.paid', 'customer.subscription.created'...).
            // String porque cada gateway define su propio vocabulario.
            $table->string('event_type', 100);

            // Body completo del webhook tras validación de firma.
            $table->jsonb('payload');

            // Header de firma guardado para audit (ej. 'Stripe-Signature').
            // No se valida después — el controller ya verificó al recibir.
            $table->string('signature_header', 500)->nullable();

            // NULL = pendiente o procesando. timestamp = procesado OK.
            $table->timestamp('processed_at')->nullable();

            // true cuando el job agotó retries — requiere intervención manual.
            // Estos eventos NO se purgan automáticamente.
            $table->boolean('needs_review')->default(false);

            // Contador de intentos. Incrementado por el job.
            $table->integer('attempts')->default(0);

            // Mensaje de la última excepción (truncado a 1000 chars en el job).
            $table->text('last_error')->nullable();

            // timestamp si fue reprocesado vía 'php artisan billing:webhook:replay'.
            $table->timestamp('replayed_at')->nullable();

            $table->timestamps();
            // SIN softDeletes — audit trail. Purga vía job nocturno (90 días).

            $table->unique(['gateway', 'gateway_event_id'], 'webhook_events_gateway_event_unique');
            $table->index('gateway');
            $table->index('event_type');
            $table->index('processed_at');
            $table->index(['gateway', 'needs_review']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_events');
    }
};
