<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * เพิ่ม fields ที่ขาดหายไปใน Tours:
     * - tour_code format: NT202601XXXX
     * - ลบ primary_country_id (ใช้ tour_countries แทน)
     * - hashtags
     * - tour_type (JOIN, INCENTIVE, COLLECTIVE)
     * - discount_amount, discount_label
     * - shopping_highlight, food_highlight, special_highlight
     * - hotel_star_min, hotel_star_max
     * - display_price (ราคาแสดง = ราคาผู้ใหญ่ถูกที่สุด)
     */
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // Tour Type: กลุ่มทัวร์
            $table->enum('tour_type', ['join', 'incentive', 'collective'])
                ->default('join')
                ->after('title')
                ->comment('JOIN=จอยทัวร์, INCENTIVE=จัดกรุ๊ป, COLLECTIVE=รวมกรุ๊ป');
            
            // Hashtags (แทน keywords หรือเพิ่มเติม)
            $table->json('hashtags')->nullable()
                ->after('suitable_for')
                ->comment('["#ทัวร์ฮ่องกง", "#ช้อปปิ้ง"]');
            
            // Display Price & Discount (aggregated)
            $table->decimal('display_price', 10, 2)->nullable()
                ->after('min_price')
                ->comment('ราคาแสดง (ราคาผู้ใหญ่ถูกที่สุด)');
            $table->decimal('discount_amount', 10, 2)->nullable()
                ->after('display_price')
                ->comment('ส่วนลด (บาท)');
            $table->string('discount_label', 50)->nullable()
                ->after('discount_amount')
                ->comment('ข้อความส่วนลด: "ลด 2,000"');
            
            // Highlights แยกตามประเภท
            $table->string('shopping_highlight', 255)->nullable()
                ->after('highlights')
                ->comment('ไฮไลต์ช็อปปิ้ง');
            $table->string('food_highlight', 255)->nullable()
                ->after('shopping_highlight')
                ->comment('ไฮไลต์อาหาร');
            $table->string('special_highlight', 255)->nullable()
                ->after('food_highlight')
                ->comment('ไฮไลต์พิเศษ: ซีฟู๊ดสไตล์เวียดนาม');
            
            // Hotel Star Rating
            $table->tinyInteger('hotel_star_min')->unsigned()->nullable()
                ->after('special_highlight')
                ->comment('ระดับดาวโรงแรมต่ำสุด');
            $table->tinyInteger('hotel_star_max')->unsigned()->nullable()
                ->after('hotel_star_min')
                ->comment('ระดับดาวโรงแรมสูงสุด');
        });

        // Generate tour codes for existing tours (NT + YYMMDD + XXXX)
        $tours = DB::table('tours')->get();
        $dateCode = now()->format('ymd');
        
        foreach ($tours as $index => $tour) {
            $newCode = 'NT' . $dateCode . str_pad($index + 1, 4, '0', STR_PAD_LEFT);
            DB::table('tours')
                ->where('id', $tour->id)
                ->update(['tour_code' => $newCode]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn([
                'tour_type',
                'hashtags',
                'display_price',
                'discount_amount',
                'discount_label',
                'shopping_highlight',
                'food_highlight',
                'special_highlight',
                'hotel_star_min',
                'hotel_star_max',
            ]);
        });
    }
};
