<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('legal_name')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('timezone')->default('America/Mexico_City');
            $table->string('locale')->default('es');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('plan')->default('basic');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'plan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
