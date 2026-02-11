<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('international_tour_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('ชื่อ setting เช่น "default", "japan_special"');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            
            // Conditions
            $table->json('conditions')->nullable()->comment('เงื่อนไขการแสดงทัวร์ [{type, value}]');
            
            // Display
            $table->integer('display_limit')->default(50)->comment('จำนวนทัวร์ที่แสดง');
            $table->integer('per_page')->default(10)->comment('จำนวนต่อหน้า');
            $table->string('sort_by')->default('popular')->comment('popular, price_asc, price_desc, newest, departure_date');
            
            // Tour card display options
            $table->boolean('show_periods')->default(true)->comment('แสดงรอบเดินทาง');
            $table->integer('max_periods_display')->default(6)->comment('จำนวนรอบที่แสดงสูงสุด');
            $table->boolean('show_transport')->default(true)->comment('แสดงสายการบิน');
            $table->boolean('show_hotel_star')->default(true)->comment('แสดงดาวโรงแรม');
            $table->boolean('show_meal_count')->default(true)->comment('แสดงจำนวนมื้ออาหาร');
            $table->boolean('show_commission')->default(false)->comment('แสดงคอมมิชชั่น (สำหรับ agent)');
            
            // Filters shown to user
            $table->boolean('filter_country')->default(true);
            $table->boolean('filter_city')->default(true);
            $table->boolean('filter_search')->default(true);
            $table->boolean('filter_airline')->default(true);
            $table->boolean('filter_departure_month')->default(true);
            $table->boolean('filter_price_range')->default(true);
            
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('international_tour_settings');
    }
};
