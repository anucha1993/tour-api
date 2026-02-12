<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * เพิ่มวันหมดอายุสำหรับ badge
     * null = ไม่หมดอายุ, มีค่า = หมดอายุตามวันที่กำหนด
     */
    public function up(): void
    {
        Schema::table('tour_tabs', function (Blueprint $table) {
            $table->timestamp('badge_expires_at')->nullable()
                ->after('badge_icon')
                ->comment('วันหมดอายุ badge, null = ไม่หมดอายุ');
        });
    }

    public function down(): void
    {
        Schema::table('tour_tabs', function (Blueprint $table) {
            $table->dropColumn('badge_expires_at');
        });
    }
};
