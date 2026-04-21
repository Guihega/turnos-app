<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('organization');
            $table->string('sector'); // salud, finanzas, gobierno, comercio, otro
            $table->string('size');   // 1, 2-5, 6-20, 20+
            $table->text('message')->nullable();

            // Trazabilidad
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('referrer', 512)->nullable();

            // Gestión interna (para cuando armes el panel admin después)
            $table->string('status')->default('new'); // new, contacted, qualified, discarded
            $table->text('notes')->nullable();
            $table->timestamp('contacted_at')->nullable();

            $table->timestamps();

            // Índices para búsqueda y filtrado
            $table->index('email');
            $table->index('sector');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
