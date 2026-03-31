<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('code', 10)->comment('Short code for ticket prefix, e.g. SUC1');
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country', 2)->default('MX');
            $table->string('zip_code', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('timezone')->default('America/Mexico_City');
            $table->json('operating_hours')->nullable()->comment('{"mon":{"open":"08:00","close":"18:00"},...}');
            $table->json('settings')->nullable();
            $table->unsignedSmallInteger('max_daily_tickets')->default(500);
            $table->unsignedSmallInteger('max_concurrent_waiting')->default(50);
            $table->boolean('is_active')->default(true);
            $table->boolean('accepts_walkins')->default(true);
            $table->boolean('accepts_appointments')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
