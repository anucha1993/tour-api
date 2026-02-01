<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * เพิ่มฟิลด์ travel_period, airline_id, airline_name
     */
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // ช่วงเวลาเดินทาง เช่น "ม.ค. - มี.ค. 69", "ต.ค. - ธ.ค. 69"
            $table->string('travel_period', 100)
                  ->nullable()
                  ->after('duration_nights')
                  ->comment('ช่วงเวลาเดินทาง เช่น ม.ค.-มี.ค. 69');
            
            // สายการบินหลัก (อ้างอิงจาก transports table)
            $table->foreignId('airline_id')
                  ->nullable()
                  ->after('travel_period')
                  ->constrained('transports')
                  ->nullOnDelete();
            
            // ชื่อสายการบินหลัก (denormalized สำหรับแสดงผลเร็ว)
            $table->string('airline_name', 100)
                  ->nullable()
                  ->after('airline_id')
                  ->comment('ชื่อสายการบินหลัก');
            
            // โค้ดสายการบิน
            $table->string('airline_code', 10)
                  ->nullable()
                  ->after('airline_name')
                  ->comment('รหัส IATA เช่น TG, AQ');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropForeign(['airline_id']);
            $table->dropColumn(['travel_period', 'airline_id', 'airline_name', 'airline_code']);
        });
    }
};
