<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('display_announcements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['announcement', 'news', 'promo'])->default('announcement');
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->string('image_url')->nullable();
            $table->integer('priority')->default(0); // mayor = más arriba
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'type']);
            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('display_announcements');
    }
};
