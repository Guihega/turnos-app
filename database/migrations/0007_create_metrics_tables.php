<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metrics_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('queue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('service_id')->nullable()->constrained()->nullOnDelete();

            $table->date('date');
            $table->unsignedTinyInteger('hour')->nullable()->comment('0-23, null for daily aggregate');
            $table->string('granularity', 10)->default('hourly')->comment('hourly, daily, weekly, monthly');

            // Volume metrics
            $table->unsignedInteger('tickets_issued')->default(0);
            $table->unsignedInteger('tickets_served')->default(0);
            $table->unsignedInteger('tickets_cancelled')->default(0);
            $table->unsignedInteger('tickets_no_show')->default(0);
            $table->unsignedInteger('tickets_transferred')->default(0);
            $table->unsignedInteger('appointments_scheduled')->default(0);
            $table->unsignedInteger('appointments_completed')->default(0);

            // Time metrics (in seconds)
            $table->unsignedInteger('avg_wait_time')->default(0);
            $table->unsignedInteger('max_wait_time')->default(0);
            $table->unsignedInteger('min_wait_time')->default(0);
            $table->unsignedInteger('p50_wait_time')->default(0);
            $table->unsignedInteger('p90_wait_time')->default(0);
            $table->unsignedInteger('p95_wait_time')->default(0);

            $table->unsignedInteger('avg_service_time')->default(0);
            $table->unsignedInteger('max_service_time')->default(0);
            $table->unsignedInteger('min_service_time')->default(0);

            // Capacity metrics
            $table->unsignedSmallInteger('peak_queue_length')->default(0);
            $table->unsignedSmallInteger('avg_queue_length')->default(0);
            $table->unsignedSmallInteger('active_operators')->default(0);

            // Satisfaction
            $table->decimal('avg_rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count')->default(0);

            // SLA compliance
            $table->decimal('sla_compliance_pct', 5, 2)->nullable()->comment('% tickets within SLA wait time');

            $table->timestamps();

            $table->unique(['branch_id', 'queue_id', 'service_id', 'date', 'hour', 'granularity'], 'idx_metrics_unique');
            $table->index(['branch_id', 'date', 'granularity'], 'idx_metrics_query');
            $table->index(['date', 'granularity']);
        });

        // Operator performance tracking
        Schema::create('operator_metrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->constrained()->cascadeOnDelete();
            $table->date('date');

            $table->unsignedInteger('tickets_served')->default(0);
            $table->unsignedInteger('avg_service_time')->default(0);
            $table->unsignedInteger('total_service_time')->default(0);
            $table->unsignedInteger('total_idle_time')->default(0);
            $table->decimal('avg_rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count')->default(0);
            $table->unsignedSmallInteger('breaks_taken')->default(0);
            $table->unsignedInteger('break_duration_seconds')->default(0);
            $table->decimal('utilization_pct', 5, 2)->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'branch_id', 'date']);
            $table->index(['branch_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_metrics');
        Schema::dropIfExists('metrics_snapshots');
    }
};
