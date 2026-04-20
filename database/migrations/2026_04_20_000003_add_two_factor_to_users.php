<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add two-factor authentication fields to users table.
 *
 * - two_factor_secret: encrypted TOTP secret (nullable, null = 2FA not set up)
 * - two_factor_recovery_codes: encrypted JSON array of single-use recovery codes
 * - two_factor_confirmed_at: timestamp when user confirmed 2FA setup
 *   (null = setup started but not confirmed, not null = 2FA active)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
