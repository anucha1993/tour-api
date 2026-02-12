<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * เพิ่ม display_mode และ badge_icon ให้ tour_tabs
     * display_mode: tab = แสดงเป็นแท็บหน้าแรก, badge = แสดง badge บนการ์ดทัวร์ทุกหน้า, both = ทั้งสอง
     */
    public function up(): void
    {
        Schema::table('tour_tabs', function (Blueprint $table) {
            $table->enum('display_mode', ['tab', 'badge', 'both'])
                ->default('tab')
                ->after('badge_color')
                ->comment('tab=แท็บหน้าแรก, badge=badge ทุกหน้า, both=ทั้งสอง');
            $table->string('badge_icon', 10)->nullable()
                ->after('display_mode')
                ->comment('ไอคอน badge เช่น 🔥 ✨ 👑');
        });
    }

    public function down(): void
    {
        Schema::table('tour_tabs', function (Blueprint $table) {
            $table->dropColumn(['display_mode', 'badge_icon']);
        });
    }
};
