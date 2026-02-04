<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CitiesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Starting cities seeder...');
        
        // ===== ประเทศไทย (country_id: 8) =====
        $this->seedCities(8, $this->getThailandCities());
        
        // ===== ประเทศจีน (country_id: 3) =====
        $this->seedCities(3, $this->getChinaCities());
        
        // ===== ประเทศญี่ปุ่น (country_id: 1) =====
        $this->seedCities(1, $this->getJapanCities());
        
        // ===== ประเทศเกาหลีใต้ (country_id: 2) =====
        $this->seedCities(2, $this->getKoreaCities());
        
        // ===== ไต้หวัน (country_id: 4) =====
        $this->seedCities(4, $this->getTaiwanCities());
        
        // ===== เวียดนาม (country_id: 9) =====
        $this->seedCities(9, $this->getVietnamCities());
        
        // ===== สิงคโปร์ (country_id: 10) =====
        $this->seedCities(10, $this->getSingaporeCities());
        
        // ===== มาเลเซีย (country_id: 11) =====
        $this->seedCities(11, $this->getMalaysiaCities());
        
        // ===== อินโดนีเซีย (country_id: 12) =====
        $this->seedCities(12, $this->getIndonesiaCities());
        
        // ===== ฟิลิปปินส์ (country_id: 13) =====
        $this->seedCities(13, $this->getPhilippinesCities());
        
        // ===== อินเดีย (country_id: 19) =====
        $this->seedCities(19, $this->getIndiaCities());
        
        // ===== ฮ่องกง (country_id: 5) =====
        $this->seedCities(5, $this->getHongKongCities());
        
        // ===== มาเก๊า (country_id: 6) =====
        $this->seedCities(6, $this->getMacauCities());
        
        $this->command->info('Cities seeder completed!');
    }
    
    private function seedCities(int $countryId, array $cities): void
    {
        $country = DB::table('countries')->where('id', $countryId)->first();
        if (!$country) {
            $this->command->warn("Country ID {$countryId} not found, skipping...");
            return;
        }
        
        $this->command->info("Seeding cities for {$country->name_en} (ID: {$countryId})...");
        
        // ดึงชื่อเมืองที่มีอยู่แล้วในประเทศนี้
        $existingCities = DB::table('cities')
            ->where('country_id', $countryId)
            ->pluck('name_en')
            ->map(fn($name) => strtolower(trim($name)))
            ->toArray();
        
        // ดึง slug ที่มีอยู่แล้วทั้งหมด (global)
        $existingSlugs = DB::table('cities')
            ->pluck('slug')
            ->toArray();
        
        $inserted = 0;
        $skipped = 0;
        
        foreach ($cities as $city) {
            $nameEnLower = strtolower(trim($city['name_en']));
            
            // Check if already exists in this country
            if (in_array($nameEnLower, $existingCities)) {
                // Update name_th if missing
                DB::table('cities')
                    ->where('country_id', $countryId)
                    ->whereRaw('LOWER(name_en) = ?', [$nameEnLower])
                    ->whereNull('name_th')
                    ->update(['name_th' => $city['name_th']]);
                $skipped++;
                continue;
            }
            
            // สร้าง unique slug (ถ้าซ้ำให้เติม country code)
            $baseSlug = Str::slug($city['name_en']);
            $slug = $baseSlug;
            
            if (in_array($slug, $existingSlugs)) {
                // เติม country code เพื่อให้ unique
                $slug = $baseSlug . '-' . strtolower($country->iso2 ?? $countryId);
            }
            
            // ถ้ายังซ้ำอีก ให้เติมเลข
            $counter = 1;
            while (in_array($slug, $existingSlugs)) {
                $slug = $baseSlug . '-' . strtolower($country->iso2 ?? $countryId) . '-' . $counter;
                $counter++;
            }
            
            try {
                DB::table('cities')->insert([
                    'name_en' => $city['name_en'],
                    'name_th' => $city['name_th'],
                    'slug' => $slug,
                    'country_id' => $countryId,
                    'is_popular' => $city['is_popular'] ?? false,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                $existingCities[] = $nameEnLower;
                $existingSlugs[] = $slug;
                $inserted++;
            } catch (\Exception $e) {
                $this->command->warn("  ! Skipped {$city['name_en']}: " . $e->getMessage());
                $skipped++;
            }
        }
        
        $this->command->info("  → Inserted: {$inserted}, Skipped/Updated: {$skipped}");
    }
    
    // ===== จังหวัดในประเทศไทย (77 จังหวัด) =====
    private function getThailandCities(): array
    {
        return [
            // ภาคเหนือ
            ['name_en' => 'Chiang Mai', 'name_th' => 'เชียงใหม่', 'is_popular' => true],
            ['name_en' => 'Chiang Rai', 'name_th' => 'เชียงราย', 'is_popular' => true],
            ['name_en' => 'Lampang', 'name_th' => 'ลำปาง'],
            ['name_en' => 'Lamphun', 'name_th' => 'ลำพูน'],
            ['name_en' => 'Mae Hong Son', 'name_th' => 'แม่ฮ่องสอน'],
            ['name_en' => 'Nan', 'name_th' => 'น่าน'],
            ['name_en' => 'Phayao', 'name_th' => 'พะเยา'],
            ['name_en' => 'Phrae', 'name_th' => 'แพร่'],
            ['name_en' => 'Uttaradit', 'name_th' => 'อุตรดิตถ์'],
            
            // ภาคกลาง
            ['name_en' => 'Bangkok', 'name_th' => 'กรุงเทพมหานคร', 'is_popular' => true],
            ['name_en' => 'Nonthaburi', 'name_th' => 'นนทบุรี'],
            ['name_en' => 'Pathum Thani', 'name_th' => 'ปทุมธานี'],
            ['name_en' => 'Samut Prakan', 'name_th' => 'สมุทรปราการ'],
            ['name_en' => 'Samut Sakhon', 'name_th' => 'สมุทรสาคร'],
            ['name_en' => 'Samut Songkhram', 'name_th' => 'สมุทรสงคราม'],
            ['name_en' => 'Nakhon Pathom', 'name_th' => 'นครปฐม'],
            ['name_en' => 'Suphan Buri', 'name_th' => 'สุพรรณบุรี'],
            ['name_en' => 'Ayutthaya', 'name_th' => 'พระนครศรีอยุธยา', 'is_popular' => true],
            ['name_en' => 'Ang Thong', 'name_th' => 'อ่างทอง'],
            ['name_en' => 'Sing Buri', 'name_th' => 'สิงห์บุรี'],
            ['name_en' => 'Lopburi', 'name_th' => 'ลพบุรี'],
            ['name_en' => 'Saraburi', 'name_th' => 'สระบุรี'],
            ['name_en' => 'Chai Nat', 'name_th' => 'ชัยนาท'],
            ['name_en' => 'Nakhon Sawan', 'name_th' => 'นครสวรรค์'],
            ['name_en' => 'Uthai Thani', 'name_th' => 'อุทัยธานี'],
            ['name_en' => 'Kamphaeng Phet', 'name_th' => 'กำแพงเพชร'],
            ['name_en' => 'Phichit', 'name_th' => 'พิจิตร'],
            ['name_en' => 'Phitsanulok', 'name_th' => 'พิษณุโลก'],
            ['name_en' => 'Phetchabun', 'name_th' => 'เพชรบูรณ์'],
            ['name_en' => 'Sukhothai', 'name_th' => 'สุโขทัย'],
            ['name_en' => 'Tak', 'name_th' => 'ตาก'],
            
            // ภาคตะวันออกเฉียงเหนือ (อีสาน)
            ['name_en' => 'Nakhon Ratchasima', 'name_th' => 'นครราชสีมา', 'is_popular' => true],
            ['name_en' => 'Khon Kaen', 'name_th' => 'ขอนแก่น', 'is_popular' => true],
            ['name_en' => 'Udon Thani', 'name_th' => 'อุดรธานี', 'is_popular' => true],
            ['name_en' => 'Ubon Ratchathani', 'name_th' => 'อุบลราชธานี'],
            ['name_en' => 'Buri Ram', 'name_th' => 'บุรีรัมย์'],
            ['name_en' => 'Surin', 'name_th' => 'สุรินทร์'],
            ['name_en' => 'Si Sa Ket', 'name_th' => 'ศรีสะเกษ'],
            ['name_en' => 'Roi Et', 'name_th' => 'ร้อยเอ็ด'],
            ['name_en' => 'Kalasin', 'name_th' => 'กาฬสินธุ์'],
            ['name_en' => 'Maha Sarakham', 'name_th' => 'มหาสารคาม'],
            ['name_en' => 'Chaiyaphum', 'name_th' => 'ชัยภูมิ'],
            ['name_en' => 'Nong Khai', 'name_th' => 'หนองคาย'],
            ['name_en' => 'Nong Bua Lam Phu', 'name_th' => 'หนองบัวลำภู'],
            ['name_en' => 'Loei', 'name_th' => 'เลย'],
            ['name_en' => 'Sakon Nakhon', 'name_th' => 'สกลนคร'],
            ['name_en' => 'Nakhon Phanom', 'name_th' => 'นครพนม'],
            ['name_en' => 'Mukdahan', 'name_th' => 'มุกดาหาร'],
            ['name_en' => 'Yasothon', 'name_th' => 'ยโสธร'],
            ['name_en' => 'Amnat Charoen', 'name_th' => 'อำนาจเจริญ'],
            ['name_en' => 'Bueng Kan', 'name_th' => 'บึงกาฬ'],
            
            // ภาคตะวันออก
            ['name_en' => 'Chonburi', 'name_th' => 'ชลบุรี', 'is_popular' => true],
            ['name_en' => 'Pattaya', 'name_th' => 'พัทยา', 'is_popular' => true],
            ['name_en' => 'Rayong', 'name_th' => 'ระยอง'],
            ['name_en' => 'Chanthaburi', 'name_th' => 'จันทบุรี'],
            ['name_en' => 'Trat', 'name_th' => 'ตราด'],
            ['name_en' => 'Sa Kaeo', 'name_th' => 'สระแก้ว'],
            ['name_en' => 'Prachin Buri', 'name_th' => 'ปราจีนบุรี'],
            ['name_en' => 'Nakhon Nayok', 'name_th' => 'นครนายก'],
            ['name_en' => 'Chachoengsao', 'name_th' => 'ฉะเชิงเทรา'],
            
            // ภาคตะวันตก
            ['name_en' => 'Kanchanaburi', 'name_th' => 'กาญจนบุรี', 'is_popular' => true],
            ['name_en' => 'Ratchaburi', 'name_th' => 'ราชบุรี'],
            ['name_en' => 'Phetchaburi', 'name_th' => 'เพชรบุรี'],
            ['name_en' => 'Prachuap Khiri Khan', 'name_th' => 'ประจวบคีรีขันธ์'],
            ['name_en' => 'Hua Hin', 'name_th' => 'หัวหิน', 'is_popular' => true],
            
            // ภาคใต้
            ['name_en' => 'Phuket', 'name_th' => 'ภูเก็ต', 'is_popular' => true],
            ['name_en' => 'Krabi', 'name_th' => 'กระบี่', 'is_popular' => true],
            ['name_en' => 'Phang Nga', 'name_th' => 'พังงา'],
            ['name_en' => 'Surat Thani', 'name_th' => 'สุราษฎร์ธานี'],
            ['name_en' => 'Koh Samui', 'name_th' => 'เกาะสมุย', 'is_popular' => true],
            ['name_en' => 'Nakhon Si Thammarat', 'name_th' => 'นครศรีธรรมราช'],
            ['name_en' => 'Songkhla', 'name_th' => 'สงขลา'],
            ['name_en' => 'Hat Yai', 'name_th' => 'หาดใหญ่', 'is_popular' => true],
            ['name_en' => 'Pattani', 'name_th' => 'ปัตตานี'],
            ['name_en' => 'Yala', 'name_th' => 'ยะลา'],
            ['name_en' => 'Narathiwat', 'name_th' => 'นราธิวาส'],
            ['name_en' => 'Trang', 'name_th' => 'ตรัง'],
            ['name_en' => 'Phatthalung', 'name_th' => 'พัทลุง'],
            ['name_en' => 'Satun', 'name_th' => 'สตูล'],
            ['name_en' => 'Ranong', 'name_th' => 'ระนอง'],
            ['name_en' => 'Chumphon', 'name_th' => 'ชุมพร'],
        ];
    }
    
    // ===== เมืองในประเทศจีน =====
    private function getChinaCities(): array
    {
        return [
            // เมืองยอดนิยมสำหรับทัวร์
            ['name_en' => 'Beijing', 'name_th' => 'ปักกิ่ง', 'is_popular' => true],
            ['name_en' => 'Shanghai', 'name_th' => 'เซี่ยงไฮ้', 'is_popular' => true],
            ['name_en' => 'Guangzhou', 'name_th' => 'กว่างโจว', 'is_popular' => true],
            ['name_en' => 'Shenzhen', 'name_th' => 'เซินเจิ้น', 'is_popular' => true],
            ['name_en' => 'Chengdu', 'name_th' => 'เฉิงตู', 'is_popular' => true],
            ['name_en' => 'Chongqing', 'name_th' => 'ฉงชิ่ง', 'is_popular' => true],
            ['name_en' => 'Hangzhou', 'name_th' => 'หางโจว', 'is_popular' => true],
            ['name_en' => 'Xian', 'name_th' => 'ซีอาน', 'is_popular' => true],
            ['name_en' => 'Guilin', 'name_th' => 'กุ้ยหลิน', 'is_popular' => true],
            ['name_en' => 'Kunming', 'name_th' => 'คุนหมิง', 'is_popular' => true],
            ['name_en' => 'Zhangjiajie', 'name_th' => 'จางเจียเจี้ย', 'is_popular' => true],
            ['name_en' => 'Lijiang', 'name_th' => 'ลี่เจียง', 'is_popular' => true],
            ['name_en' => 'Suzhou', 'name_th' => 'ซูโจว'],
            ['name_en' => 'Nanjing', 'name_th' => 'นานกิง'],
            ['name_en' => 'Wuhan', 'name_th' => 'อู่ฮั่น'],
            ['name_en' => 'Tianjin', 'name_th' => 'เทียนจิน'],
            ['name_en' => 'Qingdao', 'name_th' => 'ชิงเต่า'],
            ['name_en' => 'Dalian', 'name_th' => 'ต้าเหลียน'],
            ['name_en' => 'Harbin', 'name_th' => 'ฮาร์บิน', 'is_popular' => true],
            ['name_en' => 'Shenyang', 'name_th' => 'เสิ่นหยาง'],
            ['name_en' => 'Xiamen', 'name_th' => 'เซียะเหมิน'],
            ['name_en' => 'Fuzhou', 'name_th' => 'ฝูโจว'],
            ['name_en' => 'Ningbo', 'name_th' => 'หนิงปอ'],
            ['name_en' => 'Wuxi', 'name_th' => 'อู๋ซี'],
            ['name_en' => 'Changsha', 'name_th' => 'ฉางชา'],
            ['name_en' => 'Nanning', 'name_th' => 'หนานหนิง'],
            ['name_en' => 'Guiyang', 'name_th' => 'กุ้ยหยาง'],
            ['name_en' => 'Lhasa', 'name_th' => 'ลาซา', 'is_popular' => true],
            ['name_en' => 'Urumqi', 'name_th' => 'อุรุมชี'],
            ['name_en' => 'Lanzhou', 'name_th' => 'หลานโจว'],
            ['name_en' => 'Xining', 'name_th' => 'ซีหนิง'],
            ['name_en' => 'Yinchuan', 'name_th' => 'หยินชวน'],
            ['name_en' => 'Hohhot', 'name_th' => 'ฮูฮอต'],
            ['name_en' => 'Jinan', 'name_th' => 'จี่หนาน'],
            ['name_en' => 'Taiyuan', 'name_th' => 'ไท่หยวน'],
            ['name_en' => 'Shijiazhuang', 'name_th' => 'สือเจียจวง'],
            ['name_en' => 'Zhengzhou', 'name_th' => 'เจิ้งโจว'],
            ['name_en' => 'Hefei', 'name_th' => 'เหอเฝย'],
            ['name_en' => 'Nanchang', 'name_th' => 'หนานชาง'],
            ['name_en' => 'Haikou', 'name_th' => 'ไหโข่ว'],
            ['name_en' => 'Sanya', 'name_th' => 'ซานย่า', 'is_popular' => true],
            ['name_en' => 'Dali', 'name_th' => 'ต้าหลี่'],
            ['name_en' => 'Shangri-La', 'name_th' => 'แชงกรีล่า'],
            ['name_en' => 'Huangshan', 'name_th' => 'หวงซาน'],
            ['name_en' => 'Luoyang', 'name_th' => 'ลั่วหยาง'],
            ['name_en' => 'Dunhuang', 'name_th' => 'ตุนหวง'],
            ['name_en' => 'Jiuzhaigou', 'name_th' => 'จิ่วไจ้โกว', 'is_popular' => true],
            ['name_en' => 'Leshan', 'name_th' => 'เล่อซาน'],
            ['name_en' => 'Emeishan', 'name_th' => 'เอ๋อเหมยซาน'],
            ['name_en' => 'Yangshuo', 'name_th' => 'หยางซั่ว'],
        ];
    }
    
    // ===== เมืองในประเทศญี่ปุ่น =====
    private function getJapanCities(): array
    {
        return [
            // Kanto
            ['name_en' => 'Tokyo', 'name_th' => 'โตเกียว', 'is_popular' => true],
            ['name_en' => 'Yokohama', 'name_th' => 'โยโกฮาม่า'],
            ['name_en' => 'Kawasaki', 'name_th' => 'คาวาซากิ'],
            ['name_en' => 'Chiba', 'name_th' => 'ชิบะ'],
            ['name_en' => 'Saitama', 'name_th' => 'ไซตามะ'],
            ['name_en' => 'Hakone', 'name_th' => 'ฮาโกเน่', 'is_popular' => true],
            ['name_en' => 'Nikko', 'name_th' => 'นิกโก้'],
            ['name_en' => 'Kamakura', 'name_th' => 'คามาคุระ'],
            
            // Kansai
            ['name_en' => 'Osaka', 'name_th' => 'โอซาก้า', 'is_popular' => true],
            ['name_en' => 'Kyoto', 'name_th' => 'เกียวโต', 'is_popular' => true],
            ['name_en' => 'Nara', 'name_th' => 'นารา', 'is_popular' => true],
            ['name_en' => 'Kobe', 'name_th' => 'โกเบ'],
            ['name_en' => 'Himeji', 'name_th' => 'ฮิเมจิ'],
            ['name_en' => 'Wakayama', 'name_th' => 'วากายาม่า'],
            
            // Chubu
            ['name_en' => 'Nagoya', 'name_th' => 'นาโกย่า', 'is_popular' => true],
            ['name_en' => 'Kanazawa', 'name_th' => 'คานาซาว่า'],
            ['name_en' => 'Takayama', 'name_th' => 'ทาคายาม่า', 'is_popular' => true],
            ['name_en' => 'Shirakawa-go', 'name_th' => 'ชิราคาวาโกะ', 'is_popular' => true],
            ['name_en' => 'Matsumoto', 'name_th' => 'มัตสึโมโตะ'],
            ['name_en' => 'Nagano', 'name_th' => 'นากาโน่'],
            ['name_en' => 'Niigata', 'name_th' => 'นีงาตะ'],
            ['name_en' => 'Shizuoka', 'name_th' => 'ชิซูโอกะ'],
            ['name_en' => 'Fujikawaguchiko', 'name_th' => 'ฟูจิคาวากูจิโกะ', 'is_popular' => true],
            
            // Hokkaido
            ['name_en' => 'Sapporo', 'name_th' => 'ซัปโปโร', 'is_popular' => true],
            ['name_en' => 'Otaru', 'name_th' => 'โอตารุ', 'is_popular' => true],
            ['name_en' => 'Hakodate', 'name_th' => 'ฮาโกดาเตะ'],
            ['name_en' => 'Furano', 'name_th' => 'ฟุราโน่', 'is_popular' => true],
            ['name_en' => 'Biei', 'name_th' => 'บิเอย์'],
            ['name_en' => 'Noboribetsu', 'name_th' => 'โนโบริเบ็ตสึ'],
            ['name_en' => 'Asahikawa', 'name_th' => 'อาซาฮิกาว่า'],
            ['name_en' => 'Niseko', 'name_th' => 'นิเซโกะ', 'is_popular' => true],
            ['name_en' => 'Kushiro', 'name_th' => 'คุชิโระ'],
            
            // Tohoku
            ['name_en' => 'Sendai', 'name_th' => 'เซนได'],
            ['name_en' => 'Aomori', 'name_th' => 'อาโอโมริ'],
            ['name_en' => 'Akita', 'name_th' => 'อาคิตะ'],
            ['name_en' => 'Yamagata', 'name_th' => 'ยามากาตะ'],
            ['name_en' => 'Fukushima', 'name_th' => 'ฟุกุชิมะ'],
            ['name_en' => 'Morioka', 'name_th' => 'โมริโอกะ'],
            
            // Chugoku
            ['name_en' => 'Hiroshima', 'name_th' => 'ฮิโรชิม่า', 'is_popular' => true],
            ['name_en' => 'Miyajima', 'name_th' => 'มิยาจิม่า', 'is_popular' => true],
            ['name_en' => 'Okayama', 'name_th' => 'โอคายาม่า'],
            ['name_en' => 'Kurashiki', 'name_th' => 'คุราชิกิ'],
            ['name_en' => 'Tottori', 'name_th' => 'ทตโตริ'],
            ['name_en' => 'Matsue', 'name_th' => 'มัตสึเอะ'],
            ['name_en' => 'Yamaguchi', 'name_th' => 'ยามากูจิ'],
            
            // Shikoku
            ['name_en' => 'Takamatsu', 'name_th' => 'ทาคามัตสึ'],
            ['name_en' => 'Matsuyama', 'name_th' => 'มัตสึยามะ'],
            ['name_en' => 'Tokushima', 'name_th' => 'โทคุชิมะ'],
            ['name_en' => 'Kochi', 'name_th' => 'โคจิ'],
            
            // Kyushu
            ['name_en' => 'Fukuoka', 'name_th' => 'ฟุกุโอกะ', 'is_popular' => true],
            ['name_en' => 'Nagasaki', 'name_th' => 'นางาซากิ'],
            ['name_en' => 'Kumamoto', 'name_th' => 'คุมาโมโตะ'],
            ['name_en' => 'Kagoshima', 'name_th' => 'คาโกชิม่า'],
            ['name_en' => 'Oita', 'name_th' => 'โออิตะ'],
            ['name_en' => 'Beppu', 'name_th' => 'เบปปุ', 'is_popular' => true],
            ['name_en' => 'Miyazaki', 'name_th' => 'มิยาซากิ'],
            ['name_en' => 'Saga', 'name_th' => 'ซากะ'],
            ['name_en' => 'Yufuin', 'name_th' => 'ยูฟุอิน', 'is_popular' => true],
            
            // Okinawa
            ['name_en' => 'Okinawa', 'name_th' => 'โอกินาว่า', 'is_popular' => true],
            ['name_en' => 'Naha', 'name_th' => 'นาฮะ'],
            ['name_en' => 'Ishigaki', 'name_th' => 'อิชิงากิ'],
        ];
    }
    
    // ===== เมืองในประเทศเกาหลีใต้ =====
    private function getKoreaCities(): array
    {
        return [
            ['name_en' => 'Seoul', 'name_th' => 'โซล', 'is_popular' => true],
            ['name_en' => 'Busan', 'name_th' => 'ปูซาน', 'is_popular' => true],
            ['name_en' => 'Incheon', 'name_th' => 'อินชอน'],
            ['name_en' => 'Daegu', 'name_th' => 'แทกู'],
            ['name_en' => 'Daejeon', 'name_th' => 'แทจอน'],
            ['name_en' => 'Gwangju', 'name_th' => 'กวางจู'],
            ['name_en' => 'Ulsan', 'name_th' => 'อุลซาน'],
            ['name_en' => 'Suwon', 'name_th' => 'ซูวอน'],
            ['name_en' => 'Jeju', 'name_th' => 'เชจู', 'is_popular' => true],
            ['name_en' => 'Gyeongju', 'name_th' => 'คยองจู', 'is_popular' => true],
            ['name_en' => 'Jeonju', 'name_th' => 'จอนจู'],
            ['name_en' => 'Sokcho', 'name_th' => 'ซกโช'],
            ['name_en' => 'Gangneung', 'name_th' => 'คังนึง'],
            ['name_en' => 'Andong', 'name_th' => 'อันดง'],
            ['name_en' => 'Chuncheon', 'name_th' => 'ชุนชอน'],
            ['name_en' => 'Yeosu', 'name_th' => 'ยอซู'],
            ['name_en' => 'Tongyeong', 'name_th' => 'ทงยอง'],
            ['name_en' => 'Pohang', 'name_th' => 'โพฮัง'],
            ['name_en' => 'Pyeongchang', 'name_th' => 'พยองชาง'],
            ['name_en' => 'Nami Island', 'name_th' => 'เกาะนามิ', 'is_popular' => true],
            ['name_en' => 'Everland', 'name_th' => 'เอเวอร์แลนด์'],
            ['name_en' => 'DMZ', 'name_th' => 'เขตปลอดทหาร DMZ'],
        ];
    }
    
    // ===== เมืองในไต้หวัน =====
    private function getTaiwanCities(): array
    {
        return [
            ['name_en' => 'Taipei', 'name_th' => 'ไทเป', 'is_popular' => true],
            ['name_en' => 'New Taipei', 'name_th' => 'นิวไทเป'],
            ['name_en' => 'Taichung', 'name_th' => 'ไถจง', 'is_popular' => true],
            ['name_en' => 'Tainan', 'name_th' => 'ไถหนาน'],
            ['name_en' => 'Kaohsiung', 'name_th' => 'เกาสง', 'is_popular' => true],
            ['name_en' => 'Hualien', 'name_th' => 'ฮัวเหลียน', 'is_popular' => true],
            ['name_en' => 'Taitung', 'name_th' => 'ไถตง'],
            ['name_en' => 'Keelung', 'name_th' => 'จีหลง'],
            ['name_en' => 'Hsinchu', 'name_th' => 'ซินจู๋'],
            ['name_en' => 'Chiayi', 'name_th' => 'เจียอี้'],
            ['name_en' => 'Yilan', 'name_th' => 'อี๋หลาน'],
            ['name_en' => 'Nantou', 'name_th' => 'หนานโถว'],
            ['name_en' => 'Sun Moon Lake', 'name_th' => 'ทะเลสาบสุริยันจันทรา', 'is_popular' => true],
            ['name_en' => 'Alishan', 'name_th' => 'อาลีซาน', 'is_popular' => true],
            ['name_en' => 'Jiufen', 'name_th' => 'จิ่วเฟิ่น', 'is_popular' => true],
            ['name_en' => 'Shifen', 'name_th' => 'สือเฟิน'],
            ['name_en' => 'Taroko', 'name_th' => 'ทาโรโกะ', 'is_popular' => true],
            ['name_en' => 'Kenting', 'name_th' => 'เขิ่นติง'],
            ['name_en' => 'Penghu', 'name_th' => 'เผิงหู'],
            ['name_en' => 'Green Island', 'name_th' => 'กรีนไอส์แลนด์'],
            ['name_en' => 'Orchid Island', 'name_th' => 'เกาะกล้วยไม้'],
            ['name_en' => 'Changhua', 'name_th' => 'จางฮั่ว'],
            ['name_en' => 'Miaoli', 'name_th' => 'เหมียวลี่'],
            ['name_en' => 'Pingtung', 'name_th' => 'ผิงตง'],
        ];
    }
    
    // ===== เมืองในเวียดนาม =====
    private function getVietnamCities(): array
    {
        return [
            ['name_en' => 'Hanoi', 'name_th' => 'ฮานอย', 'is_popular' => true],
            ['name_en' => 'Ho Chi Minh City', 'name_th' => 'โฮจิมินห์', 'is_popular' => true],
            ['name_en' => 'Da Nang', 'name_th' => 'ดานัง', 'is_popular' => true],
            ['name_en' => 'Hoi An', 'name_th' => 'ฮอยอัน', 'is_popular' => true],
            ['name_en' => 'Ha Long Bay', 'name_th' => 'ฮาลองเบย์', 'is_popular' => true],
            ['name_en' => 'Nha Trang', 'name_th' => 'ญาจาง', 'is_popular' => true],
            ['name_en' => 'Phu Quoc', 'name_th' => 'ฟูก๊วก', 'is_popular' => true],
            ['name_en' => 'Hue', 'name_th' => 'เว้', 'is_popular' => true],
            ['name_en' => 'Sapa', 'name_th' => 'ซาปา', 'is_popular' => true],
            ['name_en' => 'Da Lat', 'name_th' => 'ดาลัด', 'is_popular' => true],
            ['name_en' => 'Mui Ne', 'name_th' => 'มุยเน่'],
            ['name_en' => 'Can Tho', 'name_th' => 'เกิ่นเทอ'],
            ['name_en' => 'Ninh Binh', 'name_th' => 'นิญบิ่ญ'],
            ['name_en' => 'Quy Nhon', 'name_th' => 'กวีเญิน'],
            ['name_en' => 'Vung Tau', 'name_th' => 'หวุงเต่า'],
            ['name_en' => 'Hai Phong', 'name_th' => 'ไฮฟอง'],
            ['name_en' => 'Buon Ma Thuot', 'name_th' => 'บวนมาทวด'],
            ['name_en' => 'Pleiku', 'name_th' => 'เปลยกู'],
            ['name_en' => 'Kon Tum', 'name_th' => 'กอนตุม'],
            ['name_en' => 'Cao Bang', 'name_th' => 'กาวบั่ง'],
            ['name_en' => 'Ha Giang', 'name_th' => 'ฮาซาง'],
            ['name_en' => 'Phan Thiet', 'name_th' => 'ฟานเทียต'],
            ['name_en' => 'My Tho', 'name_th' => 'หมีทอ'],
            ['name_en' => 'Ben Tre', 'name_th' => 'เบ๊นแจ'],
        ];
    }
    
    // ===== เมืองในสิงคโปร์ =====
    private function getSingaporeCities(): array
    {
        return [
            ['name_en' => 'Singapore', 'name_th' => 'สิงคโปร์', 'is_popular' => true],
            ['name_en' => 'Sentosa', 'name_th' => 'เซ็นโตซ่า', 'is_popular' => true],
            ['name_en' => 'Orchard Road', 'name_th' => 'ออร์ชาร์ด โร้ด'],
            ['name_en' => 'Marina Bay', 'name_th' => 'มารีน่า เบย์', 'is_popular' => true],
            ['name_en' => 'Chinatown', 'name_th' => 'ไชน่าทาวน์'],
            ['name_en' => 'Little India', 'name_th' => 'ลิตเติ้ล อินเดีย'],
            ['name_en' => 'Kampong Glam', 'name_th' => 'กัมปง กลาม'],
            ['name_en' => 'Jurong', 'name_th' => 'จูรง'],
            ['name_en' => 'Changi', 'name_th' => 'ชางงี'],
        ];
    }
    
    // ===== เมืองในมาเลเซีย =====
    private function getMalaysiaCities(): array
    {
        return [
            ['name_en' => 'Kuala Lumpur', 'name_th' => 'กัวลาลัมเปอร์', 'is_popular' => true],
            ['name_en' => 'Penang', 'name_th' => 'ปีนัง', 'is_popular' => true],
            ['name_en' => 'Langkawi', 'name_th' => 'ลังกาวี', 'is_popular' => true],
            ['name_en' => 'Malacca', 'name_th' => 'มะละกา', 'is_popular' => true],
            ['name_en' => 'Johor Bahru', 'name_th' => 'ยะโฮร์บาห์รู'],
            ['name_en' => 'Genting Highlands', 'name_th' => 'เก็นติ้ง ไฮแลนด์', 'is_popular' => true],
            ['name_en' => 'Cameron Highlands', 'name_th' => 'คาเมรอน ไฮแลนด์'],
            ['name_en' => 'Ipoh', 'name_th' => 'อิโปห์'],
            ['name_en' => 'Kuching', 'name_th' => 'กูชิง'],
            ['name_en' => 'Kota Kinabalu', 'name_th' => 'โกตาคินาบาลู', 'is_popular' => true],
            ['name_en' => 'Sabah', 'name_th' => 'ซาบาห์'],
            ['name_en' => 'Sarawak', 'name_th' => 'ซาราวัก'],
            ['name_en' => 'Putrajaya', 'name_th' => 'ปุตราจายา'],
            ['name_en' => 'Shah Alam', 'name_th' => 'ชาห์อาลัม'],
            ['name_en' => 'Selangor', 'name_th' => 'เซอลังงอร์'],
            ['name_en' => 'Terengganu', 'name_th' => 'ตรังกานู'],
            ['name_en' => 'Redang Island', 'name_th' => 'เกาะเรดัง'],
            ['name_en' => 'Tioman Island', 'name_th' => 'เกาะติโอมัน'],
            ['name_en' => 'Perhentian Islands', 'name_th' => 'หมู่เกาะเปอร์เฮนเตียน'],
            ['name_en' => 'Batu Caves', 'name_th' => 'ถ้ำบาตู'],
        ];
    }
    
    // ===== เมืองในอินโดนีเซีย =====
    private function getIndonesiaCities(): array
    {
        return [
            ['name_en' => 'Bali', 'name_th' => 'บาหลี', 'is_popular' => true],
            ['name_en' => 'Jakarta', 'name_th' => 'จาการ์ตา', 'is_popular' => true],
            ['name_en' => 'Ubud', 'name_th' => 'อูบุด', 'is_popular' => true],
            ['name_en' => 'Seminyak', 'name_th' => 'เซมินยัก', 'is_popular' => true],
            ['name_en' => 'Kuta', 'name_th' => 'กูตา'],
            ['name_en' => 'Sanur', 'name_th' => 'ซานูร์'],
            ['name_en' => 'Nusa Dua', 'name_th' => 'นูซาดัว'],
            ['name_en' => 'Yogyakarta', 'name_th' => 'ยอกยาการ์ตา', 'is_popular' => true],
            ['name_en' => 'Surabaya', 'name_th' => 'สุราบายา'],
            ['name_en' => 'Bandung', 'name_th' => 'บันดุง'],
            ['name_en' => 'Lombok', 'name_th' => 'ลอมบอก', 'is_popular' => true],
            ['name_en' => 'Gili Islands', 'name_th' => 'หมู่เกาะกิลี', 'is_popular' => true],
            ['name_en' => 'Komodo Island', 'name_th' => 'เกาะโคโมโด'],
            ['name_en' => 'Flores', 'name_th' => 'ฟลอเรส'],
            ['name_en' => 'Raja Ampat', 'name_th' => 'ราชาอัมปัต'],
            ['name_en' => 'Sulawesi', 'name_th' => 'สุลาเวสี'],
            ['name_en' => 'Sumatra', 'name_th' => 'สุมาตรา'],
            ['name_en' => 'Medan', 'name_th' => 'เมดาน'],
            ['name_en' => 'Bintan', 'name_th' => 'บินตัน'],
            ['name_en' => 'Batam', 'name_th' => 'บาตัม'],
            ['name_en' => 'Nusa Penida', 'name_th' => 'นูซาเปอนิดา', 'is_popular' => true],
            ['name_en' => 'Canggu', 'name_th' => 'จางกู'],
            ['name_en' => 'Uluwatu', 'name_th' => 'อูลูวาตู'],
            ['name_en' => 'Tanah Lot', 'name_th' => 'ตานาห์ล็อต'],
            ['name_en' => 'Bromo', 'name_th' => 'โบรโม่'],
        ];
    }
    
    // ===== เมืองในฟิลิปปินส์ =====
    private function getPhilippinesCities(): array
    {
        return [
            ['name_en' => 'Manila', 'name_th' => 'มะนิลา', 'is_popular' => true],
            ['name_en' => 'Cebu', 'name_th' => 'เซบู', 'is_popular' => true],
            ['name_en' => 'Boracay', 'name_th' => 'โบราไกย์', 'is_popular' => true],
            ['name_en' => 'Palawan', 'name_th' => 'ปาลาวัน', 'is_popular' => true],
            ['name_en' => 'El Nido', 'name_th' => 'เอลนิโด', 'is_popular' => true],
            ['name_en' => 'Coron', 'name_th' => 'โครอน'],
            ['name_en' => 'Bohol', 'name_th' => 'โบโฮล', 'is_popular' => true],
            ['name_en' => 'Siargao', 'name_th' => 'เซียร์เกา'],
            ['name_en' => 'Davao', 'name_th' => 'ดาเวา'],
            ['name_en' => 'Baguio', 'name_th' => 'บาเกียว'],
            ['name_en' => 'Vigan', 'name_th' => 'วีกัน'],
            ['name_en' => 'Tagaytay', 'name_th' => 'ตากายไตย์'],
            ['name_en' => 'Puerto Princesa', 'name_th' => 'ปูเอร์โต ปรินเซซา'],
            ['name_en' => 'Mactan', 'name_th' => 'มักตัน'],
            ['name_en' => 'Dumaguete', 'name_th' => 'ดูมาเกเต'],
            ['name_en' => 'Iloilo', 'name_th' => 'อิโลอิโล'],
            ['name_en' => 'Batanes', 'name_th' => 'บาตาเนส'],
            ['name_en' => 'La Union', 'name_th' => 'ลา ยูเนียน'],
            ['name_en' => 'Sagada', 'name_th' => 'ซากาดา'],
            ['name_en' => 'Panglao', 'name_th' => 'พังเลา'],
        ];
    }
    
    // ===== เมืองในอินเดีย =====
    private function getIndiaCities(): array
    {
        return [
            ['name_en' => 'New Delhi', 'name_th' => 'นิวเดลี', 'is_popular' => true],
            ['name_en' => 'Mumbai', 'name_th' => 'มุมไบ', 'is_popular' => true],
            ['name_en' => 'Agra', 'name_th' => 'อักรา', 'is_popular' => true],
            ['name_en' => 'Jaipur', 'name_th' => 'ชัยปุระ', 'is_popular' => true],
            ['name_en' => 'Varanasi', 'name_th' => 'พาราณสี', 'is_popular' => true],
            ['name_en' => 'Goa', 'name_th' => 'กัว', 'is_popular' => true],
            ['name_en' => 'Kerala', 'name_th' => 'เกรละ'],
            ['name_en' => 'Udaipur', 'name_th' => 'อุไดปูร์'],
            ['name_en' => 'Jodhpur', 'name_th' => 'โจธปูร์'],
            ['name_en' => 'Jaisalmer', 'name_th' => 'ไจซัลแมร์'],
            ['name_en' => 'Kolkata', 'name_th' => 'โกลกาตา'],
            ['name_en' => 'Chennai', 'name_th' => 'เชนไน'],
            ['name_en' => 'Bangalore', 'name_th' => 'บังกาลอร์'],
            ['name_en' => 'Hyderabad', 'name_th' => 'ไฮเดอราบาด'],
            ['name_en' => 'Darjeeling', 'name_th' => 'ดาร์จีลิง'],
            ['name_en' => 'Shimla', 'name_th' => 'ชิมลา'],
            ['name_en' => 'Manali', 'name_th' => 'มานาลี'],
            ['name_en' => 'Rishikesh', 'name_th' => 'ริชิเกช'],
            ['name_en' => 'Amritsar', 'name_th' => 'อมฤตสระ'],
            ['name_en' => 'Leh Ladakh', 'name_th' => 'เลห์ ลาดัก'],
            ['name_en' => 'Mysore', 'name_th' => 'ไมซอร์'],
            ['name_en' => 'Hampi', 'name_th' => 'ฮัมปี'],
            ['name_en' => 'Khajuraho', 'name_th' => 'คาจูราโห'],
            ['name_en' => 'Pushkar', 'name_th' => 'พุชการ์'],
            ['name_en' => 'Pondicherry', 'name_th' => 'ปอนดิเชอร์รี'],
        ];
    }
    
    // ===== เมืองในฮ่องกง =====
    private function getHongKongCities(): array
    {
        return [
            ['name_en' => 'Hong Kong Island', 'name_th' => 'เกาะฮ่องกง', 'is_popular' => true],
            ['name_en' => 'Kowloon', 'name_th' => 'เกาลูน', 'is_popular' => true],
            ['name_en' => 'Tsim Sha Tsui', 'name_th' => 'จิมซาจุ่ย', 'is_popular' => true],
            ['name_en' => 'Mong Kok', 'name_th' => 'มงก๊ก'],
            ['name_en' => 'Central', 'name_th' => 'เซ็นทรัล', 'is_popular' => true],
            ['name_en' => 'Causeway Bay', 'name_th' => 'คอสเวย์เบย์'],
            ['name_en' => 'Lantau Island', 'name_th' => 'เกาะลันเตา'],
            ['name_en' => 'Victoria Peak', 'name_th' => 'วิคตอเรีย พีค', 'is_popular' => true],
            ['name_en' => 'Stanley', 'name_th' => 'สแตนลีย์'],
            ['name_en' => 'Aberdeen', 'name_th' => 'อเบอร์ดีน'],
            ['name_en' => 'Wan Chai', 'name_th' => 'หว่านไจ๋'],
            ['name_en' => 'New Territories', 'name_th' => 'นิวเทอริทอรีส์'],
            ['name_en' => 'Sai Kung', 'name_th' => 'ไซกุง'],
            ['name_en' => 'Repulse Bay', 'name_th' => 'รีพัลส์เบย์'],
            ['name_en' => 'Disneyland', 'name_th' => 'ดิสนีย์แลนด์'],
            ['name_en' => 'Ocean Park', 'name_th' => 'โอเชียนพาร์ค'],
        ];
    }
    
    // ===== เมืองในมาเก๊า =====
    private function getMacauCities(): array
    {
        return [
            ['name_en' => 'Macau Peninsula', 'name_th' => 'คาบสมุทรมาเก๊า', 'is_popular' => true],
            ['name_en' => 'Taipa', 'name_th' => 'ไทปา'],
            ['name_en' => 'Cotai', 'name_th' => 'โคไท', 'is_popular' => true],
            ['name_en' => 'Coloane', 'name_th' => 'โคโลอาน'],
            ['name_en' => 'Senado Square', 'name_th' => 'เซนาโด สแควร์', 'is_popular' => true],
            ['name_en' => 'Ruins of St. Paul', 'name_th' => 'ซากโบสถ์เซนต์ปอล', 'is_popular' => true],
            ['name_en' => 'Venetian Macau', 'name_th' => 'เวเนเชียน มาเก๊า', 'is_popular' => true],
            ['name_en' => 'Macau Tower', 'name_th' => 'มาเก๊า ทาวเวอร์'],
        ];
    }
}
