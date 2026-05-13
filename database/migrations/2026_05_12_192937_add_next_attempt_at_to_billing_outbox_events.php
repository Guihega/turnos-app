<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds next_attempt_at to billing_outbox_events.
 *
 * Used by PublishOutboxEventsJob (PR-H) for per-row backoff scheduling.
 * After a failed publish attempt, the column is set to NOW() + backoff(attempts).
 * The job's claim query excludes rows whose next_attempt_at is in the future,
 * implementing the [60, 300, 1800]s backoff defined in ADR-013 without
 * relying on updated_at, which moves for unrelated reasons.
 *
 * NULL = eligible immediately (initial state, or never attempted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_outbox_events', function (Blueprint $table) {
            $table->timestamp('next_attempt_at')->nullable()->after('attempts');
            $table->index(['published_at', 'failed_at', 'next_attempt_at'], 'billing_outbox_events_claim_idx');
        });
    }

    public function down(): void
    {
        Schema::table('billing_outbox_events', function (Blueprint $table) {
            $table->dropIndex('billing_outbox_events_claim_idx');
            $table->dropColumn('next_attempt_at');
        });
    }
};
