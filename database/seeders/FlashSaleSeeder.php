<?php

namespace Database\Seeders;

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Period;
use App\Models\Tour;
use Illuminate\Database\Seeder;

class FlashSaleSeeder extends Seeder
{
    public function run(): void
    {
        // Create/update the flash sale campaign (3-day window)
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

        // Get popular active tours that have open periods with offers
        $tours = Tour::where('status', 'active')
            ->whereNotNull('min_price')
            ->where('min_price', '>', 0)
            ->whereHas('periods', function ($q) {
                $q->whereIn('status', ['open', 'confirmed'])
                  ->whereHas('offer');
            })
            ->orderByDesc('view_count')
            ->limit(10)
            ->get();

        // Discount % options for variety
        $discountOptions = [25, 15, 30, 20, 18, 35, 12, 28, 22, 10];
        // Some items get quantity limits, some don't
        $quantityLimits = [20, null, 15, null, 10, 20, null, 8, 15, null];
        $soldData = [0, 3, 7, 0, 5, 12, 0, 2, 8, 0];

        $sortOrder = 0;
        $totalItems = 0;

        foreach ($tours as $index => $tour) {
            // Get periods with offers for this tour (soonest first, max 2 per tour)
            $periods = Period::where('tour_id', $tour->id)
                ->whereIn('status', ['open', 'confirmed'])
                ->whereHas('offer')
                ->with('offer')
                ->orderBy('start_date')
                ->limit(2)
                ->get();

            if ($periods->isEmpty()) continue;

            $discountPercent = $discountOptions[$index] ?? rand(10, 30);

            foreach ($periods as $pIdx => $period) {
                $originalPrice = $period->offer->price_adult ?? $tour->min_price ?? 0;
                if ($originalPrice <= 0) continue;

                $flashPrice = round($originalPrice * (1 - $discountPercent / 100), -2);

                $quantityLimit = $quantityLimits[$index] ?? null;
                $quantitySold = $quantityLimit ? min($soldData[$index] ?? 0, $quantityLimit) : 0;

                // Vary flash_end_date: some use campaign end, some end earlier
                $flashEndDate = null;
                if ($pIdx === 0) {
                    // First period of each tour: custom end date (1-2 days from now)
                    $flashEndDate = now()->addHours(rand(18, 48))->startOfHour();
                }
                // Second period: null (follows campaign end_date)

                FlashSaleItem::create([
                    'flash_sale_id' => $flashSale->id,
                    'tour_id' => $tour->id,
                    'period_id' => $period->id,
                    'flash_price' => $flashPrice,
                    'original_price' => $originalPrice,
                    'discount_percent' => round(($originalPrice - $flashPrice) / $originalPrice * 100, 1),
                    'quantity_limit' => $quantityLimit,
                    'quantity_sold' => $quantitySold,
                    'flash_end_date' => $flashEndDate,
                    'sort_order' => $sortOrder++,
                    'is_active' => true,
                ]);
                $totalItems++;
            }
        }

        $this->command->info("Flash Sale seeded: {$totalItems} period-level items across {$tours->count()} tours");
    }
}
