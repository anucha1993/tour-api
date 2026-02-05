<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * past_period_handling options:
     * - 'skip': ข้าม period ที่เดินทางไปแล้ว ไม่บันทึกข้อมูล
     * - 'close': บันทึก period แต่ตั้งสถานะเป็น closed
     * - 'keep': บันทึกปกติ ไม่เปลี่ยนสถานะ
     */
    public function up(): void
    {
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            $table->string('past_period_handling', 20)->default('skip')->after('sync_mode')
                ->comment('How to handle past periods: skip, close, keep');
            $table->integer('past_period_threshold_days')->default(0)->after('past_period_handling')
                ->comment('Days before today to consider as past (0 = today)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wholesaler_api_configs', function (Blueprint $table) {
            $table->dropColumn(['past_period_handling', 'past_period_threshold_days']);
        });
    }
};
