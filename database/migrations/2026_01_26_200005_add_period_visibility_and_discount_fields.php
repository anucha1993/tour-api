<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * เพิ่มฟิลด์สำหรับ:
     * 1. periods: is_visible (สถานะแสดง), sale_status (สถานะวางขาย)
     * 2. offers: ราคาลดสำหรับแต่ละประเภท, โปรโมชั่น
     */
    public function up(): void
    {
        // Add visibility and sale_status to periods
        Schema::table('periods', function (Blueprint $table) {
            $table->boolean('is_visible')->default(true)->after('status')->comment('สถานะแสดง on/off');
            $table->enum('sale_status', ['available', 'booking', 'sold_out'])
                  ->default('available')
                  ->after('is_visible')
                  ->comment('สถานะวางขาย: ไลน์(available), จอง(booking), เต็ม(sold_out)');
        });

        // Add discount fields and promotion info to offers
        Schema::table('offers', function (Blueprint $table) {
            // ราคาลดสำหรับแต่ละประเภท (0 = ไม่มีส่วนลด)
            $table->decimal('discount_adult', 10, 2)->default(0)->after('price_adult')->comment('ส่วนลดผู้ใหญ่พัก 2-3');
            $table->decimal('discount_single', 10, 2)->default(0)->after('price_single_surcharge')->comment('ส่วนลดพักเดี่ยว');
            $table->decimal('discount_child_bed', 10, 2)->default(0)->after('price_child')->comment('ส่วนลดเด็กมีเตียง');
            $table->decimal('discount_child_nobed', 10, 2)->default(0)->after('price_child_nobed')->comment('ส่วนลดเด็กไม่มีเตียง');
            
            // โปรโมชั่น
            $table->string('promo_name', 255)->nullable()->after('notes')->comment('ชื่อโปรโมชั่น');
            $table->date('promo_start_date')->nullable()->after('promo_name')->comment('วันเริ่มโปรโมชั่น');
            $table->date('promo_end_date')->nullable()->after('promo_start_date')->comment('วันสิ้นสุดโปรโมชั่น');
            $table->integer('promo_quota')->nullable()->after('promo_end_date')->comment('จำนวนโปรโมชั่นที่ใช้ได้');
            $table->integer('promo_used')->default(0)->after('promo_quota')->comment('จำนวนโปรโมชั่นที่ใช้ไปแล้ว');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->dropColumn(['is_visible', 'sale_status']);
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn([
                'discount_adult',
                'discount_single',
                'discount_child_bed',
                'discount_child_nobed',
                'promo_name',
                'promo_start_date',
                'promo_end_date',
                'promo_quota',
                'promo_used',
            ]);
        });
    }
};
