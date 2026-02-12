<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tour;
use App\Models\TourLocation;
use App\Models\TourGallery;
use App\Models\TourTransport;
use App\Models\TourItinerary;
use App\Models\Period;
use App\Models\Offer;
use App\Models\OfferPromotion;
use App\Models\Country;
use App\Models\Wholesaler;
use App\Models\Transport;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TourSeeder extends Seeder
{
    /**
     * 10 ทัวร์ตัวอย่าง จาก 10 ประเทศ แต่ละทัวร์มี 10+ periods
     */
    public function run(): void
    {
        // Get or create a wholesaler
        $wholesaler = Wholesaler::first();
        if (!$wholesaler) {
            $wholesaler = Wholesaler::create([
                'name' => 'Zego Travel',
                'code' => 'ZEGO',
                'contact_name' => 'Admin',
                'contact_email' => 'admin@zegotravel.com',
                'is_active' => true,
            ]);
        }

        // Get some transports (airlines)
        $airlines = Transport::where('type', 'airline')->limit(10)->get();
        $defaultAirline = $airlines->first();

        // Tour data - 10 tours from 10 countries
        $toursData = [
            [
                'country_code' => 'JP',
                'sub_region' => 'EAST_ASIA',
                'title' => 'ทัวร์ญี่ปุ่น โตเกียว ฟูจิ โอซาก้า',
                'duration_days' => 6,
                'duration_nights' => 4,
                'highlights' => 'ชมภูเขาไฟฟูจิ เที่ยวโตเกียว ช้อปปิ้งชินจูกุ วัดอาซากุสะ ปราสาทโอซาก้า',
                'themes' => ['SHOPPING', 'CULTURE', 'TEMPLE'],
                'suitable_for' => ['FAMILY', 'COUPLE'],
                'locations' => ['โตเกียว', 'ฟูจิ', 'โอซาก้า', 'เกียวโต'],
                'base_price' => 39900,
                'airline_code' => 'TG',
                'departure_airport' => 'BKK',
            ],
            [
                'country_code' => 'KR',
                'sub_region' => 'EAST_ASIA',
                'title' => 'ทัวร์เกาหลี โซล เอเวอร์แลนด์ นามิ',
                'duration_days' => 5,
                'duration_nights' => 3,
                'highlights' => 'สวนสนุกเอเวอร์แลนด์ เกาะนามิ พระราชวังเคียงบก ช้อปปิ้งเมียงดง',
                'themes' => ['SHOPPING', 'CULTURE', 'ADVENTURE'],
                'suitable_for' => ['FAMILY', 'GROUP'],
                'locations' => ['โซล', 'นามิ', 'เอเวอร์แลนด์'],
                'base_price' => 25900,
                'airline_code' => 'TG',
                'departure_airport' => 'BKK',
            ],
            [
                'country_code' => 'CN',
                'sub_region' => 'EAST_ASIA',
                'title' => 'ทัวร์จีน ปักกิ่ง กำแพงเมืองจีน พระราชวังต้องห้าม',
                'duration_days' => 5,
                'duration_nights' => 4,
                'highlights' => 'กำแพงเมืองจีน พระราชวังต้องห้าม หอฟ้าเทียนถาน จัตุรัสเทียนอันเหมิน',
                'themes' => ['CULTURE', 'TEMPLE'],
                'suitable_for' => ['FAMILY', 'GROUP'],
                'locations' => ['ปักกิ่ง', 'กำแพงเมืองจีน'],
                'base_price' => 19900,
                'airline_code' => 'CA',
                'departure_airport' => 'BKK',
            ],
            [
                'country_code' => 'VN',
                'sub_region' => 'SOUTHEAST_ASIA',
                'title' => 'ทัวร์เวียดนาม ดานัง ฮอยอัน บานาฮิลล์',
                'duration_days' => 4,
                'duration_nights' => 3,
                'highlights' => 'สะพานมือยักษ์ บานาฮิลล์ เมืองเก่าฮอยอัน หาดมีเค',
                'themes' => ['NATURE', 'CULTURE', 'BEACH'],
                'suitable_for' => ['COUPLE', 'FAMILY'],
                'locations' => ['ดานัง', 'ฮอยอัน', 'บานาฮิลล์'],
                'base_price' => 12900,
                'airline_code' => 'VJ',
                'departure_airport' => 'BKK',
            ],
            [
                'country_code' => 'SG',
                'sub_region' => 'SOUTHEAST_ASIA',
                'title' => 'ทัวร์สิงคโปร์ ยูนิเวอร์แซล การ์เด้นบายเดอะเบย์',
                'duration_days' => 4,
                'duration_nights' => 3,
                'highlights' => 'ยูนิเวอร์แซล สตูดิโอ การ์เด้นบายเดอะเบย์ มารีน่าเบย์แซนด์ เมอร์ไลออน',
                'themes' => ['SHOPPING', 'ADVENTURE', 'FAMILY'],
                'suitable_for' => ['FAMILY', 'COUPLE'],
                'locations' => ['สิงคโปร์', 'เซ็นโตซ่า'],
                'base_price' => 18900,
                'airline_code' => 'SQ',
                'departure_airport' => 'BKK',
            ],
            [
                'country_code' => 'TW',
                'sub_region' => 'EAST_ASIA',
                'title' => 'ทัวร์ไต้หวัน ไทเป จิ่วเฟิ่น อาลีซาน',
                'duration_days' => 5,
                'duration_nights' => 4,
                'highlights' => 'ตึกไทเป 101 หมู่บ้านจิ่วเฟิ่น อุทยานอาลีซาน ทะเลสาบสุริยันจันทรา',
                'themes' => ['NATURE', 'CULTURE', 'SHOPPING'],
                'suitable_for' => ['FAMILY', 'GROUP'],
                'locations' => ['ไทเป', 'จิ่วเฟิ่น', 'อาลีซาน', 'ทะเลสาบสุริยันจันทรา'],
                'base_price' => 22900,
                'airline_code' => 'CI',
                'departure_airport' => 'BKK',
            ],
            [
                'country_code' => 'FR',
                'sub_region' => 'WEST_EUROPE',
                'title' => 'ทัวร์ฝรั่งเศส ปารีส หอไอเฟล พระราชวังแวร์ซาย',
                'duration_days' => 8,
                'duration_nights' => 6,
                'highlights' => 'หอไอเฟล พิพิธภัณฑ์ลูฟร์ พระราชวังแวร์ซาย ล่องเรือแม่น้ำแซน ช้อปปิ้งชองป์เซลิเซ่',
                'themes' => ['CULTURE', 'SHOPPING', 'HONEYMOON'],
                'suitable_for' => ['COUPLE', 'PREMIUM'],
                'locations' => ['ปารีส', 'แวร์ซาย', 'มงต์มาร์ต'],
                'base_price' => 89900,
                'airline_code' => 'TG',
                'departure_airport' => 'BKK',
            ],
            [
                'country_code' => 'IT',
                'sub_region' => 'SOUTH_EUROPE',
                'title' => 'ทัวร์อิตาลี โรม ฟลอเรนซ์ เวนิส มิลาน',
                'duration_days' => 9,
                'duration_nights' => 7,
                'highlights' => 'โคลอสเซียม น้ำพุเทรวี่ หอเอนปิซ่า มหาวิหารเซนต์ปีเตอร์ ล่องเรือกอนโดล่า',
                'themes' => ['CULTURE', 'HONEYMOON', 'PREMIUM'],
                'suitable_for' => ['COUPLE', 'PREMIUM'],
                'locations' => ['โรม', 'ฟลอเรนซ์', 'เวนิส', 'มิลาน', 'ปิซ่า'],
                'base_price' => 99900,
                'airline_code' => 'TG',
                'departure_airport' => 'BKK',
            ],
            [
                'country_code' => 'AU',
                'sub_region' => null,
                'title' => 'ทัวร์ออสเตรเลีย ซิดนีย์ โอเปร่าเฮาส์ บลูเมาเท่น',
                'duration_days' => 6,
                'duration_nights' => 4,
                'highlights' => 'โอเปร่าเฮาส์ สะพานฮาร์เบอร์ บลูเมาเท่น ดาร์ลิ่งฮาร์เบอร์ ชมโคอาล่า',
                'themes' => ['NATURE', 'CULTURE', 'FAMILY'],
                'suitable_for' => ['FAMILY', 'COUPLE'],
                'locations' => ['ซิดนีย์', 'บลูเมาเท่น'],
                'base_price' => 69900,
                'airline_code' => 'TG',
                'departure_airport' => 'BKK',
            ],
            [
                'country_code' => 'AE',
                'sub_region' => null,
                'title' => 'ทัวร์ดูไบ เบิร์จคาลิฟา ทะเลทราย อาบูดาบี',
                'duration_days' => 5,
                'duration_nights' => 3,
                'highlights' => 'เบิร์จคาลิฟา ตึกที่สูงที่สุดในโลก ขี่อูฐทะเลทราย ดูไบมอลล์ ปาล์มจูเมร่า อาบูดาบี',
                'themes' => ['SHOPPING', 'ADVENTURE', 'PREMIUM'],
                'suitable_for' => ['COUPLE', 'FAMILY'],
                'locations' => ['ดูไบ', 'อาบูดาบี'],
                'base_price' => 45900,
                'airline_code' => 'EK',
                'departure_airport' => 'BKK',
            ],
        ];

        $this->command->info('Creating 10 tours with 10+ periods each...');

        foreach ($toursData as $index => $tourData) {
            // Get country
            $country = Country::where('iso2', $tourData['country_code'])->first();
            if (!$country) {
                $this->command->warn("Country {$tourData['country_code']} not found, skipping...");
                continue;
            }

            // Get or use default airline
            $airline = Transport::where('code', $tourData['airline_code'])->first() ?? $defaultAirline;

            // Create tour
            $tourCode = strtoupper(substr($tourData['country_code'], 0, 2)) . '-' . date('ymd') . '-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);
            
            // Create slug from tour code (Thai text doesn't work with Str::slug)
            $slug = strtolower($tourCode);
            
            $tour = Tour::create([
                'wholesaler_id' => $wholesaler->id,
                'external_id' => 'EXT-' . $tourCode,
                'tour_code' => $tourCode,
                'title' => $tourData['title'],
                'primary_country_id' => $country->id,
                'region' => $country->region,
                'sub_region' => $tourData['sub_region'] ?? null,
                'duration_days' => $tourData['duration_days'],
                'duration_nights' => $tourData['duration_nights'],
                'highlights' => $tourData['highlights'],
                'inclusions' => "✓ ตั๋วเครื่องบินไป-กลับ ชั้นประหยัด\n✓ ที่พักโรงแรมตามรายการ\n✓ อาหารตามรายการ\n✓ รถนำเที่ยวตลอดการเดินทาง\n✓ ค่าเข้าชมสถานที่ตามรายการ\n✓ มัคคุเทศก์นำเที่ยว\n✓ ประกันอุบัติเหตุการเดินทาง",
                'exclusions' => "✗ ค่าทิปมัคคุเทศก์และคนขับรถ\n✗ ค่าใช้จ่ายส่วนตัว\n✗ ค่าน้ำหนักกระเป๋าเกิน\n✗ ค่าวีซ่า (ถ้ามี)",
                'conditions' => "กรุณาชำระมัดจำ 10,000 บาท ภายใน 3 วันหลังจองทัวร์\nส่วนที่เหลือชำระก่อนเดินทาง 14 วัน",
                'slug' => $slug,
                'meta_title' => $tourData['title'] . ' | NextTrip',
                'meta_description' => $tourData['highlights'],
                'keywords' => array_merge(['ทัวร์' . $country->name_th], $tourData['locations']),
                'cover_image_url' => 'https://picsum.photos/seed/' . $tourCode . '/1200/800',
                'cover_image_alt' => $tourData['title'],
                'themes' => $tourData['themes'],
                'suitable_for' => $tourData['suitable_for'],
                'departure_airports' => [$tourData['departure_airport']],
                'badge' => $index < 3 ? 'HOT' : ($index < 6 ? 'NEW' : null),
                'popularity_score' => rand(100, 1000),
                'sort_order' => $index,
                'status' => 'active',
            ]);

            // Create locations
            foreach ($tourData['locations'] as $i => $location) {
                TourLocation::create([
                    'tour_id' => $tour->id,
                    'name' => $location,
                    'name_en' => $location,
                    'sort_order' => $i,
                ]);
            }

            // Create gallery (5 images)
            for ($i = 1; $i <= 5; $i++) {
                TourGallery::create([
                    'tour_id' => $tour->id,
                    'url' => "https://picsum.photos/seed/{$tourCode}-{$i}/1200/800",
                    'thumbnail_url' => "https://picsum.photos/seed/{$tourCode}-{$i}/400/300",
                    'alt' => "{$tourData['title']} - รูปที่ {$i}",
                    'sort_order' => $i,
                ]);
            }

            // Create transports (outbound + inbound)
            if ($airline) {
                TourTransport::create([
                    'tour_id' => $tour->id,
                    'transport_id' => $airline->id,
                    'transport_code' => $airline->code,
                    'transport_name' => $airline->name,
                    'flight_no' => $airline->code . rand(100, 999),
                    'route_from' => $tourData['departure_airport'],
                    'route_to' => $tourData['country_code'] == 'JP' ? 'NRT' : ($tourData['country_code'] == 'KR' ? 'ICN' : 'XXX'),
                    'depart_time' => sprintf('%02d:%02d:00', rand(6, 22), rand(0, 5) * 10),
                    'arrive_time' => sprintf('%02d:%02d:00', rand(6, 22), rand(0, 5) * 10),
                    'transport_type' => 'outbound',
                    'day_no' => 1,
                    'sort_order' => 1,
                ]);

                TourTransport::create([
                    'tour_id' => $tour->id,
                    'transport_id' => $airline->id,
                    'transport_code' => $airline->code,
                    'transport_name' => $airline->name,
                    'flight_no' => $airline->code . rand(100, 999),
                    'route_from' => $tourData['country_code'] == 'JP' ? 'NRT' : ($tourData['country_code'] == 'KR' ? 'ICN' : 'XXX'),
                    'route_to' => $tourData['departure_airport'],
                    'depart_time' => sprintf('%02d:%02d:00', rand(6, 22), rand(0, 5) * 10),
                    'arrive_time' => sprintf('%02d:%02d:00', rand(6, 22), rand(0, 5) * 10),
                    'transport_type' => 'inbound',
                    'day_no' => $tourData['duration_days'],
                    'sort_order' => 2,
                ]);
            }

            // Create itineraries
            for ($day = 1; $day <= $tourData['duration_days']; $day++) {
                $title = match($day) {
                    1 => 'กรุงเทพฯ - ' . $tourData['locations'][0],
                    $tourData['duration_days'] => $tourData['locations'][array_key_last($tourData['locations'])] . ' - กรุงเทพฯ',
                    default => $tourData['locations'][min($day - 1, count($tourData['locations']) - 1)],
                };

                TourItinerary::create([
                    'tour_id' => $tour->id,
                    'day_no' => $day,
                    'title' => "วันที่ {$day}: {$title}",
                    'description' => "รายละเอียดโปรแกรมวันที่ {$day} ของ{$tourData['title']}. พาท่านเที่ยวชมสถานที่สำคัญ สัมผัสบรรยากาศและวัฒนธรรมท้องถิ่น",
                    'hotel_name' => $day < $tourData['duration_days'] ? 'โรงแรม ' . $tourData['locations'][min($day - 1, count($tourData['locations']) - 1)] . ' หรือเทียบเท่า' : null,
                    'hotel_star' => $day < $tourData['duration_days'] ? rand(3, 5) : null,
                    'meal_breakfast' => $day > 1,
                    'meal_lunch' => true,
                    'meal_dinner' => $day < $tourData['duration_days'],
                ]);
            }

            // Create 12 periods (12 months)
            $minPrice = $tourData['base_price'];
            $maxPrice = $tourData['base_price'];
            $totalSeats = 0;
            $nextDeparture = null;

            for ($month = 1; $month <= 12; $month++) {
                $startDate = Carbon::create(2026, $month, rand(10, 20));
                
                // Skip if in the past
                if ($startDate->isPast()) {
                    $startDate = Carbon::now()->addDays(rand(30, 60));
                }
                
                $endDate = $startDate->copy()->addDays($tourData['duration_days'] - 1);
                
                // Price varies by season
                $seasonMultiplier = in_array($month, [4, 10, 12]) ? 1.2 : (in_array($month, [6, 7, 8]) ? 1.1 : 1.0);
                $periodPrice = round($tourData['base_price'] * $seasonMultiplier, -2);
                
                $capacity = rand(20, 40);
                $booked = rand(0, $capacity - 5);
                $available = $capacity - $booked;

                $period = Period::create([
                    'tour_id' => $tour->id,
                    'external_id' => 'PER-' . $tourCode . '-' . str_pad($month, 2, '0', STR_PAD_LEFT),
                    'period_code' => $tourCode . '-' . $startDate->format('ymd'),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'capacity' => $capacity,
                    'booked' => $booked,
                    'available' => $available,
                    'status' => $available > 0 ? 'open' : 'sold_out',
                ]);

                // Create offer
                $offer = Offer::create([
                    'period_id' => $period->id,
                    'currency' => 'THB',
                    'price_adult' => $periodPrice,
                    'price_child' => round($periodPrice * 0.8, -2),
                    'price_child_nobed' => round($periodPrice * 0.7, -2),
                    'price_infant' => round($periodPrice * 0.3, -2),
                    'price_single' => round($periodPrice * 0.3, -2),
                    'deposit' => min(10000, round($periodPrice * 0.3, -2)),
                    'commission_agent' => round($periodPrice * 0.05, -2),
                    'commission_sale' => round($periodPrice * 0.02, -2),
                    'cancellation_policy' => "ยกเลิกก่อน 30 วัน: คืนเงินเต็มจำนวน\nยกเลิกก่อน 15 วัน: หักค่ามัดจำ\nยกเลิกก่อน 7 วัน: หัก 50%\nยกเลิกน้อยกว่า 7 วัน: ไม่คืนเงิน",
                    'notes' => 'ราคาอาจมีการเปลี่ยนแปลงโดยไม่ต้องแจ้งให้ทราบล่วงหน้า',
                    'ttl_minutes' => 10,
                ]);

                // Add promotion for some periods
                if ($month % 3 == 0) {
                    OfferPromotion::create([
                        'offer_id' => $offer->id,
                        'promo_code' => 'EARLY' . $month,
                        'name' => 'จองล่วงหน้าลด 1,000 บาท',
                        'type' => 'discount_amount',
                        'value' => 1000,
                        'apply_to' => 'per_pax',
                        'start_at' => now(),
                        'end_at' => $startDate->copy()->subDays(30),
                        'conditions' => ['min_pax' => 2, 'booking_before_days' => 30],
                        'is_active' => true,
                    ]);
                }

                // Track min/max price
                if ($periodPrice < $minPrice) $minPrice = $periodPrice;
                if ($periodPrice > $maxPrice) $maxPrice = $periodPrice;
                $totalSeats += $available;
                if ($nextDeparture === null && $startDate->isFuture() && $available > 0) {
                    $nextDeparture = $startDate;
                }
            }

            // Update tour aggregates
            $tour->update([
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'next_departure_date' => $nextDeparture,
                'total_departures' => 12,
                'available_seats' => $totalSeats,
                'has_promotion' => true,
            ]);

            $this->command->info("Created tour: {$tour->title} with 12 periods");
        }

        $this->command->info('Tour seeding completed!');
        $this->command->info('Total tours: ' . Tour::count());
        $this->command->info('Total periods: ' . Period::count());
        $this->command->info('Total offers: ' . Offer::count());
    }
}
