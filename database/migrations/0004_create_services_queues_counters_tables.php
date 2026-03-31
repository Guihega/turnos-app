<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('code', 5)->comment('Letter code for tickets, e.g. A, B, C');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 7)->default('#3B82F6');
            $table->unsignedSmallInteger('estimated_duration_minutes')->default(15);
            $table->unsignedSmallInteger('max_daily_capacity')->nullable();
            $table->boolean('requires_appointment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active', 'sort_order']);
        });

        Schema::create('queues', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('prefix', 3)->comment('Ticket number prefix, e.g. A, B');
            $table->text('description')->nullable();
            $table->string('priority_algorithm')->default('fifo')->comment('fifo, priority, weighted_fair');
            $table->unsignedSmallInteger('max_capacity')->default(100);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'prefix']);
            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('queue_service', function (Blueprint $table) {
            $table->foreignUlid('queue_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamps();

            $table->unique(['queue_id', 'service_id']);
        });

        Schema::create('counters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('number', 10);
            $table->foreignUlid('current_operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('current_ticket_id')->nullable();
            $table->string('status')->default('closed')->comment('open, serving, paused, closed');
            $table->json('serves_queues')->nullable()->comment('Array of queue IDs this counter serves');
            $table->timestamps();

            $table->unique(['branch_id', 'number']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counters');
        Schema::dropIfExists('queue_service');
        Schema::dropIfExists('queues');
        Schema::dropIfExists('services');
    }
};
