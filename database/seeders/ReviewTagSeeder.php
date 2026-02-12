<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ReviewTag;

class ReviewTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            ['name' => 'ไกด์ดีมาก', 'slug' => 'guide-excellent', 'icon' => '👨‍✈️'],
            ['name' => 'อาหารอร่อย', 'slug' => 'food-delicious', 'icon' => '🍜'],
            ['name' => 'โรงแรมสะอาด', 'slug' => 'hotel-clean', 'icon' => '🏨'],
            ['name' => 'คุ้มค่า', 'slug' => 'value-for-money', 'icon' => '💰'],
            ['name' => 'โปรแกรมตรงปก', 'slug' => 'program-accurate', 'icon' => '✅'],
            ['name' => 'วิวสวย', 'slug' => 'beautiful-view', 'icon' => '🏞️'],
            ['name' => 'สนุกสนาน', 'slug' => 'fun', 'icon' => '🎉'],
            ['name' => 'ถ่ายรูปสวย', 'slug' => 'photogenic', 'icon' => '📸'],
            ['name' => 'เหมาะกับครอบครัว', 'slug' => 'family-friendly', 'icon' => '👨‍👩‍👧‍👦'],
            ['name' => 'เหมาะกับคู่รัก', 'slug' => 'romantic', 'icon' => '❤️'],
            ['name' => 'บริการดีเยี่ยม', 'slug' => 'excellent-service', 'icon' => '⭐'],
            ['name' => 'การเดินทางสะดวก', 'slug' => 'convenient-travel', 'icon' => '🚌'],
            ['name' => 'เวลาพอดี', 'slug' => 'good-timing', 'icon' => '⏰'],
            ['name' => 'ของฝากเยอะ', 'slug' => 'lots-of-souvenirs', 'icon' => '🎁'],
            ['name' => 'อยากกลับไปอีก', 'slug' => 'want-to-return', 'icon' => '🔁'],
        ];

        foreach ($tags as $index => $tag) {
            ReviewTag::updateOrCreate(
                ['slug' => $tag['slug']],
                array_merge($tag, ['sort_order' => $index, 'is_active' => true])
            );
        }
    }
}
