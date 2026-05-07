<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * billing_payment_methods — método de pago tokenizado del Customer.
 *
 * NUNCA almacena PAN (Primary Account Number) ni CVC. Solo tokens y
 * datos visibles (last4, brand, expiry) por compliance PCI SAQ-A.
 *
 * Soporta varios tipos vía App\Enums\Billing\PaymentMethodType:
 *   card | bank_transfer | oxxo | cash | manual
 *
 * Soft delete: al "borrar" una tarjeta, mantenemos el registro para
 * trazabilidad de pagos pasados (un Payment puede referenciar un
 * payment_method "borrado").
 *
 * @see docs/billing/SPEC.md §8 (PCI-DSS SAQ-A)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_payment_methods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('customer_id');

            // App\Enums\Billing\PaymentMethodType
            $table->string('type', 30);

            // Stripe PaymentMethod ID (pm_xxx)
            $table->string('stripe_payment_method_id')->nullable()->unique();

            // Solo UNA default por customer. Constraint en aplicación
            // (o trigger en futuro hardening). El index ayuda al lookup.
            $table->boolean('is_default')->default(false);

            // Datos visibles (NO sensibles).
            $table->string('brand', 20)->nullable();        // visa, mastercard, amex...
            $table->string('last4', 4)->nullable();
            $table->integer('exp_month')->nullable();
            $table->integer('exp_year')->nullable();
            $table->string('cardholder_name', 100)->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')
                ->references('id')->on('billing_customers')
                ->restrictOnDelete();

            $table->index(['customer_id', 'is_default']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payment_methods');
    }
};
