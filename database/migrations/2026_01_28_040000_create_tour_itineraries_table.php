<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * ตาราง tour_itineraries - โปรแกรมทัวร์รายวัน
     */
    public function up(): void
    {
        Schema::create('tour_itineraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            
            // ข้อมูลวัน
            $table->unsignedTinyInteger('day_number'); // วันที่ 1, 2, 3...
            $table->string('title', 500)->nullable(); // หัวข้อวัน เช่น "สนามบินสุวรรณภูมิ กรุงเทพ"
            $table->text('description')->nullable(); // รายละเอียดกิจกรรม
            
            // สถานที่ (เก็บเป็น JSON array)
            // ["ศาลเจ้าดาไซฟุ", "หมู่บ้านยูฟูอิน", "AEON MALL"]
            $table->json('places')->nullable();
            
            // อาหาร
            $table->boolean('has_breakfast')->default(false);
            $table->boolean('has_lunch')->default(false);
            $table->boolean('has_dinner')->default(false);
            $table->string('meals_note', 500)->nullable(); // หมายเหตุอาหาร เช่น "อาหารไทย"
            
            // ที่พัก
            $table->string('accommodation', 500)->nullable(); // ชื่อโรงแรม
            $table->unsignedTinyInteger('hotel_star')->nullable(); // ระดับดาว
            
            // รูปภาพ
            $table->json('images')->nullable(); // URLs รูปภาพ
            
            // ลำดับการแสดงผล (optional - ถ้าต้องการเรียงต่างจาก day_number)
            $table->unsignedSmallInteger('sort_order')->nullable();
            
            $table->timestamps();
            
            // Index
            $table->index(['tour_id', 'day_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_itineraries');
    }
};
