<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');           // Bronze, Silver, Gold, Platinum
            $table->string('slug')->unique(); // bronze, silver, gold, platinum
            $table->string('icon')->nullable();
            $table->string('color', 20)->default('#6B7280'); // hex color
            $table->unsignedInteger('min_points')->default(0); // lifetime points to reach
            $table->decimal('discount_percent', 5, 2)->default(0); // e.g. 5.00 = 5%
            $table->decimal('point_multiplier', 4, 2)->default(1.00); // e.g. 1.5x
            $table->decimal('redemption_rate', 8, 2)->default(1.00); // 1 point = ฿X
            $table->json('benefits')->nullable(); // extra benefits JSON
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false); // default level for new members
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default levels
        DB::table('member_levels')->insert([
            [
                'name' => 'Bronze',
                'slug' => 'bronze',
                'icon' => '🥉',
                'color' => '#CD7F32',
                'min_points' => 0,
                'discount_percent' => 0,
                'point_multiplier' => 1.00,
                'redemption_rate' => 1.00,
                'benefits' => json_encode(['description' => 'สมาชิกระดับเริ่มต้น']),
                'sort_order' => 1,
                'is_default' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Silver',
                'slug' => 'silver',
                'icon' => '🥈',
                'color' => '#C0C0C0',
                'min_points' => 1000,
                'discount_percent' => 2.00,
                'point_multiplier' => 1.20,
                'redemption_rate' => 1.00,
                'benefits' => json_encode(['description' => 'ส่วนลด 2%, คะแนน x1.2']),
                'sort_order' => 2,
                'is_default' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Gold',
                'slug' => 'gold',
                'icon' => '🥇',
                'color' => '#FFD700',
                'min_points' => 5000,
                'discount_percent' => 5.00,
                'point_multiplier' => 1.50,
                'redemption_rate' => 1.20,
                'benefits' => json_encode(['description' => 'ส่วนลด 5%, คะแนน x1.5, แลกพอยท์ได้มากกว่า']),
                'sort_order' => 3,
                'is_default' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Platinum',
                'slug' => 'platinum',
                'icon' => '💎',
                'color' => '#E5E4E2',
                'min_points' => 15000,
                'discount_percent' => 8.00,
                'point_multiplier' => 2.00,
                'redemption_rate' => 1.50,
                'benefits' => json_encode(['description' => 'ส่วนลด 8%, คะแนน x2.0, แลกพอยท์คุ้มสุด']),
                'sort_order' => 4,
                'is_default' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('member_levels');
    }
};
