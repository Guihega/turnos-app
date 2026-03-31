<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('service_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();

            $table->date('scheduled_date');
            $table->time('scheduled_time');
            $table->timestamp('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(15);

            $table->string('status')->default('scheduled');
            $table->string('confirmation_code', 8)->unique();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->text('notes')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'scheduled_date', 'status'], 'idx_appointments_daily');
            $table->index(['branch_id', 'service_id', 'scheduled_date'], 'idx_appointments_service');
            $table->index(['customer_email', 'scheduled_date']);
            $table->index('confirmation_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
