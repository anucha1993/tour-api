<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tour_tabs', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // ชื่อ Tab เช่น "ทัวร์ยอดนิยม"
            $table->string('slug')->unique();          // URL-friendly name
            $table->string('description')->nullable(); // คำอธิบายสั้น
            $table->string('icon')->nullable();        // Icon name (lucide)
            $table->string('badge_text')->nullable();  // ข้อความ badge เช่น "HOT", "NEW"
            $table->string('badge_color')->nullable(); // สี badge
            
            // Filter conditions as JSON
            $table->json('conditions')->nullable();
            
            // Display settings
            $table->integer('display_limit')->default(8);  // จำนวนทัวร์ที่แสดง
            $table->string('sort_by')->default('popular'); // popular, price_asc, price_desc, newest, departure_date
            $table->integer('sort_order')->default(0);     // ลำดับ Tab
            $table->boolean('is_active')->default(true);   // เปิด/ปิดใช้งาน
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_tabs');
    }
};
