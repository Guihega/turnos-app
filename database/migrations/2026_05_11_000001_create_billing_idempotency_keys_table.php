<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * billing_idempotency_keys — registro local de claves de idempotencia
 * usadas contra cada gateway.
 *
 * Per ADR-016:
 *
 *   - Cada operación de write (createCustomer, createSubscription,
 *     cancelSubscription, ...) genera un ULID que el Action layer pasa
 *     como idempotency key al adapter.
 *
 *   - Persistimos la key localmente para:
 *     1. detectar reuso accidental con payload distinto
 *        (request_hash mismatch),
 *     2. recuperar la respuesta original sin re-llamar al gateway en
 *        un retry transparente,
 *     3. tener auditoría forense de qué se mandó y qué se recibió.
 *
 *   - response_snapshot guarda el resultado deserializado del gateway
 *     (el DTO serializado a array). NO contiene PAN/CVV (Stripe nunca
 *     los expone) ni emails crudos (la fuente de verdad de PII está
 *     en billing_customers con CipherSweet).
 *
 *   - customer_id es NULLABLE porque create_customer precede al
 *     customer local. Las demás operaciones lo poblan.
 *
 *   - expires_at gobierna el cleanup job. Stripe garantiza idempotency
 *     24h server-side; mantenemos 7 días para forensics, configurable
 *     via config('billing.idempotency.ttl_days').
 *
 * @see docs/adr/ADR-016-billing-write-contract-and-create-flows.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_idempotency_keys', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Nullable: create_customer no tiene customer todavía al
            // momento de generar la key (se crean en la misma transaction).
            $table->ulid('customer_id')->nullable();

            // e.g. 'create_customer', 'create_subscription', 'cancel_subscription'.
            $table->string('operation', 50);

            // e.g. 'stripe', 'mercadopago', 'manual'. Coincide con keys
            // de config('billing.gateways.*').
            $table->string('gateway', 20);

            // El ULID que mandamos al gateway. Único por gateway.
            $table->string('idempotency_key', 100);

            // sha256 del payload normalizado. Detecta reuso de la misma
            // key con un payload distinto (= bug del Action layer).
            $table->char('request_hash', 64);

            // Snapshot del DTO retornado por el adapter en la primera
            // llamada exitosa. Permite retry transparente sin re-llamar
            // al gateway.
            $table->jsonb('response_snapshot')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at');

            // FK a customers. SET NULL en delete permite hard-delete de
            // un customer sin perder la auditoría histórica.
            $table->foreign('customer_id')
                ->references('id')->on('billing_customers')
                ->nullOnDelete();

            // Una key es única dentro de un gateway. La combinación
            // (gateway, key) garantiza que distintos gateways nunca
            // colisionen incluso si por casualidad reutilizan strings.
            $table->unique(['gateway', 'idempotency_key'], 'idempotency_keys_gateway_key_unique');

            // Lookup pattern: "¿este customer ya tiene un create_subscription
            // pendiente?" — consulta del Action en retry/replay.
            $table->index(['customer_id', 'operation']);

            // Cleanup job semanal: SELECT WHERE expires_at < now() LIMIT 1000.
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_idempotency_keys');
    }
};
