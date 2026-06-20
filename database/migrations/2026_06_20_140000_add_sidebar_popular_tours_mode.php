<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('international_tour_settings', function (Blueprint $table) {
            $table->string('sidebar_popular_tours_mode', 20)->default('popular')->after('sidebar_popular_tours_limit');
            $table->string('sidebar_popular_tours_codes', 1000)->nullable()->after('sidebar_popular_tours_mode');
        });
    }

    public function down(): void
    {
        Schema::table('international_tour_settings', function (Blueprint $table) {
            $table->dropColumn(['sidebar_popular_tours_mode', 'sidebar_popular_tours_codes']);
        });
    }
};
