<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * billing_customer_gateway_refs — IDs externos del Customer en cada pasarela.
 *
 * Un mismo Customer puede existir en N pasarelas (Stripe + Mercado Pago + ...).
 * Esta tabla mapea: Customer ↔ (gateway, gateway_customer_id).
 *
 * Ejemplo:
 *   Customer 01H... tiene:
 *     - stripe / cus_NA12345
 *     - mercadopago / 1234567890
 *     - openpay / a1b2c3d4
 *
 * Permite que un mismo Tenant pague con Stripe en USD y MP en MXN
 * sin duplicar el Customer.
 *
 * @see docs/billing/SPEC.md §5
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_customer_gateway_refs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('customer_id');

            // Enum value from App\Enums\Billing\Gateway
            $table->string('gateway', 32);

            // ID returned by the gateway when the customer was created.
            // Format varies per gateway; we don't enforce shape.
            $table->string('gateway_customer_id');

            // Gateway-specific extra data (livemode, account, etc.)
            $table->jsonb('metadata')->nullable();

            $table->timestamps();

            $table->foreign('customer_id')
                ->references('id')->on('billing_customers')
                ->cascadeOnDelete();

            // Same gateway_customer_id cannot exist twice in the same gateway.
            $table->unique(['gateway', 'gateway_customer_id'], 'cgr_gateway_id_unique');

            $table->index(['customer_id', 'gateway']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_customer_gateway_refs');
    }
};
