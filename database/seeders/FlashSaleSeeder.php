<?php

namespace Database\Seeders;

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Tour;
use Illuminate\Database\Seeder;

class FlashSaleSeeder extends Seeder
{
    public function run(): void
    {
        // Create a test flash sale running now for 3 days
        $flashSale = FlashSale::updateOrCreate(
            ['title' => 'Flash Sale สุดพิเศษ!'],
            [
                'description' => '⚡ ลดราคาพิเศษ! จองด่วนก่อนหมดเวลา ที่นั่งมีจำนวนจำกัด',
                'start_date' => now(),
                'end_date' => now()->addDays(3),
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        // Clear existing items
        $flashSale->items()->delete();

        // Get 10 active tours with price, diverse selection
        $tours = Tour::where('status', 'active')
            ->whereNotNull('min_price')
            ->where('min_price', '>', 0)
            ->orderByDesc('view_count')
            ->limit(10)
            ->get();

        // Predefined sold quantities for realism
        $soldData = [0, 3, 7, 0, 5, 12, 0, 2, 8, 0];

        foreach ($tours as $index => $tour) {
            $originalPrice = $tour->min_price ?? $tour->price_adult;

            // Varied discounts: 10-35%
            $discountPercents = [25, 15, 30, 20, 18, 35, 12, 28, 22, 10];
            $discountPercent = $discountPercents[$index] ?? rand(10, 30);
            $flashPrice = round($originalPrice * (1 - $discountPercent / 100), -2);

            // Quantity limits: some limited, some unlimited
            $quantityLimits = [20, null, 15, null, 10, 20, null, 8, 15, null];
            $quantityLimit = $quantityLimits[$index] ?? null;
            $quantitySold = $quantityLimit ? min($soldData[$index] ?? 0, $quantityLimit) : 0;

            FlashSaleItem::create([
                'flash_sale_id' => $flashSale->id,
                'tour_id' => $tour->id,
                'flash_price' => $flashPrice,
                'original_price' => $originalPrice,
                'discount_percent' => round(($originalPrice - $flashPrice) / $originalPrice * 100, 1),
                'quantity_limit' => $quantityLimit,
                'quantity_sold' => $quantitySold,
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }

        $itemCount = $flashSale->items()->count();
        $this->command->info("Flash Sale created with {$itemCount} tours (10 target)");
    }
}
