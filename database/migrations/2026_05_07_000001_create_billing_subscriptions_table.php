<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * billing_subscriptions — suscripción activa o histórica de un Customer a un Plan.
 *
 * Un Customer puede tener N Subscriptions a lo largo del tiempo (histórico),
 * pero como mucho UNA en estado activo (pilot/trialing/active/past_due/paused).
 * Este invariante se garantiza a nivel motor con un unique partial index.
 *
 * Estado controlado por App\Enums\Billing\SubscriptionStatus:
 *   pilot | trialing | active | past_due | suspended | paused | canceled
 *
 * Referencia al Price exacto cobrado (price_id) para audit trail:
 *   si el Plan cambia de precio en el futuro, las subs antiguas conservan
 *   su precio original via FK directa.
 *
 * Stripe Subscription ID (stripe_subscription_id) en columna dedicada — NO
 * en JSON — porque es el lookup más frecuente al recibir webhooks.
 *
 * @see docs/billing/SPEC.md §6 (state machine)
 * @see docs/billing/DECISIONS.md ADR-005 (entitlements decoupled), ADR-007 (webhooks)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('customer_id');
            $table->ulid('plan_id');
            // Price exacto cobrado al activar la sub. NULL en pilot (free).
            $table->ulid('price_id')->nullable();

            // App\Enums\Billing\SubscriptionStatus
            $table->string('status', 30);

            // Stripe Subscription ID (sub_xxx). NULL en pilot o suscripciones manuales.
            $table->string('stripe_subscription_id')->nullable()->unique();

            // Timestamps de ciclo de vida
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            // Schedulable: el usuario solicita cancelación → se ejecuta al fin del periodo.
            $table->timestamp('cancel_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('paused_at')->nullable();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')
                ->references('id')->on('billing_customers')
                ->restrictOnDelete();
            $table->foreign('plan_id')
                ->references('id')->on('billing_plans')
                ->restrictOnDelete();
            $table->foreign('price_id')
                ->references('id')->on('billing_prices')
                ->nullOnDelete();

            $table->index('status');
            $table->index(['customer_id', 'status']);
            $table->index('current_period_end');
        });

        // Unique partial index: máximo UNA suscripción activa por Customer.
        // 'Activa' = cualquier estado donde el cliente todavía está vivo en el sistema.
        // Excluye soft-deleted rows.
        DB::statement("
            CREATE UNIQUE INDEX one_active_subscription_per_customer
            ON billing_subscriptions (customer_id)
            WHERE status IN ('pilot', 'trialing', 'active', 'past_due', 'paused')
              AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_subscriptions');
    }
};
