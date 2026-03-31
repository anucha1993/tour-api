<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add pinned_tour_codes to country covers (comma-separated tour codes)
        Schema::table('international_tour_country_covers', function (Blueprint $table) {
            $table->text('pinned_tour_codes')->nullable()->after('hero_text')
                  ->comment('Comma-separated tour codes to pin at top');
        });

        // Add pagination_mode to international tour settings
        Schema::table('international_tour_settings', function (Blueprint $table) {
            $table->string('pagination_mode', 20)->default('page')->after('per_page')
                  ->comment('page or load_more');
        });

        // Add pagination_mode to domestic tour settings
        Schema::table('domestic_tour_settings', function (Blueprint $table) {
            $table->string('pagination_mode', 20)->default('page')->after('per_page')
                  ->comment('page or load_more');
        });
    }

    public function down(): void
    {
        Schema::table('international_tour_country_covers', function (Blueprint $table) {
            $table->dropColumn('pinned_tour_codes');
        });
        Schema::table('international_tour_settings', function (Blueprint $table) {
            $table->dropColumn('pagination_mode');
        });
        Schema::table('domestic_tour_settings', function (Blueprint $table) {
            $table->dropColumn('pagination_mode');
        });
    }
};
