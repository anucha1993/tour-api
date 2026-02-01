<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * เพิ่มฟิลด์ wholesaler_tour_code สำหรับเก็บรหัสทัวร์ของ Wholesaler
     */
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // รหัสทัวร์จาก Wholesaler เช่น TH-JP-001
            $table->string('wholesaler_tour_code', 100)
                  ->nullable()
                  ->after('tour_code')
                  ->comment('รหัสทัวร์จาก Wholesaler');
            
            // Index สำหรับค้นหา
            $table->index('wholesaler_tour_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropIndex(['wholesaler_tour_code']);
            $table->dropColumn('wholesaler_tour_code');
        });
    }
};
