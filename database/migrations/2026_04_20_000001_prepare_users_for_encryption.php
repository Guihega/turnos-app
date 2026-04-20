<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Prepare the users table for CipherSweet field-level encryption.
 *
 * CipherSweet stores ciphertext that is significantly longer than the
 * original plaintext (AES-256 + authentication tag + nonce).
 * Columns must be TEXT type to accommodate the encrypted values.
 *
 * The unique constraint on email is replaced by blind index lookups
 * via the blind_indexes table (managed by spatie/laravel-ciphersweet).
 *
 * This migration is REVERSIBLE: rolling back restores varchar columns
 * and the unique constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the composite unique constraint on (tenant_id, email).
        // Uniqueness will now be enforced via blind index lookups
        // in the blind_indexes table (managed by spatie/laravel-ciphersweet).
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_tenant_id_email_unique');

        // Change column types to TEXT for encrypted storage.
        // CipherSweet ciphertext is much longer than the original plaintext.
        DB::statement('ALTER TABLE users ALTER COLUMN email TYPE TEXT');
        DB::statement('ALTER TABLE users ALTER COLUMN phone TYPE TEXT');
        DB::statement('ALTER TABLE users ALTER COLUMN last_login_ip TYPE TEXT');
    }

    public function down(): void
    {
        // Revert columns to varchar
        // WARNING: Only run down() AFTER decrypting all data first.
        DB::statement('ALTER TABLE users ALTER COLUMN email TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE users ALTER COLUMN phone TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE users ALTER COLUMN last_login_ip TYPE VARCHAR(255)');

        // Restore the composite unique constraint
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_tenant_id_email_unique UNIQUE (tenant_id, email)');
    }
};
