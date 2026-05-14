<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the blind_indexes table used by spatie/laravel-ciphersweet.
 *
 * NOTE: This project ships its own migration instead of publishing the
 * vendor one (`spatie/laravel-ciphersweet`) because the vendor uses
 * `morphs()` (bigint auto-increment) while the project standardises on
 * ULIDs (`char(26)`) for all primary keys. Switching the indexable
 * morph type breaks compatibility with ULID-keyed models such as User.
 *
 * The vendor migration is published-only (registered via
 * Spatie\LaravelPackageTools\Package::hasMigration) and does NOT run
 * automatically, so there is no risk of duplicate creation.
 *
 * Idempotency: up() guards with Schema::hasTable so replay over an
 * already-provisioned DB (e.g. a partial migrate state) is safe.
 * Reversibility: down() drops the table, enabling migrate:rollback in CI.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blind_indexes')) {
            return;
        }

        Schema::create('blind_indexes', function (Blueprint $table): void {
            $table->ulidMorphs('indexable');
            $table->string('name');
            $table->string('value');

            $table->index(['name', 'value']);
            $table->unique(['indexable_type', 'indexable_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blind_indexes');
    }
};
