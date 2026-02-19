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

        // ===== Asia - East =====
        $this->seedCities('JP', $this->getJapanCities());
        $this->seedCities('KR', $this->getKoreaCities());
        $this->seedCities('CN', $this->getChinaCities());
        $this->seedCities('TW', $this->getTaiwanCities());
        $this->seedCities('HK', $this->getHongKongCities());
        $this->seedCities('MO', $this->getMacauCities());

        // ===== Asia - Southeast =====
        $this->seedCities('TH', $this->getThailandCities());
        $this->seedCities('VN', $this->getVietnamCities());
        $this->seedCities('SG', $this->getSingaporeCities());
        $this->seedCities('MY', $this->getMalaysiaCities());
        $this->seedCities('ID', $this->getIndonesiaCities());
        $this->seedCities('PH', $this->getPhilippinesCities());
        $this->seedCities('MM', $this->getMyanmarCities());
        $this->seedCities('KH', $this->getCambodiaCities());
        $this->seedCities('LA', $this->getLaosCities());

        // ===== Asia - South =====
        $this->seedCities('IN', $this->getIndiaCities());
        $this->seedCities('NP', $this->getNepalCities());
        $this->seedCities('LK', $this->getSriLankaCities());
        $this->seedCities('MV', $this->getMaldivesCities());

        // ===== Middle East =====
        $this->seedCities('AE', $this->getUAECities());
        $this->seedCities('TR', $this->getTurkeyCities());
        $this->seedCities('JO', $this->getJordanCities());
        $this->seedCities('SA', $this->getSaudiArabiaCities());
        $this->seedCities('QA', $this->getQatarCities());

        // ===== Europe - Western =====
        $this->seedCities('GB', $this->getUKCities());
        $this->seedCities('FR', $this->getFranceCities());
        $this->seedCities('DE', $this->getGermanyCities());
        $this->seedCities('IT', $this->getItalyCities());
        $this->seedCities('ES', $this->getSpainCities());
        $this->seedCities('CH', $this->getSwitzerlandCities());
        $this->seedCities('AT', $this->getAustriaCities());
        $this->seedCities('NL', $this->getNetherlandsCities());
        $this->seedCities('BE', $this->getBelgiumCities());
        $this->seedCities('PT', $this->getPortugalCities());

        // ===== Europe - Northern =====
        $this->seedCities('SE', $this->getSwedenCities());
        $this->seedCities('NO', $this->getNorwayCities());
        $this->seedCities('DK', $this->getDenmarkCities());
        $this->seedCities('FI', $this->getFinlandCities());
        $this->seedCities('IS', $this->getIcelandCities());

        // ===== Europe - Eastern & Balkans =====
        $this->seedCities('RU', $this->getRussiaCities());
        $this->seedCities('CZ', $this->getCzechCities());
        $this->seedCities('HU', $this->getHungaryCities());
        $this->seedCities('PL', $this->getPolandCities());
        $this->seedCities('GR', $this->getGreeceCities());
        $this->seedCities('HR', $this->getCroatiaCities());

        // ===== Americas =====
        $this->seedCities('US', $this->getUSACities());
        $this->seedCities('CA', $this->getCanadaCities());
        $this->seedCities('MX', $this->getMexicoCities());
        $this->seedCities('BR', $this->getBrazilCities());
        $this->seedCities('AR', $this->getArgentinaCities());
        $this->seedCities('PE', $this->getPeruCities());

        // ===== Africa =====
        $this->seedCities('EG', $this->getEgyptCities());
        $this->seedCities('MA', $this->getMoroccoCities());
        $this->seedCities('ZA', $this->getSouthAfricaCities());
        $this->seedCities('KE', $this->getKenyaCities());
        $this->seedCities('TZ', $this->getTanzaniaCities());

        // ===== Oceania =====
        $this->seedCities('AU', $this->getAustraliaCities());
        $this->seedCities('NZ', $this->getNewZealandCities());

        $this->command->info('Cities seeder completed!');
    }
    
    private function seedCities(string $iso2, array $cities): void
    {
        $country = DB::table('countries')->where('iso2', strtoupper($iso2))->first();
        if (!$country) {
            $this->command->warn("Country {$iso2} not found, skipping...");
            return;
        }
        $countryId = $country->id;
        
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

    // ===== เมืองในพม่า =====
    private function getMyanmarCities(): array
    {
        return [
            ['name_en' => 'Yangon', 'name_th' => 'ย่างกุ้ง', 'is_popular' => true],
            ['name_en' => 'Mandalay', 'name_th' => 'มัณฑะเลย์', 'is_popular' => true],
            ['name_en' => 'Bagan', 'name_th' => 'พุกาม', 'is_popular' => true],
            ['name_en' => 'Inle Lake', 'name_th' => 'ทะเลสาบอินเล', 'is_popular' => true],
            ['name_en' => 'Naypyidaw', 'name_th' => 'เนปีดอ'],
            ['name_en' => 'Ngapali Beach', 'name_th' => 'งาปาลีบีช'],
            ['name_en' => 'Hsipaw', 'name_th' => 'ซีป้อ'],
            ['name_en' => 'Kyaiktiyo', 'name_th' => 'ไจ้ทีโย'],
            ['name_en' => 'Mawlamyine', 'name_th' => 'มะละแหม่ง'],
            ['name_en' => 'Hpa-an', 'name_th' => 'ผะอาน'],
        ];
    }

    // ===== เมืองในกัมพูชา =====
    private function getCambodiaCities(): array
    {
        return [
            ['name_en' => 'Phnom Penh', 'name_th' => 'พนมเปญ', 'is_popular' => true],
            ['name_en' => 'Siem Reap', 'name_th' => 'เสียมราฐ', 'is_popular' => true],
            ['name_en' => 'Angkor Wat', 'name_th' => 'นครวัด', 'is_popular' => true],
            ['name_en' => 'Sihanoukville', 'name_th' => 'สีหนุวิลล์', 'is_popular' => true],
            ['name_en' => 'Battambang', 'name_th' => 'พระตะบอง'],
            ['name_en' => 'Kampot', 'name_th' => 'กำปอต'],
            ['name_en' => 'Kep', 'name_th' => 'แกบ'],
            ['name_en' => 'Koh Rong', 'name_th' => 'เกาะรอง'],
            ['name_en' => 'Isles of Koh Rong Samloem', 'name_th' => 'เกาะรองแสมเลิม'],
        ];
    }

    // ===== เมืองในลาว =====
    private function getLaosCities(): array
    {
        return [
            ['name_en' => 'Vientiane', 'name_th' => 'เวียงจันทน์', 'is_popular' => true],
            ['name_en' => 'Luang Prabang', 'name_th' => 'หลวงพระบาง', 'is_popular' => true],
            ['name_en' => 'Vang Vieng', 'name_th' => 'วังเวียง', 'is_popular' => true],
            ['name_en' => 'Pakse', 'name_th' => 'ปากเซ'],
            ['name_en' => 'Savannakhet', 'name_th' => 'สะหวันนะเขต'],
            ['name_en' => 'Phonsali', 'name_th' => 'ฝ้ายสาลี'],
            ['name_en' => 'Nong Khiaw', 'name_th' => 'หนองเขียว'],
            ['name_en' => 'Bolaven Plateau', 'name_th' => 'ที่ราบสูงโบลาเวน'],
        ];
    }

    // ===== เมืองในเนปาล =====
    private function getNepalCities(): array
    {
        return [
            ['name_en' => 'Kathmandu', 'name_th' => 'กาฐมาณฑุ', 'is_popular' => true],
            ['name_en' => 'Pokhara', 'name_th' => 'โปขรา', 'is_popular' => true],
            ['name_en' => 'Chitwan', 'name_th' => 'ชิตวัน'],
            ['name_en' => 'Lumbini', 'name_th' => 'ลุมพินี'],
            ['name_en' => 'Everest Base Camp', 'name_th' => 'ค่ายฐานเอเวอเรสต์', 'is_popular' => true],
            ['name_en' => 'Annapurna', 'name_th' => 'อันนาปุรณา', 'is_popular' => true],
            ['name_en' => 'Bhaktapur', 'name_th' => 'ภักตาปูร์'],
            ['name_en' => 'Nagarkot', 'name_th' => 'นากาโกต'],
            ['name_en' => 'Bandipur', 'name_th' => 'บันดีปูร์'],
            ['name_en' => 'Mustang', 'name_th' => 'มุสตัง'],
        ];
    }

    // ===== เมืองในศรีลังกา =====
    private function getSriLankaCities(): array
    {
        return [
            ['name_en' => 'Colombo', 'name_th' => 'โคลัมโบ', 'is_popular' => true],
            ['name_en' => 'Kandy', 'name_th' => 'แคนดี้', 'is_popular' => true],
            ['name_en' => 'Sigiriya', 'name_th' => 'สิกิริยา', 'is_popular' => true],
            ['name_en' => 'Galle', 'name_th' => 'กอล', 'is_popular' => true],
            ['name_en' => 'Ella', 'name_th' => 'เอลลา', 'is_popular' => true],
            ['name_en' => 'Nuwara Eliya', 'name_th' => 'นูวาระเอลิยะ'],
            ['name_en' => 'Anuradhapura', 'name_th' => 'อนุราธปุระ'],
            ['name_en' => 'Polonnaruwa', 'name_th' => 'โปลอนนารุวะ'],
            ['name_en' => 'Trincomalee', 'name_th' => 'ตรินโคมาลี'],
            ['name_en' => 'Mirissa', 'name_th' => 'มิริสสา'],
            ['name_en' => 'Negombo', 'name_th' => 'เนกอมโบ'],
            ['name_en' => 'Bentota', 'name_th' => 'เบนโตตา'],
        ];
    }

    // ===== เมืองในมัลดีฟส์ =====
    private function getMaldivesCities(): array
    {
        return [
            ['name_en' => 'Male', 'name_th' => 'มาเล', 'is_popular' => true],
            ['name_en' => 'North Male Atoll', 'name_th' => 'เหนือมาเล อะทอลล์', 'is_popular' => true],
            ['name_en' => 'South Male Atoll', 'name_th' => 'ใต้มาเล อะทอลล์'],
            ['name_en' => 'Ari Atoll', 'name_th' => 'อะทอลล์อารี', 'is_popular' => true],
            ['name_en' => 'Baa Atoll', 'name_th' => 'อะทอลล์บา'],
            ['name_en' => 'Maafushi', 'name_th' => 'มาฟูชิ'],
            ['name_en' => 'Hulhumale', 'name_th' => 'ฮูลูมาเล'],
            ['name_en' => 'Addu Atoll', 'name_th' => 'อะทอลล์อัดดู'],
        ];
    }

    // ===== เมืองในยูเออี =====
    private function getUAECities(): array
    {
        return [
            ['name_en' => 'Dubai', 'name_th' => 'ดูไบ', 'is_popular' => true],
            ['name_en' => 'Abu Dhabi', 'name_th' => 'อาบูดาบี', 'is_popular' => true],
            ['name_en' => 'Sharjah', 'name_th' => 'ชาร์จาห์'],
            ['name_en' => 'Ajman', 'name_th' => 'อัจมาน'],
            ['name_en' => 'Ras Al Khaimah', 'name_th' => 'ราสอัลไคมาห์'],
            ['name_en' => 'Fujairah', 'name_th' => 'ฟูไจราห์'],
            ['name_en' => 'Umm Al Quwain', 'name_th' => 'อุมม์อัลไกวาอิน'],
            ['name_en' => 'Al Ain', 'name_th' => 'อัลไอน์'],
        ];
    }

    // ===== เมืองในตุรกี =====
    private function getTurkeyCities(): array
    {
        return [
            ['name_en' => 'Istanbul', 'name_th' => 'อิสตันบูล', 'is_popular' => true],
            ['name_en' => 'Cappadocia', 'name_th' => 'คัปปาโดเกีย', 'is_popular' => true],
            ['name_en' => 'Antalya', 'name_th' => 'อันตาเลีย', 'is_popular' => true],
            ['name_en' => 'Pamukkale', 'name_th' => 'ปามุคคาเล', 'is_popular' => true],
            ['name_en' => 'Ephesus', 'name_th' => 'เอเฟซัส', 'is_popular' => true],
            ['name_en' => 'Ankara', 'name_th' => 'อังการา'],
            ['name_en' => 'Izmir', 'name_th' => 'อิซเมียร์'],
            ['name_en' => 'Bodrum', 'name_th' => 'โบดรุม'],
            ['name_en' => 'Kusadasi', 'name_th' => 'คูซาดาซี'],
            ['name_en' => 'Trabzon', 'name_th' => 'ทราซอน'],
            ['name_en' => 'Goreme', 'name_th' => 'กอเรเม', 'is_popular' => true],
            ['name_en' => 'Konya', 'name_th' => 'คอนยา'],
            ['name_en' => 'Bursa', 'name_th' => 'บูร์ซา'],
            ['name_en' => 'Gallipoli', 'name_th' => 'กัลลิโปลี'],
            ['name_en' => 'Troy', 'name_th' => 'ทรอย'],
        ];
    }

    // ===== เมืองในจอร์แดน =====
    private function getJordanCities(): array
    {
        return [
            ['name_en' => 'Amman', 'name_th' => 'อัมมาน', 'is_popular' => true],
            ['name_en' => 'Petra', 'name_th' => 'เปตรา', 'is_popular' => true],
            ['name_en' => 'Wadi Rum', 'name_th' => 'วาดีรัม', 'is_popular' => true],
            ['name_en' => 'Aqaba', 'name_th' => 'อะกาบา'],
            ['name_en' => 'Dead Sea', 'name_th' => 'ทะเลเดดซี', 'is_popular' => true],
            ['name_en' => 'Jerash', 'name_th' => 'เจราช'],
            ['name_en' => 'Ajloun', 'name_th' => 'อัจลุน'],
            ['name_en' => 'Madaba', 'name_th' => 'มาดาบา'],
        ];
    }

    // ===== เมืองในซาอุดีอาระเบีย =====
    private function getSaudiArabiaCities(): array
    {
        return [
            ['name_en' => 'Riyadh', 'name_th' => 'ริยาด', 'is_popular' => true],
            ['name_en' => 'Jeddah', 'name_th' => 'เจดดาห์', 'is_popular' => true],
            ['name_en' => 'Mecca', 'name_th' => 'เมกกะ'],
            ['name_en' => 'Medina', 'name_th' => 'มดีนะ'],
            ['name_en' => 'AlUla', 'name_th' => 'อัลอูลา', 'is_popular' => true],
            ['name_en' => 'Tabuk', 'name_th' => 'ตาบุก'],
            ['name_en' => 'Abha', 'name_th' => 'อับฮา'],
            ['name_en' => 'NEOM', 'name_th' => 'นีออม'],
        ];
    }

    // ===== เมืองในกาตาร์ =====
    private function getQatarCities(): array
    {
        return [
            ['name_en' => 'Doha', 'name_th' => 'โดฮา', 'is_popular' => true],
            ['name_en' => 'Al Wakrah', 'name_th' => 'อัล วักเราะห์'],
            ['name_en' => 'Al Khor', 'name_th' => 'อัล คอร์'],
            ['name_en' => 'Lusail', 'name_th' => 'ลูซาอิล'],
            ['name_en' => 'Dukhan', 'name_th' => 'ดูกัน'],
        ];
    }

    // ===== เมืองในสหราชอาณาจักร =====
    private function getUKCities(): array
    {
        return [
            ['name_en' => 'London', 'name_th' => 'ลอนดอน', 'is_popular' => true],
            ['name_en' => 'Edinburgh', 'name_th' => 'เอดินบะระ', 'is_popular' => true],
            ['name_en' => 'Manchester', 'name_th' => 'แมนเชสเตอร์'],
            ['name_en' => 'Birmingham', 'name_th' => 'เบอร์มิงแฮม'],
            ['name_en' => 'Liverpool', 'name_th' => 'ลิเวอร์พูล'],
            ['name_en' => 'Glasgow', 'name_th' => 'กลาสโกว์'],
            ['name_en' => 'Oxford', 'name_th' => 'ออกซ์ฟอร์ด', 'is_popular' => true],
            ['name_en' => 'Cambridge', 'name_th' => 'เคมบริดจ์', 'is_popular' => true],
            ['name_en' => 'Bath', 'name_th' => 'บาธ', 'is_popular' => true],
            ['name_en' => 'Stonehenge', 'name_th' => 'สโตนเฮนจ์', 'is_popular' => true],
            ['name_en' => 'Cotswolds', 'name_th' => 'คอตสวอลด์', 'is_popular' => true],
            ['name_en' => 'Windsor', 'name_th' => 'วินด์เซอร์'],
            ['name_en' => 'York', 'name_th' => 'ยอร์ก'],
            ['name_en' => 'Cardiff', 'name_th' => 'คาร์ดิฟฟ์'],
            ['name_en' => 'Belfast', 'name_th' => 'เบลฟาสต์'],
            ['name_en' => 'Inverness', 'name_th' => 'อินเวอร์เนส'],
            ['name_en' => 'Loch Ness', 'name_th' => 'ล็อกเนส'],
            ['name_en' => 'Scottish Highlands', 'name_th' => 'ไฮแลนด์สก็อตแลนด์'],
        ];
    }

    // ===== เมืองในฝรั่งเศส =====
    private function getFranceCities(): array
    {
        return [
            ['name_en' => 'Paris', 'name_th' => 'ปารีส', 'is_popular' => true],
            ['name_en' => 'Nice', 'name_th' => 'นีซ', 'is_popular' => true],
            ['name_en' => 'Lyon', 'name_th' => 'ลียง'],
            ['name_en' => 'Marseille', 'name_th' => 'มาร์เซย์'],
            ['name_en' => 'Bordeaux', 'name_th' => 'บอร์โด'],
            ['name_en' => 'Strasbourg', 'name_th' => 'สตราสบูร์ก'],
            ['name_en' => 'Mont Saint-Michel', 'name_th' => 'มองต์แซงต์มิเชล', 'is_popular' => true],
            ['name_en' => 'Versailles', 'name_th' => 'แวร์ซาย', 'is_popular' => true],
            ['name_en' => 'Cannes', 'name_th' => 'คานส์'],
            ['name_en' => 'Chamonix', 'name_th' => 'ชาโมนิกซ์'],
            ['name_en' => 'Loire Valley', 'name_th' => 'หุบเขาลัวร์'],
            ['name_en' => 'Normandy', 'name_th' => 'นอร์มังดี'],
            ['name_en' => 'Alsace', 'name_th' => 'อัลซาส'],
            ['name_en' => 'Provence', 'name_th' => 'โพรวองซ์'],
            ['name_en' => 'Disneyland Paris', 'name_th' => 'ดิสนีย์แลนด์ปารีส'],
        ];
    }

    // ===== เมืองในเยอรมนี =====
    private function getGermanyCities(): array
    {
        return [
            ['name_en' => 'Berlin', 'name_th' => 'เบอร์ลิน', 'is_popular' => true],
            ['name_en' => 'Munich', 'name_th' => 'มิวนิก', 'is_popular' => true],
            ['name_en' => 'Frankfurt', 'name_th' => 'แฟรงก์เฟิร์ต'],
            ['name_en' => 'Hamburg', 'name_th' => 'ฮัมบูร์ก'],
            ['name_en' => 'Cologne', 'name_th' => 'โคโลญ'],
            ['name_en' => 'Stuttgart', 'name_th' => 'สตุตการ์ท'],
            ['name_en' => 'Düsseldorf', 'name_th' => 'ดุสเซลดอร์ฟ'],
            ['name_en' => 'Dresden', 'name_th' => 'เดรสเดน'],
            ['name_en' => 'Heidelberg', 'name_th' => 'ไฮเดลเบิร์ก', 'is_popular' => true],
            ['name_en' => 'Rothenburg ob der Tauber', 'name_th' => 'โรเทนบวร์ก', 'is_popular' => true],
            ['name_en' => 'Neuschwanstein', 'name_th' => 'นอยชวานสไตน์', 'is_popular' => true],
            ['name_en' => 'Nuremberg', 'name_th' => 'นูเรมเบิร์ก'],
            ['name_en' => 'Leipzig', 'name_th' => 'ไลพ์ซิก'],
            ['name_en' => 'Black Forest', 'name_th' => 'ป่าดำ'],
            ['name_en' => 'Rhine Valley', 'name_th' => 'หุบเขาไรน์'],
            ['name_en' => 'Bavarian Alps', 'name_th' => 'เทือกเขาแอลป์บาวาเรีย'],
        ];
    }

    // ===== เมืองในอิตาลี =====
    private function getItalyCities(): array
    {
        return [
            ['name_en' => 'Rome', 'name_th' => 'โรม', 'is_popular' => true],
            ['name_en' => 'Venice', 'name_th' => 'เวนิส', 'is_popular' => true],
            ['name_en' => 'Florence', 'name_th' => 'ฟลอเรนซ์', 'is_popular' => true],
            ['name_en' => 'Milan', 'name_th' => 'มิลาน', 'is_popular' => true],
            ['name_en' => 'Naples', 'name_th' => 'เนเปิลส์'],
            ['name_en' => 'Amalfi Coast', 'name_th' => 'ชายฝั่งอมาลฟี', 'is_popular' => true],
            ['name_en' => 'Cinque Terre', 'name_th' => 'ชิงเกวแตร์เร', 'is_popular' => true],
            ['name_en' => 'Tuscany', 'name_th' => 'ทัสคานี', 'is_popular' => true],
            ['name_en' => 'Pompeii', 'name_th' => 'ปอมเปอี'],
            ['name_en' => 'Sicily', 'name_th' => 'ซิซิลี'],
            ['name_en' => 'Sardinia', 'name_th' => 'ซาร์ดิเนีย'],
            ['name_en' => 'Bologna', 'name_th' => 'โบโลญญา'],
            ['name_en' => 'Verona', 'name_th' => 'เวโรนา'],
            ['name_en' => 'Pisa', 'name_th' => 'ปิซา'],
            ['name_en' => 'Capri', 'name_th' => 'คาปรี'],
            ['name_en' => 'Positano', 'name_th' => 'โพซิตาโน'],
        ];
    }

    // ===== เมืองในสเปน =====
    private function getSpainCities(): array
    {
        return [
            ['name_en' => 'Barcelona', 'name_th' => 'บาร์เซโลนา', 'is_popular' => true],
            ['name_en' => 'Madrid', 'name_th' => 'มาดริด', 'is_popular' => true],
            ['name_en' => 'Seville', 'name_th' => 'เซบีญา', 'is_popular' => true],
            ['name_en' => 'Valencia', 'name_th' => 'บาเลนเซีย'],
            ['name_en' => 'Granada', 'name_th' => 'กรานาดา', 'is_popular' => true],
            ['name_en' => 'Bilbao', 'name_th' => 'บิลบาโอ'],
            ['name_en' => 'Toledo', 'name_th' => 'โตเลโด'],
            ['name_en' => 'Mallorca', 'name_th' => 'มายอร์กา', 'is_popular' => true],
            ['name_en' => 'Ibiza', 'name_th' => 'อิบิซา'],
            ['name_en' => 'Tenerife', 'name_th' => 'เตเนรีเฟ', 'is_popular' => true],
            ['name_en' => 'San Sebastian', 'name_th' => 'ซานเซบาเตียน'],
            ['name_en' => 'Cordoba', 'name_th' => 'กอร์โดบา'],
            ['name_en' => 'Santiago de Compostela', 'name_th' => 'ซันเตียโกเดกอมโปสเตลา'],
        ];
    }

    // ===== เมืองในสวิตเซอร์แลนด์ =====
    private function getSwitzerlandCities(): array
    {
        return [
            ['name_en' => 'Zurich', 'name_th' => 'ซูริก', 'is_popular' => true],
            ['name_en' => 'Geneva', 'name_th' => 'เจนีวา', 'is_popular' => true],
            ['name_en' => 'Bern', 'name_th' => 'เบิร์น'],
            ['name_en' => 'Lucerne', 'name_th' => 'ลูเซิร์น', 'is_popular' => true],
            ['name_en' => 'Interlaken', 'name_th' => 'อินเทอร์ลาเคน', 'is_popular' => true],
            ['name_en' => 'Zermatt', 'name_th' => 'เซอร์มัทท์', 'is_popular' => true],
            ['name_en' => 'Jungfrau', 'name_th' => 'ยุงเฟรา', 'is_popular' => true],
            ['name_en' => 'St. Moritz', 'name_th' => 'เซนต์มอริตซ์'],
            ['name_en' => 'Basel', 'name_th' => 'บาเซิล'],
            ['name_en' => 'Lausanne', 'name_th' => 'โลซาน'],
            ['name_en' => 'Grindelwald', 'name_th' => 'กรินเดลวาลด์'],
            ['name_en' => 'Montreux', 'name_th' => 'มงเทรอ'],
        ];
    }

    // ===== เมืองในออสเตรีย =====
    private function getAustriaCities(): array
    {
        return [
            ['name_en' => 'Vienna', 'name_th' => 'เวียนนา', 'is_popular' => true],
            ['name_en' => 'Salzburg', 'name_th' => 'ซาลซ์บูร์ก', 'is_popular' => true],
            ['name_en' => 'Innsbruck', 'name_th' => 'อินส์บรุค'],
            ['name_en' => 'Hallstatt', 'name_th' => 'ฮาลล์สตัทท์', 'is_popular' => true],
            ['name_en' => 'Graz', 'name_th' => 'กราซ'],
            ['name_en' => 'Linz', 'name_th' => 'ลินซ์'],
            ['name_en' => 'Schönbrunn', 'name_th' => 'เชินบรุนน์'],
            ['name_en' => 'Tyrol', 'name_th' => 'ทีโรล'],
        ];
    }

    // ===== เมืองในเนเธอร์แลนด์ =====
    private function getNetherlandsCities(): array
    {
        return [
            ['name_en' => 'Amsterdam', 'name_th' => 'อัมสเตอร์ดัม', 'is_popular' => true],
            ['name_en' => 'Rotterdam', 'name_th' => 'รอตเตอร์ดัม'],
            ['name_en' => 'The Hague', 'name_th' => 'เดอะเฮก'],
            ['name_en' => 'Utrecht', 'name_th' => 'ยูเทรคท์'],
            ['name_en' => 'Eindhoven', 'name_th' => 'ไอนด์โฮเฟน'],
            ['name_en' => 'Keukenhof', 'name_th' => 'เคอเคนโฮฟ', 'is_popular' => true],
            ['name_en' => 'Delft', 'name_th' => 'เดลฟท์'],
            ['name_en' => 'Bruges', 'name_th' => 'บรูจส์'],
            ['name_en' => 'Kinderdijk', 'name_th' => 'กินเดอร์ไดค์'],
        ];
    }

    // ===== เมืองในเบลเยียม =====
    private function getBelgiumCities(): array
    {
        return [
            ['name_en' => 'Brussels', 'name_th' => 'บรัสเซลส์', 'is_popular' => true],
            ['name_en' => 'Bruges', 'name_th' => 'บรูจส์', 'is_popular' => true],
            ['name_en' => 'Ghent', 'name_th' => 'เคนท์'],
            ['name_en' => 'Antwerp', 'name_th' => 'แอนต์เวิร์ป'],
            ['name_en' => 'Mons', 'name_th' => 'มงส์'],
            ['name_en' => 'Liège', 'name_th' => 'ลีแยฌ'],
        ];
    }

    // ===== เมืองในโปรตุเกส =====
    private function getPortugalCities(): array
    {
        return [
            ['name_en' => 'Lisbon', 'name_th' => 'ลิสบอน', 'is_popular' => true],
            ['name_en' => 'Porto', 'name_th' => 'ปอร์โต', 'is_popular' => true],
            ['name_en' => 'Algarve', 'name_th' => 'อัลการ์ฟ', 'is_popular' => true],
            ['name_en' => 'Sintra', 'name_th' => 'ซินตรา', 'is_popular' => true],
            ['name_en' => 'Madeira', 'name_th' => 'มาเดรา'],
            ['name_en' => 'Azores', 'name_th' => 'อาซอเรส'],
            ['name_en' => 'Coimbra', 'name_th' => 'โกอิมบรา'],
            ['name_en' => 'Évora', 'name_th' => 'เอโวรา'],
        ];
    }

    // ===== เมืองในสวีเดน =====
    private function getSwedenCities(): array
    {
        return [
            ['name_en' => 'Stockholm', 'name_th' => 'สตอกโฮล์ม', 'is_popular' => true],
            ['name_en' => 'Gothenburg', 'name_th' => 'โกเธนเบิร์ก'],
            ['name_en' => 'Malmö', 'name_th' => 'มัลเมอ'],
            ['name_en' => 'Uppsala', 'name_th' => 'อุปซอลา'],
            ['name_en' => 'Kiruna', 'name_th' => 'คิรูนา'],
            ['name_en' => 'Abisko', 'name_th' => 'อาบิสโก'],
            ['name_en' => 'Visby', 'name_th' => 'วิสบี'],
        ];
    }

    // ===== เมืองในนอร์เวย์ =====
    private function getNorwayCities(): array
    {
        return [
            ['name_en' => 'Oslo', 'name_th' => 'ออสโล', 'is_popular' => true],
            ['name_en' => 'Bergen', 'name_th' => 'เบอร์เกน', 'is_popular' => true],
            ['name_en' => 'Tromsø', 'name_th' => 'ทรอมโซ', 'is_popular' => true],
            ['name_en' => 'Flåm', 'name_th' => 'ฟลอม', 'is_popular' => true],
            ['name_en' => 'Geiranger', 'name_th' => 'ไกรองเก'],
            ['name_en' => 'Lofoten Islands', 'name_th' => 'หมู่เกาะโลโฟเทน', 'is_popular' => true],
            ['name_en' => 'Ålesund', 'name_th' => 'โอเลซุนด์'],
            ['name_en' => 'Trondheim', 'name_th' => 'ทรอนด์เฮม'],
        ];
    }

    // ===== เมืองในเดนมาร์ก =====
    private function getDenmarkCities(): array
    {
        return [
            ['name_en' => 'Copenhagen', 'name_th' => 'โคเปนเฮเกน', 'is_popular' => true],
            ['name_en' => 'Aarhus', 'name_th' => 'อาร์ฮุส'],
            ['name_en' => 'Odense', 'name_th' => 'โอเดนเซ'],
            ['name_en' => 'Aalborg', 'name_th' => 'อาลบอร์ก'],
            ['name_en' => 'Legoland', 'name_th' => 'เลโก้แลนด์'],
            ['name_en' => 'Bornholm', 'name_th' => 'บอร์นโฮล์ม'],
        ];
    }

    // ===== เมืองในฟินแลนด์ =====
    private function getFinlandCities(): array
    {
        return [
            ['name_en' => 'Helsinki', 'name_th' => 'เฮลซิงกิ', 'is_popular' => true],
            ['name_en' => 'Rovaniemi', 'name_th' => 'โรวาเนียมี', 'is_popular' => true],
            ['name_en' => 'Tampere', 'name_th' => 'ทัมเปเร'],
            ['name_en' => 'Turku', 'name_th' => 'ตูรกู'],
            ['name_en' => 'Saariselkä', 'name_th' => 'ซาริเซลกา'],
            ['name_en' => 'Levi', 'name_th' => 'เลวี'],
            ['name_en' => 'Oulu', 'name_th' => 'อูลู'],
        ];
    }

    // ===== เมืองในไอซ์แลนด์ =====
    private function getIcelandCities(): array
    {
        return [
            ['name_en' => 'Reykjavik', 'name_th' => 'เรคยาวิก', 'is_popular' => true],
            ['name_en' => 'Akureyri', 'name_th' => 'อาคูเรรี'],
            ['name_en' => 'Blue Lagoon', 'name_th' => 'บลูลากูน', 'is_popular' => true],
            ['name_en' => 'Golden Circle', 'name_th' => 'โกลเดนเซอร์เคิล', 'is_popular' => true],
            ['name_en' => 'South Coast', 'name_th' => 'ชายฝั่งใต้'],
            ['name_en' => 'Vatnajökull', 'name_th' => 'วัตนาโจกุตล์'],
            ['name_en' => 'Westfjords', 'name_th' => 'เวสต์ฟยอร์ด'],
        ];
    }

    // ===== เมืองในรัสเซีย =====
    private function getRussiaCities(): array
    {
        return [
            ['name_en' => 'Moscow', 'name_th' => 'มอสโก', 'is_popular' => true],
            ['name_en' => 'Saint Petersburg', 'name_th' => 'เซนต์ปีเตอร์สเบิร์ก', 'is_popular' => true],
            ['name_en' => 'Kazan', 'name_th' => 'คาซาน'],
            ['name_en' => 'Vladivostok', 'name_th' => 'วลาดิวอสต็อก'],
            ['name_en' => 'Sochi', 'name_th' => 'โซชิ'],
            ['name_en' => 'Novosibirsk', 'name_th' => 'โนโวซีบีร์สก์'],
            ['name_en' => 'Lake Baikal', 'name_th' => 'ทะเลสาบไบคาล', 'is_popular' => true],
            ['name_en' => 'Irkutsk', 'name_th' => 'อีร์คุตสค์'],
        ];
    }

    // ===== เมืองในสาธารณรัฐเช็ก =====
    private function getCzechCities(): array
    {
        return [
            ['name_en' => 'Prague', 'name_th' => 'ปราก', 'is_popular' => true],
            ['name_en' => 'Brno', 'name_th' => 'บร์โน'],
            ['name_en' => 'Cesky Krumlov', 'name_th' => 'เชสกีครุมลอฟ', 'is_popular' => true],
            ['name_en' => 'Karlovy Vary', 'name_th' => 'คาร์โลวีวาร'],
            ['name_en' => 'Pilsen', 'name_th' => 'พิลเซน'],
            ['name_en' => 'Olomouc', 'name_th' => 'โอโลมุตส์'],
        ];
    }

    // ===== เมืองในฮังการี =====
    private function getHungaryCities(): array
    {
        return [
            ['name_en' => 'Budapest', 'name_th' => 'บูดาเปสต์', 'is_popular' => true],
            ['name_en' => 'Debrecen', 'name_th' => 'เดเบรเซน'],
            ['name_en' => 'Pécs', 'name_th' => 'เปช'],
            ['name_en' => 'Győr', 'name_th' => 'เกอร์'],
            ['name_en' => 'Eger', 'name_th' => 'เอเกอร์'],
            ['name_en' => 'Lake Balaton', 'name_th' => 'ทะเลสาบบาลาตอน'],
        ];
    }

    // ===== เมืองในโปแลนด์ =====
    private function getPolandCities(): array
    {
        return [
            ['name_en' => 'Warsaw', 'name_th' => 'วอร์ซอ', 'is_popular' => true],
            ['name_en' => 'Krakow', 'name_th' => 'คราคูฟ', 'is_popular' => true],
            ['name_en' => 'Gdansk', 'name_th' => 'กดัญสก์'],
            ['name_en' => 'Wroclaw', 'name_th' => 'วรอตส์วาฟ'],
            ['name_en' => 'Poznan', 'name_th' => 'พอซนาน'],
            ['name_en' => 'Zakopane', 'name_th' => 'ซาโกพาเน'],
            ['name_en' => 'Auschwitz', 'name_th' => 'เอาชวิทซ์'],
        ];
    }

    // ===== เมืองในกรีซ =====
    private function getGreeceCities(): array
    {
        return [
            ['name_en' => 'Athens', 'name_th' => 'เอเธนส์', 'is_popular' => true],
            ['name_en' => 'Santorini', 'name_th' => 'ซานโตรินี', 'is_popular' => true],
            ['name_en' => 'Mykonos', 'name_th' => 'ไมโคนอส', 'is_popular' => true],
            ['name_en' => 'Crete', 'name_th' => 'คริต', 'is_popular' => true],
            ['name_en' => 'Rhodes', 'name_th' => 'โรดส์'],
            ['name_en' => 'Corfu', 'name_th' => 'คอร์ฟู'],
            ['name_en' => 'Thessaloniki', 'name_th' => 'เทสซาโลนิกิ'],
            ['name_en' => 'Meteora', 'name_th' => 'เมทีออร่า', 'is_popular' => true],
            ['name_en' => 'Zakynthos', 'name_th' => 'ซากินโทส'],
            ['name_en' => 'Delphi', 'name_th' => 'เดลไฟ'],
            ['name_en' => 'Olympia', 'name_th' => 'โอลิมเปีย'],
        ];
    }

    // ===== เมืองในโครเอเชีย =====
    private function getCroatiaCities(): array
    {
        return [
            ['name_en' => 'Dubrovnik', 'name_th' => 'ดูบรอฟนิก', 'is_popular' => true],
            ['name_en' => 'Split', 'name_th' => 'สปลิท', 'is_popular' => true],
            ['name_en' => 'Zagreb', 'name_th' => 'ซาเกร็บ'],
            ['name_en' => 'Plitvice Lakes', 'name_th' => 'ทะเลสาบพลิทวีเซ', 'is_popular' => true],
            ['name_en' => 'Hvar', 'name_th' => 'ฮวาร์'],
            ['name_en' => 'Rovinj', 'name_th' => 'โรวินจ์'],
            ['name_en' => 'Zadar', 'name_th' => 'ซาดาร์'],
            ['name_en' => 'Korcula', 'name_th' => 'คอร์คูลา'],
        ];
    }

    // ===== เมืองในสหรัฐอเมริกา =====
    private function getUSACities(): array
    {
        return [
            ['name_en' => 'New York', 'name_th' => 'นิวยอร์ก', 'is_popular' => true],
            ['name_en' => 'Los Angeles', 'name_th' => 'ลอสแอนเจลิส', 'is_popular' => true],
            ['name_en' => 'Las Vegas', 'name_th' => 'ลาสเวกัส', 'is_popular' => true],
            ['name_en' => 'San Francisco', 'name_th' => 'ซานฟรานซิสโก', 'is_popular' => true],
            ['name_en' => 'Miami', 'name_th' => 'ไมอามี', 'is_popular' => true],
            ['name_en' => 'Chicago', 'name_th' => 'ชิคาโก'],
            ['name_en' => 'Washington D.C.', 'name_th' => 'วอชิงตัน ดี.ซี.'],
            ['name_en' => 'Orlando', 'name_th' => 'ออร์แลนโด', 'is_popular' => true],
            ['name_en' => 'Boston', 'name_th' => 'บอสตัน'],
            ['name_en' => 'Seattle', 'name_th' => 'ซีแอตเทิล'],
            ['name_en' => 'Hawaii', 'name_th' => 'ฮาวาย', 'is_popular' => true],
            ['name_en' => 'Honolulu', 'name_th' => 'โฮโนลูลู', 'is_popular' => true],
            ['name_en' => 'Grand Canyon', 'name_th' => 'แกรนด์แคนยอน', 'is_popular' => true],
            ['name_en' => 'Yellowstone', 'name_th' => 'เยลโลว์สโตน'],
            ['name_en' => 'New Orleans', 'name_th' => 'นิวออร์ลีนส์'],
            ['name_en' => 'Nashville', 'name_th' => 'แนชวิลล์'],
            ['name_en' => 'San Diego', 'name_th' => 'ซานดิเอโก'],
            ['name_en' => 'Denver', 'name_th' => 'เดนเวอร์'],
            ['name_en' => 'Atlanta', 'name_th' => 'แอตแลนตา'],
            ['name_en' => 'Dallas', 'name_th' => 'ดัลลัส'],
        ];
    }

    // ===== เมืองในแคนาดา =====
    private function getCanadaCities(): array
    {
        return [
            ['name_en' => 'Toronto', 'name_th' => 'โตรอนโต', 'is_popular' => true],
            ['name_en' => 'Vancouver', 'name_th' => 'แวนคูเวอร์', 'is_popular' => true],
            ['name_en' => 'Montreal', 'name_th' => 'มอนทรีออล'],
            ['name_en' => 'Calgary', 'name_th' => 'แคลกะรี'],
            ['name_en' => 'Ottawa', 'name_th' => 'ออตตาวา'],
            ['name_en' => 'Niagara Falls', 'name_th' => 'น้ำตกไนแองการา', 'is_popular' => true],
            ['name_en' => 'Quebec City', 'name_th' => 'เมืองควิเบก'],
            ['name_en' => 'Banff', 'name_th' => 'แบนฟ์', 'is_popular' => true],
            ['name_en' => 'Jasper', 'name_th' => 'แจสเปอร์'],
            ['name_en' => 'Whistler', 'name_th' => 'วิสต์เลอร์'],
            ['name_en' => 'Victoria', 'name_th' => 'วิกตอเรีย'],
        ];
    }

    // ===== เมืองในเม็กซิโก =====
    private function getMexicoCities(): array
    {
        return [
            ['name_en' => 'Mexico City', 'name_th' => 'เม็กซิโกซิตี้', 'is_popular' => true],
            ['name_en' => 'Cancun', 'name_th' => 'แคนคูน', 'is_popular' => true],
            ['name_en' => 'Playa del Carmen', 'name_th' => 'ปลายาเดลคาร์เมน', 'is_popular' => true],
            ['name_en' => 'Tulum', 'name_th' => 'ตูลุม', 'is_popular' => true],
            ['name_en' => 'Guadalajara', 'name_th' => 'กัวดาลาฮารา'],
            ['name_en' => 'Oaxaca', 'name_th' => 'โออาซากา'],
            ['name_en' => 'San Miguel de Allende', 'name_th' => 'ซานมิเกลเดอยาเลนเด'],
            ['name_en' => 'Los Cabos', 'name_th' => 'ลอสกาบอส'],
            ['name_en' => 'Puerto Vallarta', 'name_th' => 'ปวยร์โตวายาร์ตา'],
            ['name_en' => 'Chichen Itza', 'name_th' => 'ชิเชนอิตซา'],
        ];
    }

    // ===== เมืองในบราซิล =====
    private function getBrazilCities(): array
    {
        return [
            ['name_en' => 'Rio de Janeiro', 'name_th' => 'ริโอเดอจาเนโร', 'is_popular' => true],
            ['name_en' => 'São Paulo', 'name_th' => 'เซาเปาโล', 'is_popular' => true],
            ['name_en' => 'Iguazu Falls', 'name_th' => 'น้ำตกอิกวาซู', 'is_popular' => true],
            ['name_en' => 'Salvador', 'name_th' => 'ซัลวาดอร์'],
            ['name_en' => 'Florianópolis', 'name_th' => 'ฟลอเรียนโนโปลิส'],
            ['name_en' => 'Manaus', 'name_th' => 'มาเนาส์'],
            ['name_en' => 'Fortaleza', 'name_th' => 'ฟอร์ตาเลซา'],
            ['name_en' => 'Brasília', 'name_th' => 'บราซีเลีย'],
            ['name_en' => 'Recife', 'name_th' => 'เรซีเฟ'],
            ['name_en' => 'Fernando de Noronha', 'name_th' => 'เฟอร์นันโดเดโนโรนยา'],
        ];
    }

    // ===== เมืองในอาร์เจนตินา =====
    private function getArgentinaCities(): array
    {
        return [
            ['name_en' => 'Buenos Aires', 'name_th' => 'บัวโนสไอเรส', 'is_popular' => true],
            ['name_en' => 'Patagonia', 'name_th' => 'ปาตาโกเนีย', 'is_popular' => true],
            ['name_en' => 'Bariloche', 'name_th' => 'บาริโลเช', 'is_popular' => true],
            ['name_en' => 'Mendoza', 'name_th' => 'เมนโดซา'],
            ['name_en' => 'Salta', 'name_th' => 'ซัลตา'],
            ['name_en' => 'Ushuaia', 'name_th' => 'อุสวายา', 'is_popular' => true],
            ['name_en' => 'Iguazú Falls (AR)', 'name_th' => 'น้ำตกอิกวาซู (AR)'],
            ['name_en' => 'El Calafate', 'name_th' => 'เอลกาลาฟาเต'],
        ];
    }

    // ===== เมืองในเปรู =====
    private function getPeruCities(): array
    {
        return [
            ['name_en' => 'Lima', 'name_th' => 'ลิมา', 'is_popular' => true],
            ['name_en' => 'Cusco', 'name_th' => 'กุสโก', 'is_popular' => true],
            ['name_en' => 'Machu Picchu', 'name_th' => 'มาชูปิกชู', 'is_popular' => true],
            ['name_en' => 'Arequipa', 'name_th' => 'อาเรกีปา'],
            ['name_en' => 'Lake Titicaca', 'name_th' => 'ทะเลสาบติติกากา'],
            ['name_en' => 'Iquitos', 'name_th' => 'อิกีตอส'],
            ['name_en' => 'Nazca', 'name_th' => 'นาซกา'],
            ['name_en' => 'Sacred Valley', 'name_th' => 'วาลเลย์ศักดิ์สิทธิ์'],
        ];
    }

    // ===== เมืองในอียิปต์ =====
    private function getEgyptCities(): array
    {
        return [
            ['name_en' => 'Cairo', 'name_th' => 'ไคโร', 'is_popular' => true],
            ['name_en' => 'Luxor', 'name_th' => 'ลักซอร์', 'is_popular' => true],
            ['name_en' => 'Aswan', 'name_th' => 'อัสวาน', 'is_popular' => true],
            ['name_en' => 'Alexandria', 'name_th' => 'อเล็กซานเดรีย'],
            ['name_en' => 'Sharm El Sheikh', 'name_th' => 'ชาร์มเอลชีค', 'is_popular' => true],
            ['name_en' => 'Hurghada', 'name_th' => 'ฮูร์กาดา', 'is_popular' => true],
            ['name_en' => 'Giza', 'name_th' => 'กีซา', 'is_popular' => true],
            ['name_en' => 'Dahab', 'name_th' => 'ดาฮับ'],
            ['name_en' => 'Siwa Oasis', 'name_th' => 'โอเอซิสซิวา'],
            ['name_en' => 'Abu Simbel', 'name_th' => 'อาบูซิมเบล'],
        ];
    }

    // ===== เมืองในโมร็อกโก =====
    private function getMoroccoCities(): array
    {
        return [
            ['name_en' => 'Marrakech', 'name_th' => 'มาร์ราเคช', 'is_popular' => true],
            ['name_en' => 'Casablanca', 'name_th' => 'คาซาบลังกา', 'is_popular' => true],
            ['name_en' => 'Fez', 'name_th' => 'เฟส', 'is_popular' => true],
            ['name_en' => 'Rabat', 'name_th' => 'รอบัต'],
            ['name_en' => 'Chefchaouen', 'name_th' => 'เชฟชาอูเอน', 'is_popular' => true],
            ['name_en' => 'Sahara Desert', 'name_th' => 'ทะเลทรายซาฮารา', 'is_popular' => true],
            ['name_en' => 'Essaouira', 'name_th' => 'เอสซาอุอิรา'],
            ['name_en' => 'Agadir', 'name_th' => 'อากาดีร์'],
            ['name_en' => 'Ouarzazate', 'name_th' => 'วาร์ซาซาต'],
            ['name_en' => 'Tangier', 'name_th' => 'แทนเจียร์'],
        ];
    }

    // ===== เมืองในแอฟริกาใต้ =====
    private function getSouthAfricaCities(): array
    {
        return [
            ['name_en' => 'Cape Town', 'name_th' => 'เคปทาวน์', 'is_popular' => true],
            ['name_en' => 'Johannesburg', 'name_th' => 'โจฮันเนสเบิร์ก', 'is_popular' => true],
            ['name_en' => 'Durban', 'name_th' => 'เดอร์บัน'],
            ['name_en' => 'Pretoria', 'name_th' => 'พริทอเรีย'],
            ['name_en' => 'Kruger National Park', 'name_th' => 'อุทยานแห่งชาติครูเกอร์', 'is_popular' => true],
            ['name_en' => 'Garden Route', 'name_th' => 'การ์เดนรูท'],
            ['name_en' => 'Stellenbosch', 'name_th' => 'สเตลเลนบอส'],
            ['name_en' => 'Port Elizabeth', 'name_th' => 'พอร์ตเอลิซาเบธ'],
            ['name_en' => 'Knysna', 'name_th' => 'ไนซนา'],
            ['name_en' => 'Drakensberg', 'name_th' => 'ดราเคนสเบิร์ก'],
        ];
    }

    // ===== เมืองในเคนยา =====
    private function getKenyaCities(): array
    {
        return [
            ['name_en' => 'Nairobi', 'name_th' => 'ไนโรบี', 'is_popular' => true],
            ['name_en' => 'Masai Mara', 'name_th' => 'มาไซมารา', 'is_popular' => true],
            ['name_en' => 'Mombasa', 'name_th' => 'มอมบาซา'],
            ['name_en' => 'Amboseli', 'name_th' => 'อัมโบเซลี'],
            ['name_en' => 'Samburu', 'name_th' => 'ซัมบูรู'],
            ['name_en' => 'Lake Nakuru', 'name_th' => 'ทะเลสาบนากูรู'],
            ['name_en' => 'Tsavo', 'name_th' => 'ทซาโว'],
            ['name_en' => 'Diani Beach', 'name_th' => 'ดายานีบีช'],
            ['name_en' => 'Mount Kenya', 'name_th' => 'ภูเขาเคนยา'],
        ];
    }

    // ===== เมืองในแทนซาเนีย =====
    private function getTanzaniaCities(): array
    {
        return [
            ['name_en' => 'Dar es Salaam', 'name_th' => 'ดาร์เอสซาลาม', 'is_popular' => true],
            ['name_en' => 'Serengeti', 'name_th' => 'เซเรนเกตี', 'is_popular' => true],
            ['name_en' => 'Ngorongoro', 'name_th' => 'กอรองโกโร', 'is_popular' => true],
            ['name_en' => 'Zanzibar', 'name_th' => 'แซนซิบาร์', 'is_popular' => true],
            ['name_en' => 'Mount Kilimanjaro', 'name_th' => 'ภูเขาคิลิมันจาโร', 'is_popular' => true],
            ['name_en' => 'Arusha', 'name_th' => 'อารูชา'],
            ['name_en' => 'Stone Town', 'name_th' => 'สโตนทาวน์'],
            ['name_en' => 'Tarangire', 'name_th' => 'ตารังกิเร'],
        ];
    }

    // ===== เมืองในออสเตรเลีย =====
    private function getAustraliaCities(): array
    {
        return [
            ['name_en' => 'Sydney', 'name_th' => 'ซิดนีย์', 'is_popular' => true],
            ['name_en' => 'Melbourne', 'name_th' => 'เมลเบิร์น', 'is_popular' => true],
            ['name_en' => 'Brisbane', 'name_th' => 'บริสเบน'],
            ['name_en' => 'Perth', 'name_th' => 'เพิร์ท'],
            ['name_en' => 'Adelaide', 'name_th' => 'แอดิเลด'],
            ['name_en' => 'Gold Coast', 'name_th' => 'โกลด์โคสต์', 'is_popular' => true],
            ['name_en' => 'Cairns', 'name_th' => 'แคนส์', 'is_popular' => true],
            ['name_en' => 'Great Barrier Reef', 'name_th' => 'แนวปะการังกั้นเกรตแบร์ริเออร์', 'is_popular' => true],
            ['name_en' => 'Uluru', 'name_th' => 'อูลูรู', 'is_popular' => true],
            ['name_en' => 'Hobart', 'name_th' => 'โฮบาร์ต'],
            ['name_en' => 'Darwin', 'name_th' => 'ดาร์วิน'],
            ['name_en' => 'Great Ocean Road', 'name_th' => 'เกรตโอเชียนโร้ด', 'is_popular' => true],
            ['name_en' => 'Byron Bay', 'name_th' => 'ไบรอนเบย์'],
            ['name_en' => 'Whitsundays', 'name_th' => 'วิตซันเดยส์'],
            ['name_en' => 'Tasmania', 'name_th' => 'แทสเมเนีย'],
        ];
    }

    // ===== เมืองในนิวซีแลนด์ =====
    private function getNewZealandCities(): array
    {
        return [
            ['name_en' => 'Auckland', 'name_th' => 'โอคแลนด์', 'is_popular' => true],
            ['name_en' => 'Queenstown', 'name_th' => 'ควีนส์ทาวน์', 'is_popular' => true],
            ['name_en' => 'Wellington', 'name_th' => 'เวลลิงตัน'],
            ['name_en' => 'Christchurch', 'name_th' => 'ไครสต์เชิร์ช'],
            ['name_en' => 'Rotorua', 'name_th' => 'โรโตรัว', 'is_popular' => true],
            ['name_en' => 'Milford Sound', 'name_th' => 'มิลฟอร์ดซาวนด์', 'is_popular' => true],
            ['name_en' => 'Hobbiton', 'name_th' => 'ฮอบบิตัน', 'is_popular' => true],
            ['name_en' => 'Fiordland', 'name_th' => 'ฟยอร์ดแลนด์'],
            ['name_en' => 'Bay of Islands', 'name_th' => 'เบย์ออฟไอส์แลนด์'],
            ['name_en' => 'Franz Josef Glacier', 'name_th' => 'ธารน้ำแข็งฟรานซ์โจเซฟ'],
            ['name_en' => 'Abel Tasman', 'name_th' => 'อาเบลแทสแมน'],
            ['name_en' => 'Wanaka', 'name_th' => 'วานากา'],
        ];
    }
}
