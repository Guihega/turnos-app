<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add composite indexes to optimize dashboard & analytics queries.
 *
 * The most frequent queries filter tickets by (branch_id, created_at)
 * and then aggregate by status, wait_time_seconds, service_time_seconds.
 * These covering indexes let PostgreSQL satisfy those queries via
 * index-only scans or narrow index scans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Core metrics: branch + date + status (covers getTodayStats, hourly, trend)
            $table->index(['branch_id', 'created_at', 'status'], 'idx_tickets_branch_date_status');

            // Service breakdown: branch + date + service_id
            $table->index(['branch_id', 'created_at', 'service_id'], 'idx_tickets_branch_date_service');

            // Operator performance: branch + date + served_by
            $table->index(['branch_id', 'created_at', 'served_by'], 'idx_tickets_branch_date_operator');

            // Heatmap: branch + created_at + wait_time (partial — only non-null waits)
            // Note: partial indexes need raw SQL
        });

        // Partial index for heatmap — only rows with wait_time_seconds
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_tickets_branch_wait_heatmap
            ON tickets (branch_id, created_at, wait_time_seconds)
            WHERE wait_time_seconds IS NOT NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_tickets_branch_wait_heatmap');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('idx_tickets_branch_date_status');
            $table->dropIndex('idx_tickets_branch_date_service');
            $table->dropIndex('idx_tickets_branch_date_operator');
        });
    }
};
