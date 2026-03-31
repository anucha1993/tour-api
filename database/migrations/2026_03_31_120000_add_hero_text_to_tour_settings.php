<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add hero_text to international_tour_settings (default hero text)
        Schema::table('international_tour_settings', function (Blueprint $table) {
            $table->string('hero_text')->nullable()->after('cover_image_position');
        });

        // Add hero_text to international_tour_country_covers (per-country hero text)
        Schema::table('international_tour_country_covers', function (Blueprint $table) {
            $table->string('hero_text')->nullable()->after('alt_text');
        });

        // Add hero_text to domestic_tour_settings (default hero text)
        Schema::table('domestic_tour_settings', function (Blueprint $table) {
            $table->string('hero_text')->nullable()->after('cover_image_position');
        });
    }

    public function down(): void
    {
        Schema::table('international_tour_settings', function (Blueprint $table) {
            $table->dropColumn('hero_text');
        });
        Schema::table('international_tour_country_covers', function (Blueprint $table) {
            $table->dropColumn('hero_text');
        });
        Schema::table('domestic_tour_settings', function (Blueprint $table) {
            $table->dropColumn('hero_text');
        });
    }
};
