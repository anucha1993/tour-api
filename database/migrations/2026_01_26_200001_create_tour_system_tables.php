<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * สร้างตารางทั้งหมดที่เกี่ยวกับ Tour:
     * - tours (ข้อมูลทัวร์หลัก)
     * - tour_locations (สถานที่/เมืองที่ไป)
     * - tour_gallery (รูปภาพ gallery)
     * - tour_transports (ยานพาหนะ - เที่ยวบิน/รถบัส/เรือ)
     * - tour_itineraries (โปรแกรมรายวัน)
     * - periods (รอบเดินทาง)
     * - offers (ราคา/เงื่อนไข)
     * - offer_promotions (โปรโมชัน)
     */
    public function up(): void
    {
        // ========================================
        // 1. TOURS - ข้อมูลทัวร์หลัก
        // ========================================
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wholesaler_id')->constrained('wholesalers')->cascadeOnDelete();
            $table->string('external_id', 50)->comment('รหัสจาก Wholesale');
            $table->string('tour_code', 50)->unique()->comment('รหัสทัวร์ในระบบเรา');
            $table->string('title', 255);
            
            // ประเทศ/ภูมิภาค
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('region', 50)->nullable()->comment('ASIA, EUROPE, etc.');
            $table->string('sub_region', 50)->nullable()->comment('EAST_ASIA, WEST_EUROPE');
            
            // ระยะเวลา
            $table->tinyInteger('duration_days')->unsigned();
            $table->tinyInteger('duration_nights')->unsigned();
            
            // เนื้อหา
            $table->text('highlights')->nullable()->comment('ไฮไลต์ทัวร์');
            $table->text('inclusions')->nullable()->comment('รวมอะไรบ้าง');
            $table->text('exclusions')->nullable()->comment('ไม่รวมอะไร');
            $table->text('conditions')->nullable()->comment('เงื่อนไขทั่วไป');
            
            // SEO
            $table->string('slug', 255)->unique()->nullable();
            $table->string('meta_title', 200)->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->json('keywords')->nullable()->comment('["ทัวร์ฮ่องกง", "จีน"]');
            
            // Media
            $table->string('cover_image_url', 500)->nullable();
            $table->string('cover_image_alt', 255)->nullable();
            $table->string('og_image_url', 500)->nullable()->comment('สำหรับ Social Share');
            $table->string('pdf_url', 500)->nullable();
            $table->string('docx_url', 500)->nullable();
            
            // Search/Filter (Aggregated - calculated from departures)
            $table->json('themes')->nullable()->comment('["SHOPPING", "CULTURE", "TEMPLE"]');
            $table->json('suitable_for')->nullable()->comment('["FAMILY", "COUPLE", "GROUP"]');
            $table->json('departure_airports')->nullable()->comment('["DMK", "BKK"]');
            $table->decimal('min_price', 10, 2)->nullable()->comment('ราคาต่ำสุด (calculated)');
            $table->decimal('max_price', 10, 2)->nullable()->comment('ราคาสูงสุด (calculated)');
            $table->date('next_departure_date')->nullable()->comment('วันเดินทางถัดไป (calculated)');
            $table->smallInteger('total_departures')->unsigned()->default(0);
            $table->smallInteger('available_seats')->unsigned()->default(0);
            $table->boolean('has_promotion')->default(false);
            
            // Display
            $table->string('badge', 20)->nullable()->comment('HOT, NEW, BEST_SELLER');
            $table->integer('popularity_score')->unsigned()->default(0);
            $table->integer('sort_order')->default(0);
            
            // Status
            $table->enum('status', ['draft', 'active', 'inactive'])->default('draft');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('updated_at_source')->nullable()->comment('เวลาอัปเดตจาก Wholesale');
            
            $table->timestamps();

            // Indexes
            $table->unique(['wholesaler_id', 'external_id']);
            $table->index(['country_id', 'status', 'is_published']);
            $table->index(['region', 'status']);
            $table->index('min_price');
            $table->index('next_departure_date');
            $table->index('popularity_score');
        });

        // ========================================
        // 2. TOUR_LOCATIONS - สถานที่/เมืองที่ไป
        // ========================================
        Schema::create('tour_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('name', 100)->comment('ชื่อสถานที่ (fallback ถ้าไม่มี city)');
            $table->string('name_en', 100)->nullable();
            $table->tinyInteger('sort_order')->unsigned()->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index('tour_id');
            $table->index('city_id');
        });

        // ========================================
        // 3. TOUR_GALLERY - รูปภาพ Gallery
        // ========================================
        Schema::create('tour_gallery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('thumbnail_url', 500)->nullable();
            $table->string('alt', 255)->nullable();
            $table->string('caption', 255)->nullable();
            $table->smallInteger('width')->unsigned()->nullable();
            $table->smallInteger('height')->unsigned()->nullable();
            $table->tinyInteger('sort_order')->unsigned()->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index('tour_id');
        });

        // ========================================
        // 4. TOUR_TRANSPORTS - ยานพาหนะ (เที่ยวบิน/รถบัส/เรือ)
        // ========================================
        Schema::create('tour_transports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->foreignId('transport_id')->nullable()->constrained('transports')->nullOnDelete();
            $table->string('transport_code', 10)->nullable()->comment('AQ, TG (fallback)');
            $table->string('transport_name', 100)->nullable();
            $table->string('flight_no', 10)->nullable()->comment('AQ1280');
            $table->string('route_from', 4)->nullable()->comment('IATA code: DMK');
            $table->string('route_to', 4)->nullable()->comment('IATA code: CAN');
            $table->time('depart_time')->nullable();
            $table->time('arrive_time')->nullable();
            $table->enum('transport_type', ['outbound', 'inbound', 'domestic'])->default('outbound');
            $table->tinyInteger('day_no')->unsigned()->nullable()->comment('วันที่เท่าไหร่');
            $table->tinyInteger('sort_order')->unsigned()->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index('tour_id');
            $table->index('transport_id');
        });

        // ========================================
        // 5. TOUR_ITINERARIES - โปรแกรมรายวัน
        // ========================================
        Schema::create('tour_itineraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->tinyInteger('day_no')->unsigned();
            $table->string('title', 255)->nullable()->comment('กรุงเทพฯ – กวางเจา');
            $table->text('description');
            $table->string('hotel_name', 150)->nullable();
            $table->tinyInteger('hotel_star')->unsigned()->nullable();
            $table->boolean('meal_breakfast')->default(false);
            $table->boolean('meal_lunch')->default(false);
            $table->boolean('meal_dinner')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index('tour_id');
            $table->unique(['tour_id', 'day_no']);
        });

        // ========================================
        // 6. PERIODS - รอบเดินทาง
        // ========================================
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->string('external_id', 50)->comment('รหัสจาก Wholesale');
            $table->string('period_code', 50)->comment('รหัสรอบ: CAN-260313A-AQ');
            $table->date('start_date');
            $table->date('end_date');
            $table->smallInteger('capacity')->unsigned()->default(0)->comment('ที่นั่งทั้งหมด');
            $table->smallInteger('booked')->unsigned()->default(0)->comment('จองแล้ว');
            $table->smallInteger('available')->unsigned()->default(0)->comment('คงเหลือ');
            $table->enum('status', ['open', 'closed', 'sold_out', 'cancelled'])->default('open');
            $table->timestamp('updated_at_source')->nullable()->comment('เวลาอัปเดตจาก Wholesale');
            $table->timestamps();

            // Indexes
            $table->unique(['tour_id', 'external_id']);
            $table->index(['start_date', 'status']);
            $table->index(['status', 'available']);
            $table->index('period_code');
        });

        // ========================================
        // 7. OFFERS - ราคา/เงื่อนไข (1 period : 1 offer)
        // ========================================
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->unique()->constrained('periods')->cascadeOnDelete();
            $table->string('currency', 3)->default('THB');
            
            // Pricing
            $table->decimal('price_adult', 10, 2)->comment('ราคาผู้ใหญ่');
            $table->decimal('price_child', 10, 2)->nullable()->comment('ราคาเด็ก');
            $table->decimal('price_child_nobed', 10, 2)->nullable()->comment('เด็กไม่เสริมเตียง');
            $table->decimal('price_infant', 10, 2)->nullable()->comment('ทารก');
            $table->decimal('price_joinland', 10, 2)->nullable()->comment('ไม่รวมตั๋วเครื่องบิน');
            $table->decimal('price_single', 10, 2)->nullable()->comment('พักเดี่ยวเพิ่ม');
            $table->decimal('deposit', 10, 2)->nullable()->comment('มัดจำ');
            
            // Commissions
            $table->decimal('commission_agent', 10, 2)->nullable();
            $table->decimal('commission_sale', 10, 2)->nullable();
            
            // Policies (Required!)
            $table->text('cancellation_policy')->comment('เงื่อนไขยกเลิก - Required');
            $table->text('refund_policy')->nullable();
            $table->text('notes')->nullable();
            
            // TTL
            $table->smallInteger('ttl_minutes')->unsigned()->default(10)->comment('อายุข้อมูลราคา (นาที)');
            
            $table->timestamps();
        });

        // ========================================
        // 8. OFFER_PROMOTIONS - โปรโมชัน
        // ========================================
        Schema::create('offer_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->string('promo_code', 50)->nullable();
            $table->string('name', 255);
            $table->enum('type', ['discount_amount', 'discount_percent', 'freebie'])->default('discount_amount');
            $table->decimal('value', 10, 2)->nullable()->comment('500 หรือ 10%');
            $table->enum('apply_to', ['per_pax', 'per_booking'])->default('per_pax');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->json('conditions')->nullable()->comment('{"min_pax": 2, "booking_before_days": 30}');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index('offer_id');
            $table->index(['start_at', 'end_at', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_promotions');
        Schema::dropIfExists('offers');
        Schema::dropIfExists('periods');
        Schema::dropIfExists('tour_itineraries');
        Schema::dropIfExists('tour_transports');
        Schema::dropIfExists('tour_gallery');
        Schema::dropIfExists('tour_locations');
        Schema::dropIfExists('tours');
    }
};
