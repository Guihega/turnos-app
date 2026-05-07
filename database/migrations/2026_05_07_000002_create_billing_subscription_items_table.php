<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * billing_subscription_items — componentes de una Subscription.
 *
 * Una Subscription típica tiene UN item base (el plan principal). Cuando se
 * habilite metered billing en Fase 5, se agregarán items de tipo addon para
 * sucursales adicionales.
 *
 * Cada item referencia su Price para audit y cálculo de proration.
 * stripe_subscription_item_id mapea al SubscriptionItem en Stripe.
 *
 * @see docs/billing/SPEC.md
 * @see docs/billing/BACKLOG.md (Fase 5 metered billing)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_subscription_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('subscription_id');
            $table->ulid('price_id');

            // 'subscription' (item base del plan) | 'addon' (extras como branches.metered)
            $table->string('kind', 20)->default('subscription');

            $table->integer('quantity')->default(1);

            // Stripe SubscriptionItem ID (si)_xxx
            $table->string('stripe_subscription_item_id')->nullable()->unique();

            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('subscription_id')
                ->references('id')->on('billing_subscriptions')
                ->cascadeOnDelete();
            $table->foreign('price_id')
                ->references('id')->on('billing_prices')
                ->restrictOnDelete();

            $table->index('subscription_id');
            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_subscription_items');
    }
};
