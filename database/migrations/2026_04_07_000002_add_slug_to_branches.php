<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('branches', 'slug')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->string('slug')->nullable()->after('name');
                $table->index('slug');
            });

            // Generate slugs for existing branches
            $branches = \DB::table('branches')->get();
            foreach ($branches as $branch) {
                \DB::table('branches')
                    ->where('id', $branch->id)
                    ->update(['slug' => Str::slug($branch->name)]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
