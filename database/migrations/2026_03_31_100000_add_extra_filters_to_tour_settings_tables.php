<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add extra filter toggles to international_tour_settings
        Schema::table('international_tour_settings', function (Blueprint $table) {
            $table->boolean('filter_festival')->default(true)->after('filter_price_range')->comment('แสดงเทศกาล/วันหยุด');
            $table->boolean('filter_promotion')->default(true)->after('filter_festival')->comment('แสดงโปรโมชั่น');
            $table->boolean('filter_theme')->default(true)->after('filter_promotion')->comment('แสดงหมวดหมู่');
            $table->boolean('filter_special_highlight')->default(true)->after('filter_theme')->comment('แสดงไฮไลท์พิเศษ');
            $table->boolean('filter_advanced')->default(true)->after('filter_special_highlight')->comment('แสดงตัวกรองเพิ่มเติม');
        });

        // Add extra filter toggles to domestic_tour_settings
        Schema::table('domestic_tour_settings', function (Blueprint $table) {
            $table->boolean('filter_festival')->default(true)->after('filter_price_range')->comment('แสดงเทศกาล/วันหยุด');
            $table->boolean('filter_promotion')->default(true)->after('filter_festival')->comment('แสดงโปรโมชั่น');
            $table->boolean('filter_theme')->default(true)->after('filter_promotion')->comment('แสดงหมวดหมู่');
            $table->boolean('filter_special_highlight')->default(true)->after('filter_theme')->comment('แสดงไฮไลท์พิเศษ');
            $table->boolean('filter_advanced')->default(true)->after('filter_special_highlight')->comment('แสดงตัวกรองเพิ่มเติม');
        });
    }

    public function down(): void
    {
        Schema::table('international_tour_settings', function (Blueprint $table) {
            $table->dropColumn(['filter_festival', 'filter_promotion', 'filter_theme', 'filter_special_highlight', 'filter_advanced']);
        });

        Schema::table('domestic_tour_settings', function (Blueprint $table) {
            $table->dropColumn(['filter_festival', 'filter_promotion', 'filter_theme', 'filter_special_highlight', 'filter_advanced']);
        });
    }
};
