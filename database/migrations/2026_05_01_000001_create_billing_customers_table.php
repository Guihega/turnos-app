<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * billing_customers — billing-context representation of a Tenant.
 *
 * Each Tenant has exactly one Customer (1:1). The Customer holds:
 *   - billing identity (email, name, tax_id, address)
 *   - default currency and country
 *   - links to gateway-specific customer IDs (via billing_customer_gateway_refs)
 *
 * PII fields (billing_email, tax_id) will be encrypted via CipherSweet
 * in a follow-up migration once the model is wired up.
 *
 * @see docs/billing/SPEC.md §5
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_customers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->unique();

            // ISO 3166-1 alpha-2 country code (MX, US, CO, AR, CL, PE, etc.)
            $table->string('country', 2)->default('MX');

            // ISO 4217 currency code (MXN, USD, COP, ARS, CLP, PEN)
            $table->string('default_currency', 3)->default('MXN');

            // Billing contact. Email will be CipherSweet-encrypted later.
            $table->string('billing_email');
            $table->string('billing_name')->nullable();

            // Tax ID (RFC in MX, RUT in CL, NIT in CO, etc.). Encrypted.
            $table->string('tax_id')->nullable();

            // Structured billing address. Stored as JSONB.
            // Shape: {street, street2, city, state, zip, country}
            $table->jsonb('billing_address')->nullable();

            // Free-form metadata for integrations.
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();

            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_customers');
    }
};
