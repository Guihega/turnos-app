<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('utm_source', 255)->nullable()->after('referrer');
            $table->string('utm_medium', 255)->nullable()->after('utm_source');
            $table->string('utm_campaign', 255)->nullable()->after('utm_medium');
            $table->string('utm_term', 255)->nullable()->after('utm_campaign');
            $table->string('utm_content', 255)->nullable()->after('utm_term');

            // El que más vas a consultar: "cuántos leads vinieron de facebook"
            $table->index('utm_source');
            // Útil para comparar campañas específicas
            $table->index('utm_campaign');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['utm_source']);
            $table->dropIndex(['utm_campaign']);
            $table->dropColumn([
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
            ]);
        });
    }
};
