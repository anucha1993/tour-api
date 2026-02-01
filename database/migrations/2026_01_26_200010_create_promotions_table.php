<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique()->nullable();
            $table->text('description')->nullable();
            $table->enum('type', ['discount_amount', 'discount_percent', 'free_gift', 'installment', 'special'])->default('special');
            $table->decimal('discount_value', 10, 2)->nullable()->comment('ส่วนลด (บาท หรือ %)');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed default promotions
        DB::table('promotions')->insert([
            ['id' => 2, 'name' => 'รูดบัตร ไม่ชาร์จ หรือ ผ่อน 0% นาน 3 เดือน', 'code' => 'CARD_0_3M', 'type' => 'installment', 'discount_value' => null, 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'รูดบัตร ไม่ชาร์จ', 'code' => 'CARD_NO_CHARGE', 'type' => 'installment', 'discount_value' => null, 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9, 'name' => 'รับส่วนลด 2,000 บาท', 'code' => 'DISCOUNT_2000', 'type' => 'discount_amount', 'discount_value' => 2000, 'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'name' => 'รับส่วนลด 500 บาท', 'code' => 'DISCOUNT_500', 'type' => 'discount_amount', 'discount_value' => 500, 'is_active' => true, 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'name' => 'รับส่วนลด 1,000 บาท', 'code' => 'DISCOUNT_1000', 'type' => 'discount_amount', 'discount_value' => 1000, 'is_active' => true, 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'name' => 'รับส่วนลด 1,500 บาท', 'code' => 'DISCOUNT_1500', 'type' => 'discount_amount', 'discount_value' => 1500, 'is_active' => true, 'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 13, 'name' => 'รับฟรี กระเป๋าลาก เอนกประสงค์', 'code' => 'FREE_BAG', 'type' => 'free_gift', 'discount_value' => null, 'is_active' => true, 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 14, 'name' => 'Promotion! 8.8', 'code' => 'PROMO_8_8', 'type' => 'special', 'discount_value' => null, 'is_active' => true, 'sort_order' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 16, 'name' => 'Promotion! 9.9', 'code' => 'PROMO_9_9', 'type' => 'special', 'discount_value' => null, 'is_active' => true, 'sort_order' => 9, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 17, 'name' => 'Promotion! 10.10', 'code' => 'PROMO_10_10', 'type' => 'special', 'discount_value' => null, 'is_active' => true, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 18, 'name' => 'Promotion! 11.11', 'code' => 'PROMO_11_11', 'type' => 'special', 'discount_value' => null, 'is_active' => true, 'sort_order' => 11, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 19, 'name' => 'รับส่วนลด 2,500 บาท', 'code' => 'DISCOUNT_2500', 'type' => 'discount_amount', 'discount_value' => 2500, 'is_active' => true, 'sort_order' => 12, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'name' => 'Promotion! 12.12', 'code' => 'PROMO_12_12', 'type' => 'special', 'discount_value' => null, 'is_active' => true, 'sort_order' => 13, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 21, 'name' => 'Promotion! 4.4', 'code' => 'PROMO_4_4', 'type' => 'special', 'discount_value' => null, 'is_active' => true, 'sort_order' => 14, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 22, 'name' => 'Promotion! 5.5', 'code' => 'PROMO_5_5', 'type' => 'special', 'discount_value' => null, 'is_active' => true, 'sort_order' => 15, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 23, 'name' => 'Promotion! 6.6', 'code' => 'PROMO_6_6', 'type' => 'special', 'discount_value' => null, 'is_active' => true, 'sort_order' => 16, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 24, 'name' => 'Promotion! 7.7', 'code' => 'PROMO_7_7', 'type' => 'special', 'discount_value' => null, 'is_active' => true, 'sort_order' => 17, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 25, 'name' => 'รับส่วนลด 3,000 บาท', 'code' => 'DISCOUNT_3000', 'type' => 'discount_amount', 'discount_value' => 3000, 'is_active' => true, 'sort_order' => 18, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 26, 'name' => 'ผ่อน 0% นาน 3 เดือน', 'code' => 'INSTALLMENT_0_3M', 'type' => 'installment', 'discount_value' => null, 'is_active' => true, 'sort_order' => 19, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Add promotion_id to offers table
        Schema::table('offers', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('promo_used')->constrained('promotions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropForeign(['promotion_id']);
            $table->dropColumn('promotion_id');
        });
        
        Schema::dropIfExists('promotions');
    }
};
