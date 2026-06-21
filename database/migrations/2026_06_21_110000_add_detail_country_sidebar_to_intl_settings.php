<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('international_tour_settings', function (Blueprint $table) {
            // Sidebar "ทัวร์ประเทศเดียวกัน" on tour detail page (/tours/{slug})
            $table->boolean('detail_country_sidebar_enabled')->default(true)->after('sidebar_popular_tours_codes');
            $table->string('detail_country_sidebar_title', 100)->nullable()->after('detail_country_sidebar_enabled');
            $table->unsignedSmallInteger('detail_country_sidebar_limit')->default(8)->after('detail_country_sidebar_title');
            // same_city = เมืองเดียวกันมากที่สุดก่อน, popular, price_asc, latest
            $table->string('detail_country_sidebar_sort', 20)->default('same_city')->after('detail_country_sidebar_limit');
        });
    }

    public function down(): void
    {
        Schema::table('international_tour_settings', function (Blueprint $table) {
            $table->dropColumn([
                'detail_country_sidebar_enabled',
                'detail_country_sidebar_title',
                'detail_country_sidebar_limit',
                'detail_country_sidebar_sort',
            ]);
        });
    }
};
