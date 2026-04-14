<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('display_announcements', function (Blueprint $table) {
            $table->string('media_url')->nullable()->after('image_url');
            $table->enum('media_type', ['image', 'video'])->nullable()->after('media_url');
        });

        // Migrar datos de image_url a media_url si existen
        DB::table('display_announcements')
            ->whereNotNull('image_url')
            ->update([
                'media_url' => DB::raw('image_url'),
                'media_type' => 'image',
            ]);
    }

    public function down(): void
    {
        Schema::table('display_announcements', function (Blueprint $table) {
            $table->dropColumn(['media_url', 'media_type']);
        });
    }
};
