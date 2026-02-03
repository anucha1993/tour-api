<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\WholesalerSyncController;
use App\Models\Wholesaler;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TestSyncApi extends Command
{
    protected $signature = 'test:sync-api {wholesaler_id}';
    protected $description = 'Test the sync API with sample data';

    public function handle()
    {
        $wholesalerId = $this->argument('wholesaler_id');
        $wholesaler = Wholesaler::find($wholesalerId);

        if (!$wholesaler) {
            $this->error("Wholesaler not found: {$wholesalerId}");
            return 1;
        }

        $this->info("Testing sync API for: {$wholesaler->name}");

        // Sample data from frontend preview
        $data = [
            'tour' => [
                'external_id' => '2418',
                'wholesaler_tour_code' => 'ZGCAN-2601AQ',
                'title' => 'ฮ่องกง กวางเจา จู่ไห่ แชกงหมิว โรงละครหอยไข่มุก',
                'duration_days' => '4',
                'duration_nights' => '0',
                'primary_country_id' => 'CH',
                'transport_id' => 'AQ',
                'hotel_star' => '3'
            ],
            'departure' => [
                [
                    'external_id' => 2418,
                    'departure_date' => '2026-03-13',
                    'return_date' => '2026-03-16',
                    'capacity' => 20,
                    'status' => 'open',
                    'currency' => 'THB',
                    'price_adult' => 14990,
                    'price_child' => 14990,
                    'price_child_nobed' => 14990,
                    'price_single' => 4000
                ],
                [
                    'external_id' => 2418,
                    'departure_date' => '2026-03-20',
                    'return_date' => '2026-03-23',
                    'capacity' => 20,
                    'status' => 'open',
                    'currency' => 'THB',
                    'price_adult' => 14990,
                    'price_child' => 14990,
                    'price_child_nobed' => 14990,
                    'price_single' => 4000
                ]
            ],
            'content' => [
                'description' => 'ฮ่องกง กวางเจา จู่ไห่ แชกงหมิว โรงละครหอยไข่มุก-ขอพรเทพเจ้ากงหมิว ช้อปปิ้งย่านจิมซาจุ่ย เช็คอิน จูไห่ฟิชเชอร์เกิร์ล สักการะพระใหญ่วัดต้าฝอ เช็คอินถนนคนเดินเป่ยจิงลู่',
                'highlights' => 'ขอพรเทพเจ้ากงหมิว ช้อปปิ้งย่านจิมซาจุ่ย เช็คอิน จูไห่ฟิชเชอร์เกิร์ล สักการะพระใหญ่วัดต้าฝอ เช็คอินถนนคนเดินเป่ยจิงลู่',
                'shopping_highlights' => 'ขอพรเทพเจ้ากงหมิว ช้อปปิ้งย่านจิมซาจุ่ย'
            ],
            'media' => [
                'cover_image_url' => 'https://www.zegotravel.com/images/image_programtour/2418_20260105164105.jpg',
                'pdf_url' => 'https://www.zegotravel.com/uploadfile/p_d_f/programtour/2418_20260109101023.pdf'
            ],
            'itinerary' => [
                [
                    'external_id' => 9597,
                    'day_number' => 1,
                    'title' => 'สนามบินดอนเมือง (ประเทศไทย) - สนามบินกวางเจา (ประเทศจีน)',
                    'description' => 'สนามบินดอนเมือง (ประเทศไทย) - สนามบินกวางเจา (ประเทศจีน)',
                    'places' => 'สนามบินดอนเมือง (ประเทศไทย) - สนามบินกวางเจา (ประเทศจีน)',
                    'has_breakfast' => false,
                    'has_lunch' => false,
                    'has_dinner' => false,
                    'accommodation' => 'ZHUHAI AREA ระดับ 3 ดาว',
                    'hotel_star' => '3'
                ],
                [
                    'external_id' => 9598,
                    'day_number' => 2,
                    'title' => 'เมืองกวางเจา - ฮ่องกง - วัดแชกงหมิว',
                    'description' => 'เมืองกวางเจา - ฮ่องกง - วัดแชกงหมิว - อเวนิว ออฟ สตาร์',
                    'places' => 'เมืองกวางเจา - ฮ่องกง - วัดแชกงหมิว',
                    'has_breakfast' => true,
                    'has_lunch' => true,
                    'has_dinner' => false,
                    'accommodation' => 'STAY INN XING CHENG HOTEL, ZHUHAI ระดับ 4 ดาว',
                    'hotel_star' => '4'
                ],
                [
                    'external_id' => 9599,
                    'day_number' => 3,
                    'title' => 'จูไห่ฟิชเชอร์เกิร์ล - โรงละครหอยไข่มุก',
                    'description' => 'จูไห่ฟิชเชอร์เกิร์ล - โรงละครหอยไข่มุก - ร้านบัวหิมะ - ตลาดกงเป่ย',
                    'places' => 'จูไห่ฟิชเชอร์เกิร์ล - โรงละครหอยไข่มุก',
                    'has_breakfast' => true,
                    'has_lunch' => true,
                    'has_dinner' => false,
                    'accommodation' => 'STAY INN XING CHENG HOTEL, ZHUHAI ระดับ 4 ดาว',
                    'hotel_star' => '4'
                ],
                [
                    'external_id' => 9600,
                    'day_number' => 4,
                    'title' => 'วัดต้าฝอ - ถนนคนเดินเป่ยจิงลู่ - สนามบินกวางเจา',
                    'description' => 'ร้านหยก - จัตุรัสฮัวเฉิง - กวางเจาทาวเวอร์ - วัดต้าฝอ - ถนนคนเดินเป่ยจิงลู่ - สนามบินกวางเจา',
                    'places' => 'วัดต้าฝอ - ถนนคนเดินเป่ยจิงลู่',
                    'has_breakfast' => true,
                    'has_lunch' => true,
                    'has_dinner' => false,
                    'accommodation' => '',
                    'hotel_star' => ''
                ]
            ],
            'seo' => [
                'meta_title' => 'ฮ่องกง กวางเจา จู่ไห่ แชกงหมิว โรงละครหอยไข่มุก',
                'meta_description' => 'ขอพรเทพเจ้ากงหมิว ช้อปปิ้งย่านจิมซาจุ่ย เช็คอิน จูไห่ฟิชเชอร์เกิร์ล สักการะพระใหญ่วัดต้าฝอ เช็คอินถนนคนเดินเป่ยจิงลู่',
                'keywords' => 'ฮ่องกง กวางเจา จู่ไห่ แชกงหมิว โรงละครหอยไข่มุก',
                'hashtags' => 'ขอพรเทพเจ้ากงหมิว ช้อปปิ้งย่านจิมซาจุ่ย'
            ]
        ];

        $this->info("\n📦 Input Data:");
        $this->line("  Tour: {$data['tour']['title']}");
        $this->line("  External ID: {$data['tour']['external_id']}");
        $this->line("  Country: {$data['tour']['primary_country_id']} (will resolve to ID)");
        $this->line("  Transport: {$data['tour']['transport_id']} (will resolve to ID)");
        $this->line("  Departures: " . count($data['departure']));
        $this->line("  Itineraries: " . count($data['itinerary']));

        // Create request and call controller
        $request = Request::create('/api/wholesalers/' . $wholesaler->id . '/sync/tour', 'POST', $data);
        $controller = new WholesalerSyncController();

        $this->info("\n🔄 Syncing...");
        $response = $controller->syncTour($request, $wholesaler);
        $result = json_decode($response->getContent(), true);

        if ($result['success']) {
            $this->info("\n✅ Sync Successful!");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Tour ID', $result['data']['tour_id']],
                    ['Tour Code', $result['data']['tour_code']],
                    ['External ID', $result['data']['external_id']],
                    ['Is New', $result['data']['is_new'] ? 'Yes' : 'No'],
                    ['Periods Created', $result['data']['periods']['created']],
                    ['Periods Updated', $result['data']['periods']['updated']],
                    ['Itineraries Created', $result['data']['itineraries']['created']],
                    ['Itineraries Updated', $result['data']['itineraries']['updated']],
                ]
            );

            // Verify saved data
            $this->info("\n📊 Verifying saved data:");
            $tour = \App\Models\Tour::with(['periods.offer', 'itineraries'])->find($result['data']['tour_id']);
            
            $this->table(
                ['Field', 'Value'],
                [
                    ['title', mb_substr($tour->title, 0, 50) . '...'],
                    ['tour_code', $tour->tour_code],
                    ['wholesaler_tour_code', $tour->wholesaler_tour_code],
                    ['duration_days', $tour->duration_days],
                    ['duration_nights', $tour->duration_nights],
                    ['primary_country_id', $tour->primary_country_id],
                    ['transport_id', $tour->transport_id],
                    ['hotel_star', $tour->hotel_star],
                    ['cover_image_url', $tour->cover_image_url ? 'SET ✓' : 'NULL ✗'],
                    ['pdf_url', $tour->pdf_url ? 'SET ✓' : 'NULL ✗'],
                    ['description', $tour->description ? 'SET ✓' : 'NULL ✗'],
                    ['highlights', $tour->highlights ? 'SET ✓' : 'NULL ✗'],
                ]
            );

            $this->info("\n📅 Periods:");
            foreach ($tour->periods as $period) {
                $this->line("  - {$period->start_date} to {$period->end_date} | capacity: {$period->capacity} | available: {$period->available}");
                if ($period->offer) {
                    $this->line("    💰 Price: {$period->offer->price_adult} THB");
                }
            }

            $this->info("\n📝 Itineraries:");
            foreach ($tour->itineraries as $itin) {
                $meals = [];
                if ($itin->has_breakfast) $meals[] = 'B';
                if ($itin->has_lunch) $meals[] = 'L';
                if ($itin->has_dinner) $meals[] = 'D';
                $mealsStr = $meals ? implode('/', $meals) : '-';
                $this->line("  Day {$itin->day_number}: " . mb_substr($itin->title, 0, 40) . "... [{$mealsStr}]");
            }

        } else {
            $this->error("\n❌ Sync Failed!");
            $this->error($result['message']);
        }

        return 0;
    }
}
