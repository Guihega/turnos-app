<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // google, facebook
            $table->string('provider_id');
            $table->string('provider_email')->nullable();
            $table->string('provider_avatar')->nullable();
            $table->text('provider_token')->nullable(); // encrypted:array cast — needs text, not json
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
            $table->unique(['provider', 'user_id']); // un usuario solo puede vincular 1 cuenta por provider
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
