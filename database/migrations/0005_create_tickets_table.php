<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('queue_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('service_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('counter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('served_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('appointment_id')->nullable();

            // Ticket identification
            $table->string('ticket_number', 10)->comment('e.g. A-042');
            $table->unsignedInteger('daily_sequence')->comment('Sequential number for the day');
            $table->string('display_number', 20)->comment('Full display: SUC1-A042');

            // Customer info (optional, for walk-ins can be anonymous)
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_id_number')->nullable()->comment('DNI/CURP/etc');

            // Status & Priority
            $table->string('status')->default('waiting');
            $table->string('priority')->default('normal');
            $table->unsignedSmallInteger('priority_score')->default(5);

            // Timestamps for each state transition
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('transferred_at')->nullable();

            // Computed metrics (denormalized for performance)
            $table->unsignedInteger('wait_time_seconds')->nullable();
            $table->unsignedInteger('service_time_seconds')->nullable();
            $table->unsignedInteger('total_time_seconds')->nullable();

            // Transfer tracking
            $table->foreignUlid('transferred_from_id')->nullable()->comment('Original ticket if transferred');
            $table->unsignedTinyInteger('transfer_count')->default(0);

            // Customer feedback
            $table->unsignedTinyInteger('rating')->nullable()->comment('1-5 stars');
            $table->text('feedback')->nullable();

            // Flexible metadata
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Performance indexes
            $table->index(['branch_id', 'status', 'priority_score', 'issued_at'], 'idx_tickets_queue_order');
            $table->index(['branch_id', 'queue_id', 'status'], 'idx_tickets_branch_queue');
            $table->index(['branch_id', 'issued_at'], 'idx_tickets_daily');
            $table->index(['served_by', 'status'], 'idx_tickets_operator');
            $table->index(['branch_id', 'created_at'], 'idx_tickets_metrics');
            $table->unique(['branch_id', 'daily_sequence', 'created_at'], 'idx_tickets_unique_daily');
        });

        Schema::create('ticket_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 50);
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['ticket_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
        Schema::dropIfExists('tickets');
    }
};
