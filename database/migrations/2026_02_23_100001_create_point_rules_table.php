<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_rules', function (Blueprint $table) {
            $table->id();
            $table->string('action')->unique(); // page_view, review, booking, referral, birthday, manual
            $table->string('name');             // ชื่อภาษาไทย
            $table->string('description')->nullable();
            $table->string('icon')->nullable();

            // Point calculation
            $table->enum('calc_type', ['fixed', 'percent'])->default('fixed');
            $table->unsignedInteger('points')->default(0);          // fixed points
            $table->decimal('percent_of_amount', 5, 2)->default(0); // e.g. 1.00 = 1฿ per 100฿

            // Limits
            $table->unsignedInteger('max_points_per_day')->nullable();  // daily cap
            $table->unsignedInteger('max_points_per_action')->nullable(); // per-action cap
            $table->unsignedInteger('cooldown_minutes')->default(0);  // cooldown per source

            // Expiry
            $table->unsignedInteger('expire_days')->default(365); // points expire after X days

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default rules
        DB::table('point_rules')->insert([
            [
                'action' => 'page_view',
                'name' => 'ดูหน้าทัวร์',
                'description' => 'ได้รับคะแนนจากการเข้าชมหน้าทัวร์',
                'icon' => '👁️',
                'calc_type' => 'fixed',
                'points' => 1,
                'percent_of_amount' => 0,
                'max_points_per_day' => 10,
                'max_points_per_action' => null,
                'cooldown_minutes' => 1440, // 24 hours per tour
                'expire_days' => 365,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'action' => 'review',
                'name' => 'เขียนรีวิว',
                'description' => 'ได้รับคะแนนเมื่อรีวิวได้รับการอนุมัติ',
                'icon' => '⭐',
                'calc_type' => 'fixed',
                'points' => 50,
                'percent_of_amount' => 0,
                'max_points_per_day' => null,
                'max_points_per_action' => null,
                'cooldown_minutes' => 0,
                'expire_days' => 365,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'action' => 'booking',
                'name' => 'จองทัวร์',
                'description' => 'ได้รับคะแนนเมื่อชำระเงินครบ (1 คะแนน ต่อ 100 บาท)',
                'icon' => '✈️',
                'calc_type' => 'percent',
                'points' => 0,
                'percent_of_amount' => 1.00, // 1 point per 100 baht
                'max_points_per_day' => null,
                'max_points_per_action' => 500,
                'cooldown_minutes' => 0,
                'expire_days' => 365,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'action' => 'referral',
                'name' => 'แนะนำเพื่อน',
                'description' => 'ได้รับคะแนนเมื่อเพื่อนสมัครสมาชิกสำเร็จ',
                'icon' => '👥',
                'calc_type' => 'fixed',
                'points' => 100,
                'percent_of_amount' => 0,
                'max_points_per_day' => null,
                'max_points_per_action' => null,
                'cooldown_minutes' => 0,
                'expire_days' => 365,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'action' => 'birthday',
                'name' => 'วันเกิด',
                'description' => 'ได้รับคะแนนพิเศษในเดือนเกิด',
                'icon' => '🎂',
                'calc_type' => 'fixed',
                'points' => 200,
                'percent_of_amount' => 0,
                'max_points_per_day' => null,
                'max_points_per_action' => null,
                'cooldown_minutes' => 0,
                'expire_days' => 365,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'action' => 'manual',
                'name' => 'ปรับคะแนนโดยแอดมิน',
                'description' => 'แอดมินปรับคะแนนด้วยตนเอง',
                'icon' => '🔧',
                'calc_type' => 'fixed',
                'points' => 0,
                'percent_of_amount' => 0,
                'max_points_per_day' => null,
                'max_points_per_action' => null,
                'cooldown_minutes' => 0,
                'expire_days' => 365,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('point_rules');
    }
};
