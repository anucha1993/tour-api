<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CitiesWorldSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Starting World Cities Seeder...');
        
        // ===== เอเชียตะวันออกเฉียงเหนือ =====
        $this->seedCities(7, $this->getMongoliaCities());     // Mongolia
        
        // ===== เอเชียตะวันออกเฉียงใต้ =====
        $this->seedCities(15, $this->getLaosCities());        // Laos
        $this->seedCities(16, $this->getCambodiaCities());    // Cambodia
        $this->seedCities(17, $this->getBruneiCities());      // Brunei
        $this->seedCities(14, $this->getMyanmarCities());     // Myanmar
        
        // ===== เอเชียใต้ =====
        $this->seedCities(20, $this->getSriLankaCities());    // Sri Lanka
        $this->seedCities(21, $this->getNepalCities());       // Nepal
        $this->seedCities(22, $this->getBhutanCities());      // Bhutan
        $this->seedCities(25, $this->getMaldivesCities());    // Maldives
        
        // ===== ตะวันออกกลาง =====
        $this->seedCities(32, $this->getUAECities());         // UAE
        $this->seedCities(33, $this->getSaudiCities());       // Saudi Arabia
        $this->seedCities(34, $this->getQatarCities());       // Qatar
        $this->seedCities(38, $this->getJordanCities());      // Jordan
        $this->seedCities(39, $this->getIsraelCities());      // Israel
        $this->seedCities(41, $this->getTurkeyCities());      // Turkey
        
        // ===== ยุโรปตะวันตก =====
        $this->seedCities(50, $this->getUKCities());          // United Kingdom
        $this->seedCities(51, $this->getFranceCities());      // France
        $this->seedCities(52, $this->getGermanyCities());     // Germany
        $this->seedCities(53, $this->getItalyCities());       // Italy
        $this->seedCities(54, $this->getSpainCities());       // Spain
        $this->seedCities(55, $this->getPortugalCities());    // Portugal
        $this->seedCities(56, $this->getNetherlandsCities()); // Netherlands
        $this->seedCities(57, $this->getBelgiumCities());     // Belgium
        $this->seedCities(59, $this->getSwitzerlandCities()); // Switzerland
        $this->seedCities(60, $this->getAustriaCities());     // Austria
        $this->seedCities(61, $this->getIrelandCities());     // Ireland
        
        // ===== ยุโรปเหนือ =====
        $this->seedCities(65, $this->getSwedenCities());      // Sweden
        $this->seedCities(66, $this->getNorwayCities());      // Norway
        $this->seedCities(67, $this->getDenmarkCities());     // Denmark
        $this->seedCities(68, $this->getFinlandCities());     // Finland
        $this->seedCities(69, $this->getIcelandCities());     // Iceland
        
        // ===== ยุโรปตะวันออก =====
        $this->seedCities(73, $this->getRussiaCities());      // Russia
        $this->seedCities(74, $this->getPolandCities());      // Poland
        $this->seedCities(75, $this->getCzechCities());       // Czech Republic
        $this->seedCities(77, $this->getHungaryCities());     // Hungary
        $this->seedCities(83, $this->getGreeceCities());      // Greece
        $this->seedCities(84, $this->getCroatiaCities());     // Croatia
        
        // ===== อเมริกาเหนือ =====
        $this->seedCities(95, $this->getUSACities());         // USA
        $this->seedCities(96, $this->getCanadaCities());      // Canada
        $this->seedCities(97, $this->getMexicoCities());      // Mexico
        
        // ===== อเมริกาใต้ =====
        $this->seedCities(119, $this->getBrazilCities());     // Brazil
        $this->seedCities(120, $this->getArgentinaCities());  // Argentina
        $this->seedCities(121, $this->getChileCities());      // Chile
        $this->seedCities(122, $this->getPeruCities());       // Peru
        
        // ===== แอฟริกา =====
        $this->seedCities(132, $this->getEgyptCities());      // Egypt
        $this->seedCities(133, $this->getMoroccoCities());    // Morocco
        $this->seedCities(138, $this->getKenyaCities());      // Kenya
        $this->seedCities(177, $this->getSouthAfricaCities()); // South Africa
        
        // ===== โอเชียเนีย =====
        $this->seedCities(186, $this->getAustraliaCities());  // Australia
        $this->seedCities(187, $this->getNewZealandCities()); // New Zealand
        $this->seedCities(188, $this->getFijiCities());       // Fiji
        
        $this->command->info('World Cities Seeder completed!');
    }
    
    private function seedCities(int $countryId, array $cities): void
    {
        $country = DB::table('countries')->where('id', $countryId)->first();
        if (!$country) {
            $this->command->warn("Country ID {$countryId} not found, skipping...");
            return;
        }
        
        $this->command->info("Seeding cities for {$country->name_en} (ID: {$countryId})...");
        
        $existingCities = DB::table('cities')
            ->where('country_id', $countryId)
            ->pluck('name_en')
            ->map(fn($name) => strtolower(trim($name)))
            ->toArray();
        
        $existingSlugs = DB::table('cities')->pluck('slug')->toArray();
        
        $inserted = 0;
        $updated = 0;
        
        foreach ($cities as $city) {
            $nameEnLower = strtolower(trim($city['name_en']));
            
            if (in_array($nameEnLower, $existingCities)) {
                // Update name_th if missing
                $affected = DB::table('cities')
                    ->where('country_id', $countryId)
                    ->whereRaw('LOWER(name_en) = ?', [$nameEnLower])
                    ->where(function($q) {
                        $q->whereNull('name_th')->orWhere('name_th', '');
                    })
                    ->update(['name_th' => $city['name_th']]);
                if ($affected > 0) $updated++;
                continue;
            }
            
            $baseSlug = Str::slug($city['name_en']);
            $slug = $baseSlug;
            
            if (in_array($slug, $existingSlugs)) {
                $slug = $baseSlug . '-' . strtolower($country->iso2 ?? $countryId);
            }
            
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
                // Skip
            }
        }
        
        $this->command->info("  → Inserted: {$inserted}, Updated: {$updated}");
    }
    
    // ===== MONGOLIA =====
    private function getMongoliaCities(): array
    {
        return [
            ['name_en' => 'Ulaanbaatar', 'name_th' => 'อูลานบาตอร์', 'is_popular' => true],
            ['name_en' => 'Erdenet', 'name_th' => 'เอร์เดเนต'],
            ['name_en' => 'Darkhan', 'name_th' => 'ดาร์คาน'],
            ['name_en' => 'Gobi Desert', 'name_th' => 'ทะเลทรายโกบี', 'is_popular' => true],
            ['name_en' => 'Kharkhorin', 'name_th' => 'คาร์โคริน'],
            ['name_en' => 'Terelj', 'name_th' => 'เทเรลจ์'],
        ];
    }
    
    // ===== LAOS =====
    private function getLaosCities(): array
    {
        return [
            ['name_en' => 'Vientiane', 'name_th' => 'เวียงจันทน์', 'is_popular' => true],
            ['name_en' => 'Luang Prabang', 'name_th' => 'หลวงพระบาง', 'is_popular' => true],
            ['name_en' => 'Pakse', 'name_th' => 'ปากเซ'],
            ['name_en' => 'Savannakhet', 'name_th' => 'สะหวันนะเขต'],
            ['name_en' => 'Vang Vieng', 'name_th' => 'วังเวียง', 'is_popular' => true],
            ['name_en' => 'Champasak', 'name_th' => 'จำปาศักดิ์'],
            ['name_en' => 'Luang Namtha', 'name_th' => 'หลวงน้ำทา'],
            ['name_en' => 'Phongsali', 'name_th' => 'พงสาลี'],
            ['name_en' => 'Xieng Khouang', 'name_th' => 'เชียงขวาง'],
            ['name_en' => 'Khammouane', 'name_th' => 'คำม่วน'],
        ];
    }
    
    // ===== CAMBODIA =====
    private function getCambodiaCities(): array
    {
        return [
            ['name_en' => 'Phnom Penh', 'name_th' => 'พนมเปญ', 'is_popular' => true],
            ['name_en' => 'Siem Reap', 'name_th' => 'เสียมเรียบ', 'is_popular' => true],
            ['name_en' => 'Angkor Wat', 'name_th' => 'นครวัด', 'is_popular' => true],
            ['name_en' => 'Sihanoukville', 'name_th' => 'สีหนุวิลล์', 'is_popular' => true],
            ['name_en' => 'Battambang', 'name_th' => 'พระตะบอง'],
            ['name_en' => 'Kampot', 'name_th' => 'กำปอต'],
            ['name_en' => 'Kep', 'name_th' => 'แกบ'],
            ['name_en' => 'Koh Rong', 'name_th' => 'เกาะรง'],
            ['name_en' => 'Mondulkiri', 'name_th' => 'มณฑลคีรี'],
            ['name_en' => 'Ratanakiri', 'name_th' => 'รัตนคีรี'],
        ];
    }
    
    // ===== BRUNEI =====
    private function getBruneiCities(): array
    {
        return [
            ['name_en' => 'Bandar Seri Begawan', 'name_th' => 'บันดาร์เสรีเบกาวัน', 'is_popular' => true],
            ['name_en' => 'Kuala Belait', 'name_th' => 'กัวลาเบอไลต์'],
            ['name_en' => 'Seria', 'name_th' => 'เซอเรีย'],
            ['name_en' => 'Tutong', 'name_th' => 'ตูตง'],
            ['name_en' => 'Temburong', 'name_th' => 'เทมบูรง'],
        ];
    }
    
    // ===== MYANMAR =====
    private function getMyanmarCities(): array
    {
        return [
            ['name_en' => 'Yangon', 'name_th' => 'ย่างกุ้ง', 'is_popular' => true],
            ['name_en' => 'Mandalay', 'name_th' => 'มัณฑะเลย์', 'is_popular' => true],
            ['name_en' => 'Bagan', 'name_th' => 'พุกาม', 'is_popular' => true],
            ['name_en' => 'Naypyidaw', 'name_th' => 'เนปยีดอ'],
            ['name_en' => 'Inle Lake', 'name_th' => 'ทะเลสาบอินเล', 'is_popular' => true],
            ['name_en' => 'Ngapali Beach', 'name_th' => 'หาดงาปาลี'],
            ['name_en' => 'Kyaiktiyo', 'name_th' => 'พระธาตุอินทร์แขวน', 'is_popular' => true],
            ['name_en' => 'Mawlamyine', 'name_th' => 'มะละแหม่ง'],
            ['name_en' => 'Taunggyi', 'name_th' => 'ตองยี'],
            ['name_en' => 'Hpa-An', 'name_th' => 'พะอัน'],
            ['name_en' => 'Pyin Oo Lwin', 'name_th' => 'ปินอูลวิน'],
            ['name_en' => 'Myitkyina', 'name_th' => 'มิตจีนา'],
        ];
    }
    
    // ===== SRI LANKA =====
    private function getSriLankaCities(): array
    {
        return [
            ['name_en' => 'Colombo', 'name_th' => 'โคลัมโบ', 'is_popular' => true],
            ['name_en' => 'Kandy', 'name_th' => 'แคนดี้', 'is_popular' => true],
            ['name_en' => 'Galle', 'name_th' => 'กอลล์', 'is_popular' => true],
            ['name_en' => 'Sigiriya', 'name_th' => 'ซิกิริยา', 'is_popular' => true],
            ['name_en' => 'Ella', 'name_th' => 'เอลลา', 'is_popular' => true],
            ['name_en' => 'Nuwara Eliya', 'name_th' => 'นูวาระเอลิยา'],
            ['name_en' => 'Bentota', 'name_th' => 'เบนโตตา'],
            ['name_en' => 'Mirissa', 'name_th' => 'มิริสสา'],
            ['name_en' => 'Trincomalee', 'name_th' => 'ตรินโคมาลี'],
            ['name_en' => 'Jaffna', 'name_th' => 'จาฟนา'],
            ['name_en' => 'Anuradhapura', 'name_th' => 'อนุราธปุระ'],
            ['name_en' => 'Polonnaruwa', 'name_th' => 'โปลอนนารุวะ'],
            ['name_en' => 'Dambulla', 'name_th' => 'ดัมบุลลา'],
            ['name_en' => 'Unawatuna', 'name_th' => 'อูนาวาตุนา'],
        ];
    }
    
    // ===== NEPAL =====
    private function getNepalCities(): array
    {
        return [
            ['name_en' => 'Kathmandu', 'name_th' => 'กาฐมาณฑุ', 'is_popular' => true],
            ['name_en' => 'Pokhara', 'name_th' => 'โปขรา', 'is_popular' => true],
            ['name_en' => 'Lumbini', 'name_th' => 'ลุมพินี', 'is_popular' => true],
            ['name_en' => 'Chitwan', 'name_th' => 'จิตวัน'],
            ['name_en' => 'Nagarkot', 'name_th' => 'นากาก็อต'],
            ['name_en' => 'Bhaktapur', 'name_th' => 'ภักตะปูร์'],
            ['name_en' => 'Patan', 'name_th' => 'ปาทัน'],
            ['name_en' => 'Everest Base Camp', 'name_th' => 'เอเวอเรสต์ เบส แคมป์', 'is_popular' => true],
            ['name_en' => 'Annapurna', 'name_th' => 'อันนาปูรณะ'],
            ['name_en' => 'Lukla', 'name_th' => 'ลุกลา'],
        ];
    }
    
    // ===== BHUTAN =====
    private function getBhutanCities(): array
    {
        return [
            ['name_en' => 'Thimphu', 'name_th' => 'ทิมพู', 'is_popular' => true],
            ['name_en' => 'Paro', 'name_th' => 'พาโร', 'is_popular' => true],
            ['name_en' => 'Punakha', 'name_th' => 'ปูนาคา', 'is_popular' => true],
            ['name_en' => 'Bumthang', 'name_th' => 'บุมทัง'],
            ['name_en' => 'Wangdue Phodrang', 'name_th' => 'วังดีโพดรัง'],
            ['name_en' => 'Trongsa', 'name_th' => 'ตรองซา'],
            ['name_en' => 'Phobjikha Valley', 'name_th' => 'หุบเขาโพบจิกา'],
            ['name_en' => 'Haa', 'name_th' => 'ฮา'],
        ];
    }
    
    // ===== MALDIVES =====
    private function getMaldivesCities(): array
    {
        return [
            ['name_en' => 'Male', 'name_th' => 'มาเล', 'is_popular' => true],
            ['name_en' => 'Maafushi', 'name_th' => 'มาฟูชิ', 'is_popular' => true],
            ['name_en' => 'Hulhumale', 'name_th' => 'ฮุลฮูมาเล'],
            ['name_en' => 'Baa Atoll', 'name_th' => 'บา อะทอลล์'],
            ['name_en' => 'Ari Atoll', 'name_th' => 'อาริ อะทอลล์', 'is_popular' => true],
            ['name_en' => 'North Male Atoll', 'name_th' => 'นอร์ธ มาเล อะทอลล์'],
            ['name_en' => 'South Male Atoll', 'name_th' => 'เซาท์ มาเล อะทอลล์'],
            ['name_en' => 'Addu Atoll', 'name_th' => 'อัดดู อะทอลล์'],
        ];
    }
    
    // ===== UAE =====
    private function getUAECities(): array
    {
        return [
            ['name_en' => 'Dubai', 'name_th' => 'ดูไบ', 'is_popular' => true],
            ['name_en' => 'Abu Dhabi', 'name_th' => 'อาบูดาบี', 'is_popular' => true],
            ['name_en' => 'Sharjah', 'name_th' => 'ชาร์จาห์'],
            ['name_en' => 'Ajman', 'name_th' => 'อัจมาน'],
            ['name_en' => 'Fujairah', 'name_th' => 'ฟูไจราห์'],
            ['name_en' => 'Ras Al Khaimah', 'name_th' => 'ราส อัล ไคมาห์'],
            ['name_en' => 'Al Ain', 'name_th' => 'อัล อาอิน'],
        ];
    }
    
    // ===== SAUDI ARABIA =====
    private function getSaudiCities(): array
    {
        return [
            ['name_en' => 'Riyadh', 'name_th' => 'ริยาด', 'is_popular' => true],
            ['name_en' => 'Jeddah', 'name_th' => 'เจดดาห์', 'is_popular' => true],
            ['name_en' => 'Mecca', 'name_th' => 'มักกะห์', 'is_popular' => true],
            ['name_en' => 'Medina', 'name_th' => 'เมดินา', 'is_popular' => true],
            ['name_en' => 'Dammam', 'name_th' => 'ดัมมาม'],
            ['name_en' => 'Al Khobar', 'name_th' => 'อัล โคบาร์'],
            ['name_en' => 'Taif', 'name_th' => 'ตาอิฟ'],
            ['name_en' => 'Abha', 'name_th' => 'อับฮา'],
            ['name_en' => 'AlUla', 'name_th' => 'อัล อูลา', 'is_popular' => true],
            ['name_en' => 'Neom', 'name_th' => 'นีออม'],
        ];
    }
    
    // ===== QATAR =====
    private function getQatarCities(): array
    {
        return [
            ['name_en' => 'Doha', 'name_th' => 'โดฮา', 'is_popular' => true],
            ['name_en' => 'Al Wakrah', 'name_th' => 'อัล วาคราห์'],
            ['name_en' => 'Al Khor', 'name_th' => 'อัล คอร์'],
            ['name_en' => 'Lusail', 'name_th' => 'ลูเซล'],
            ['name_en' => 'The Pearl', 'name_th' => 'เดอะ เพิร์ล'],
        ];
    }
    
    // ===== JORDAN =====
    private function getJordanCities(): array
    {
        return [
            ['name_en' => 'Amman', 'name_th' => 'อัมมาน', 'is_popular' => true],
            ['name_en' => 'Petra', 'name_th' => 'เพตรา', 'is_popular' => true],
            ['name_en' => 'Wadi Rum', 'name_th' => 'วาดิรัม', 'is_popular' => true],
            ['name_en' => 'Dead Sea', 'name_th' => 'ทะเลเดดซี', 'is_popular' => true],
            ['name_en' => 'Aqaba', 'name_th' => 'อะกาบา'],
            ['name_en' => 'Jerash', 'name_th' => 'เจราช'],
            ['name_en' => 'Madaba', 'name_th' => 'มาดาบา'],
        ];
    }
    
    // ===== ISRAEL =====
    private function getIsraelCities(): array
    {
        return [
            ['name_en' => 'Jerusalem', 'name_th' => 'เยรูซาเล็ม', 'is_popular' => true],
            ['name_en' => 'Tel Aviv', 'name_th' => 'เทล อาวีฟ', 'is_popular' => true],
            ['name_en' => 'Haifa', 'name_th' => 'ไฮฟา'],
            ['name_en' => 'Eilat', 'name_th' => 'เอลัต'],
            ['name_en' => 'Dead Sea', 'name_th' => 'ทะเลเดดซี', 'is_popular' => true],
            ['name_en' => 'Nazareth', 'name_th' => 'นาซาเร็ธ'],
            ['name_en' => 'Bethlehem', 'name_th' => 'เบธเลเฮม', 'is_popular' => true],
            ['name_en' => 'Tiberias', 'name_th' => 'ทิเบอเรียส'],
        ];
    }
    
    // ===== TURKEY =====
    private function getTurkeyCities(): array
    {
        return [
            ['name_en' => 'Istanbul', 'name_th' => 'อิสตันบูล', 'is_popular' => true],
            ['name_en' => 'Ankara', 'name_th' => 'อังการา'],
            ['name_en' => 'Cappadocia', 'name_th' => 'คัปปาโดเกีย', 'is_popular' => true],
            ['name_en' => 'Antalya', 'name_th' => 'อันตัลยา', 'is_popular' => true],
            ['name_en' => 'Izmir', 'name_th' => 'อิซเมียร์'],
            ['name_en' => 'Bodrum', 'name_th' => 'โบดรุม'],
            ['name_en' => 'Ephesus', 'name_th' => 'เอฟิซุส'],
            ['name_en' => 'Pamukkale', 'name_th' => 'ปามุคคาเล', 'is_popular' => true],
            ['name_en' => 'Konya', 'name_th' => 'คอนยา'],
            ['name_en' => 'Fethiye', 'name_th' => 'เฟตีเย'],
            ['name_en' => 'Marmaris', 'name_th' => 'มาร์มาริส'],
            ['name_en' => 'Kusadasi', 'name_th' => 'คูซาดาสี'],
            ['name_en' => 'Trabzon', 'name_th' => 'ทราบซอน'],
            ['name_en' => 'Bursa', 'name_th' => 'บูร์ซา'],
        ];
    }
    
    // ===== UNITED KINGDOM =====
    private function getUKCities(): array
    {
        return [
            ['name_en' => 'London', 'name_th' => 'ลอนดอน', 'is_popular' => true],
            ['name_en' => 'Edinburgh', 'name_th' => 'เอดินบะระ', 'is_popular' => true],
            ['name_en' => 'Manchester', 'name_th' => 'แมนเชสเตอร์'],
            ['name_en' => 'Liverpool', 'name_th' => 'ลิเวอร์พูล'],
            ['name_en' => 'Birmingham', 'name_th' => 'เบอร์มิงแฮม'],
            ['name_en' => 'Glasgow', 'name_th' => 'กลาสโกว์'],
            ['name_en' => 'Oxford', 'name_th' => 'อ็อกซ์ฟอร์ด', 'is_popular' => true],
            ['name_en' => 'Cambridge', 'name_th' => 'เคมบริดจ์', 'is_popular' => true],
            ['name_en' => 'Bath', 'name_th' => 'บาธ'],
            ['name_en' => 'Stonehenge', 'name_th' => 'สโตนเฮนจ์', 'is_popular' => true],
            ['name_en' => 'York', 'name_th' => 'ยอร์ก'],
            ['name_en' => 'Bristol', 'name_th' => 'บริสตอล'],
            ['name_en' => 'Cardiff', 'name_th' => 'คาร์ดิฟฟ์'],
            ['name_en' => 'Belfast', 'name_th' => 'เบลฟาสต์'],
            ['name_en' => 'Brighton', 'name_th' => 'ไบรตัน'],
            ['name_en' => 'Leeds', 'name_th' => 'ลีดส์'],
            ['name_en' => 'Inverness', 'name_th' => 'อินเวอร์เนส'],
            ['name_en' => 'Windsor', 'name_th' => 'วินด์เซอร์'],
            ['name_en' => 'Cotswolds', 'name_th' => 'คอตส์โวลด์'],
            ['name_en' => 'Lake District', 'name_th' => 'เลค ดิสทริกต์'],
        ];
    }
    
    // ===== FRANCE =====
    private function getFranceCities(): array
    {
        return [
            ['name_en' => 'Paris', 'name_th' => 'ปารีส', 'is_popular' => true],
            ['name_en' => 'Nice', 'name_th' => 'นีซ', 'is_popular' => true],
            ['name_en' => 'Lyon', 'name_th' => 'ลียง'],
            ['name_en' => 'Marseille', 'name_th' => 'มาร์เซย์'],
            ['name_en' => 'Bordeaux', 'name_th' => 'บอร์โดซ์'],
            ['name_en' => 'Strasbourg', 'name_th' => 'สตราสบูร์ก'],
            ['name_en' => 'Cannes', 'name_th' => 'คานส์', 'is_popular' => true],
            ['name_en' => 'Monaco', 'name_th' => 'โมนาโก'],
            ['name_en' => 'Versailles', 'name_th' => 'แวร์ซาย', 'is_popular' => true],
            ['name_en' => 'Avignon', 'name_th' => 'อาวีญง'],
            ['name_en' => 'Toulouse', 'name_th' => 'ตูลูส'],
            ['name_en' => 'Nantes', 'name_th' => 'น็องต์'],
            ['name_en' => 'Montpellier', 'name_th' => 'มงต์เปอลีเย'],
            ['name_en' => 'Provence', 'name_th' => 'โพรวองซ์', 'is_popular' => true],
            ['name_en' => 'Mont Saint-Michel', 'name_th' => 'มงต์ แซงต์-มิเชล', 'is_popular' => true],
            ['name_en' => 'Chamonix', 'name_th' => 'ชาโมนีซ์'],
            ['name_en' => 'Dijon', 'name_th' => 'ดิฌง'],
            ['name_en' => 'Lille', 'name_th' => 'ลีล'],
        ];
    }
    
    // ===== GERMANY =====
    private function getGermanyCities(): array
    {
        return [
            ['name_en' => 'Berlin', 'name_th' => 'เบอร์ลิน', 'is_popular' => true],
            ['name_en' => 'Munich', 'name_th' => 'มิวนิก', 'is_popular' => true],
            ['name_en' => 'Frankfurt', 'name_th' => 'แฟรงก์เฟิร์ต', 'is_popular' => true],
            ['name_en' => 'Hamburg', 'name_th' => 'ฮัมบูร์ก'],
            ['name_en' => 'Cologne', 'name_th' => 'โคโลญ'],
            ['name_en' => 'Dusseldorf', 'name_th' => 'ดุสเซลดอร์ฟ'],
            ['name_en' => 'Stuttgart', 'name_th' => 'ชตุทท์การ์ท'],
            ['name_en' => 'Heidelberg', 'name_th' => 'ไฮเดลแบร์ก'],
            ['name_en' => 'Nuremberg', 'name_th' => 'นูเรมเบิร์ก'],
            ['name_en' => 'Dresden', 'name_th' => 'เดรสเดน'],
            ['name_en' => 'Leipzig', 'name_th' => 'ไลป์ซิก'],
            ['name_en' => 'Rothenburg', 'name_th' => 'โรเธนบูร์ก'],
            ['name_en' => 'Neuschwanstein', 'name_th' => 'นอยชวานสไตน์', 'is_popular' => true],
            ['name_en' => 'Black Forest', 'name_th' => 'แบล็ก ฟอเรสต์'],
            ['name_en' => 'Bavarian Alps', 'name_th' => 'เทือกเขาแอลป์บาวาเรีย'],
            ['name_en' => 'Rhine Valley', 'name_th' => 'หุบเขาไรน์'],
        ];
    }
    
    // ===== ITALY =====
    private function getItalyCities(): array
    {
        return [
            ['name_en' => 'Rome', 'name_th' => 'โรม', 'is_popular' => true],
            ['name_en' => 'Venice', 'name_th' => 'เวนิส', 'is_popular' => true],
            ['name_en' => 'Florence', 'name_th' => 'ฟลอเรนซ์', 'is_popular' => true],
            ['name_en' => 'Milan', 'name_th' => 'มิลาน', 'is_popular' => true],
            ['name_en' => 'Naples', 'name_th' => 'เนเปิลส์'],
            ['name_en' => 'Pisa', 'name_th' => 'ปิซา', 'is_popular' => true],
            ['name_en' => 'Amalfi Coast', 'name_th' => 'อมาลฟี โคสต์', 'is_popular' => true],
            ['name_en' => 'Cinque Terre', 'name_th' => 'ชิงเกว แตร์เร', 'is_popular' => true],
            ['name_en' => 'Vatican City', 'name_th' => 'นครวาติกัน', 'is_popular' => true],
            ['name_en' => 'Verona', 'name_th' => 'เวโรนา'],
            ['name_en' => 'Bologna', 'name_th' => 'โบโลญญา'],
            ['name_en' => 'Turin', 'name_th' => 'ตูริน'],
            ['name_en' => 'Siena', 'name_th' => 'เซียนา'],
            ['name_en' => 'Capri', 'name_th' => 'คาปรี'],
            ['name_en' => 'Pompeii', 'name_th' => 'ปอมเปอี', 'is_popular' => true],
            ['name_en' => 'Lake Como', 'name_th' => 'ทะเลสาบโคโม'],
            ['name_en' => 'Sicily', 'name_th' => 'ซิซิลี'],
            ['name_en' => 'Sardinia', 'name_th' => 'ซาร์ดิเนีย'],
            ['name_en' => 'Positano', 'name_th' => 'โพซิตาโน'],
            ['name_en' => 'Sorrento', 'name_th' => 'ซอร์เรนโต'],
        ];
    }
    
    // ===== SPAIN =====
    private function getSpainCities(): array
    {
        return [
            ['name_en' => 'Barcelona', 'name_th' => 'บาร์เซโลนา', 'is_popular' => true],
            ['name_en' => 'Madrid', 'name_th' => 'มาดริด', 'is_popular' => true],
            ['name_en' => 'Seville', 'name_th' => 'เซบีญา', 'is_popular' => true],
            ['name_en' => 'Granada', 'name_th' => 'กรานาดา', 'is_popular' => true],
            ['name_en' => 'Valencia', 'name_th' => 'บาเลนเซีย'],
            ['name_en' => 'Malaga', 'name_th' => 'มาลากา'],
            ['name_en' => 'Bilbao', 'name_th' => 'บิลเบา'],
            ['name_en' => 'San Sebastian', 'name_th' => 'ซาน เซบาสเตียน'],
            ['name_en' => 'Toledo', 'name_th' => 'โตเลโด'],
            ['name_en' => 'Cordoba', 'name_th' => 'กอร์โดบา'],
            ['name_en' => 'Ibiza', 'name_th' => 'อีบิซา', 'is_popular' => true],
            ['name_en' => 'Mallorca', 'name_th' => 'มายอร์กา'],
            ['name_en' => 'Tenerife', 'name_th' => 'เทเนริเฟ'],
            ['name_en' => 'Costa Brava', 'name_th' => 'คอสตา บราวา'],
            ['name_en' => 'Santiago de Compostela', 'name_th' => 'ซานติอาโก เด กอมโปสเตลา'],
        ];
    }
    
    // ===== PORTUGAL =====
    private function getPortugalCities(): array
    {
        return [
            ['name_en' => 'Lisbon', 'name_th' => 'ลิสบอน', 'is_popular' => true],
            ['name_en' => 'Porto', 'name_th' => 'ปอร์โต', 'is_popular' => true],
            ['name_en' => 'Sintra', 'name_th' => 'ซินตรา', 'is_popular' => true],
            ['name_en' => 'Faro', 'name_th' => 'ฟาโร'],
            ['name_en' => 'Algarve', 'name_th' => 'อัลการ์ฟ'],
            ['name_en' => 'Madeira', 'name_th' => 'มาเดรา'],
            ['name_en' => 'Coimbra', 'name_th' => 'โคอิมบรา'],
            ['name_en' => 'Evora', 'name_th' => 'เอโวรา'],
            ['name_en' => 'Cascais', 'name_th' => 'กัชไกช์'],
            ['name_en' => 'Azores', 'name_th' => 'อะโซร์ส'],
        ];
    }
    
    // ===== NETHERLANDS =====
    private function getNetherlandsCities(): array
    {
        return [
            ['name_en' => 'Amsterdam', 'name_th' => 'อัมสเตอร์ดัม', 'is_popular' => true],
            ['name_en' => 'Rotterdam', 'name_th' => 'รอตเทอร์ดัม'],
            ['name_en' => 'The Hague', 'name_th' => 'เดอะ เฮก'],
            ['name_en' => 'Utrecht', 'name_th' => 'อูเทรกต์'],
            ['name_en' => 'Delft', 'name_th' => 'เดลฟท์'],
            ['name_en' => 'Leiden', 'name_th' => 'ไลเดน'],
            ['name_en' => 'Haarlem', 'name_th' => 'ฮาร์เล็ม'],
            ['name_en' => 'Maastricht', 'name_th' => 'มาสทริกต์'],
            ['name_en' => 'Keukenhof', 'name_th' => 'เคอเคนฮอฟ', 'is_popular' => true],
            ['name_en' => 'Giethoorn', 'name_th' => 'กีธอร์น'],
            ['name_en' => 'Kinderdijk', 'name_th' => 'คินเดอร์ไดค์'],
        ];
    }
    
    // ===== BELGIUM =====
    private function getBelgiumCities(): array
    {
        return [
            ['name_en' => 'Brussels', 'name_th' => 'บรัสเซลส์', 'is_popular' => true],
            ['name_en' => 'Bruges', 'name_th' => 'บรูจส์', 'is_popular' => true],
            ['name_en' => 'Ghent', 'name_th' => 'เกนท์'],
            ['name_en' => 'Antwerp', 'name_th' => 'แอนต์เวิร์ป'],
            ['name_en' => 'Leuven', 'name_th' => 'เลอเวน'],
            ['name_en' => 'Liege', 'name_th' => 'ลีแอช'],
            ['name_en' => 'Dinant', 'name_th' => 'ดีน็อง'],
        ];
    }
    
    // ===== SWITZERLAND =====
    private function getSwitzerlandCities(): array
    {
        return [
            ['name_en' => 'Zurich', 'name_th' => 'ซูริค', 'is_popular' => true],
            ['name_en' => 'Geneva', 'name_th' => 'เจนีวา', 'is_popular' => true],
            ['name_en' => 'Lucerne', 'name_th' => 'ลูเซิร์น', 'is_popular' => true],
            ['name_en' => 'Interlaken', 'name_th' => 'อินเทอร์ลาเคน', 'is_popular' => true],
            ['name_en' => 'Bern', 'name_th' => 'เบิร์น'],
            ['name_en' => 'Zermatt', 'name_th' => 'เซอร์แมทท์', 'is_popular' => true],
            ['name_en' => 'Basel', 'name_th' => 'บาเซิล'],
            ['name_en' => 'Lausanne', 'name_th' => 'โลซาน'],
            ['name_en' => 'Grindelwald', 'name_th' => 'กรินเดลวาลด์', 'is_popular' => true],
            ['name_en' => 'Jungfrau', 'name_th' => 'ยุงเฟรา', 'is_popular' => true],
            ['name_en' => 'Matterhorn', 'name_th' => 'แมทเทอร์ฮอร์น'],
            ['name_en' => 'St. Moritz', 'name_th' => 'เซนต์ มอริตซ์'],
            ['name_en' => 'Montreux', 'name_th' => 'มองเทรอซ์'],
            ['name_en' => 'Engelberg', 'name_th' => 'เองเงิลแบร์ก'],
        ];
    }
    
    // ===== AUSTRIA =====
    private function getAustriaCities(): array
    {
        return [
            ['name_en' => 'Vienna', 'name_th' => 'เวียนนา', 'is_popular' => true],
            ['name_en' => 'Salzburg', 'name_th' => 'ซาลซ์บูร์ก', 'is_popular' => true],
            ['name_en' => 'Innsbruck', 'name_th' => 'อินส์บรุค', 'is_popular' => true],
            ['name_en' => 'Hallstatt', 'name_th' => 'ฮัลล์ชตัทท์', 'is_popular' => true],
            ['name_en' => 'Graz', 'name_th' => 'กราซ'],
            ['name_en' => 'Linz', 'name_th' => 'ลินซ์'],
            ['name_en' => 'Klagenfurt', 'name_th' => 'คลาเกนฟูร์ท'],
            ['name_en' => 'Zell am See', 'name_th' => 'เซลล์ อัม เซ'],
            ['name_en' => 'St. Anton', 'name_th' => 'เซนต์ อันตอน'],
        ];
    }
    
    // ===== IRELAND =====
    private function getIrelandCities(): array
    {
        return [
            ['name_en' => 'Dublin', 'name_th' => 'ดับลิน', 'is_popular' => true],
            ['name_en' => 'Cork', 'name_th' => 'คอร์ก'],
            ['name_en' => 'Galway', 'name_th' => 'กัลเวย์'],
            ['name_en' => 'Limerick', 'name_th' => 'ลิเมอริก'],
            ['name_en' => 'Killarney', 'name_th' => 'คิลลาร์นีย์'],
            ['name_en' => 'Cliffs of Moher', 'name_th' => 'คลิฟฟ์ส ออฟ โมเฮอร์', 'is_popular' => true],
            ['name_en' => 'Ring of Kerry', 'name_th' => 'ริง ออฟ เคอร์รี'],
            ['name_en' => 'Belfast', 'name_th' => 'เบลฟาสต์'],
            ['name_en' => 'Giants Causeway', 'name_th' => 'ไจแอนท์ส คอสเวย์'],
        ];
    }
    
    // ===== SWEDEN =====
    private function getSwedenCities(): array
    {
        return [
            ['name_en' => 'Stockholm', 'name_th' => 'สตอกโฮล์ม', 'is_popular' => true],
            ['name_en' => 'Gothenburg', 'name_th' => 'โกเธนเบิร์ก'],
            ['name_en' => 'Malmo', 'name_th' => 'มัลเมอ'],
            ['name_en' => 'Uppsala', 'name_th' => 'อุปซอลา'],
            ['name_en' => 'Kiruna', 'name_th' => 'คิรูนา', 'is_popular' => true],
            ['name_en' => 'Abisko', 'name_th' => 'อาบิสโก'],
            ['name_en' => 'Visby', 'name_th' => 'วิสบี'],
            ['name_en' => 'Lapland', 'name_th' => 'แลปแลนด์', 'is_popular' => true],
        ];
    }
    
    // ===== NORWAY =====
    private function getNorwayCities(): array
    {
        return [
            ['name_en' => 'Oslo', 'name_th' => 'ออสโล', 'is_popular' => true],
            ['name_en' => 'Bergen', 'name_th' => 'แบร์เกน', 'is_popular' => true],
            ['name_en' => 'Tromso', 'name_th' => 'ทรอมโซ', 'is_popular' => true],
            ['name_en' => 'Stavanger', 'name_th' => 'สตาวังเงอร์'],
            ['name_en' => 'Trondheim', 'name_th' => 'ทรอนด์เฮม'],
            ['name_en' => 'Geiranger Fjord', 'name_th' => 'ไกแรงเกอร์ ฟยอร์ด', 'is_popular' => true],
            ['name_en' => 'Flam', 'name_th' => 'ฟลอม'],
            ['name_en' => 'Lofoten Islands', 'name_th' => 'หมู่เกาะโลโฟเทน', 'is_popular' => true],
            ['name_en' => 'Alesund', 'name_th' => 'โอเลซุนด์'],
            ['name_en' => 'Svalbard', 'name_th' => 'สฟาลบาร์ด'],
            ['name_en' => 'Northern Lights', 'name_th' => 'แสงเหนือ'],
        ];
    }
    
    // ===== DENMARK =====
    private function getDenmarkCities(): array
    {
        return [
            ['name_en' => 'Copenhagen', 'name_th' => 'โคเปนเฮเกน', 'is_popular' => true],
            ['name_en' => 'Aarhus', 'name_th' => 'ออร์ฮุส'],
            ['name_en' => 'Odense', 'name_th' => 'โอเดนเซ'],
            ['name_en' => 'Aalborg', 'name_th' => 'โอลบอร์ก'],
            ['name_en' => 'Roskilde', 'name_th' => 'รอสคิลด์'],
            ['name_en' => 'Helsingor', 'name_th' => 'เฮลซิงเออร์'],
            ['name_en' => 'Legoland', 'name_th' => 'เลโก้แลนด์', 'is_popular' => true],
        ];
    }
    
    // ===== FINLAND =====
    private function getFinlandCities(): array
    {
        return [
            ['name_en' => 'Helsinki', 'name_th' => 'เฮลซิงกิ', 'is_popular' => true],
            ['name_en' => 'Rovaniemi', 'name_th' => 'โรวาเนียมี', 'is_popular' => true],
            ['name_en' => 'Turku', 'name_th' => 'ตูร์กู'],
            ['name_en' => 'Tampere', 'name_th' => 'ตัมเปเร'],
            ['name_en' => 'Lapland', 'name_th' => 'แลปแลนด์', 'is_popular' => true],
            ['name_en' => 'Saariselka', 'name_th' => 'ซาริเซลก้า'],
            ['name_en' => 'Levi', 'name_th' => 'เลวี'],
            ['name_en' => 'Santa Claus Village', 'name_th' => 'หมู่บ้านซานตาคลอส', 'is_popular' => true],
        ];
    }
    
    // ===== ICELAND =====
    private function getIcelandCities(): array
    {
        return [
            ['name_en' => 'Reykjavik', 'name_th' => 'เรคยาวิก', 'is_popular' => true],
            ['name_en' => 'Blue Lagoon', 'name_th' => 'บลู ลากูน', 'is_popular' => true],
            ['name_en' => 'Golden Circle', 'name_th' => 'โกลเดน เซอร์เคิล', 'is_popular' => true],
            ['name_en' => 'Vik', 'name_th' => 'วิก'],
            ['name_en' => 'Akureyri', 'name_th' => 'อาคูเรย์รี'],
            ['name_en' => 'Jokulsarlon', 'name_th' => 'โยกุลซาร์ลอน', 'is_popular' => true],
            ['name_en' => 'Skogafoss', 'name_th' => 'สโกกาฟอส'],
            ['name_en' => 'Gullfoss', 'name_th' => 'กุลฟอส'],
            ['name_en' => 'Geysir', 'name_th' => 'เกย์เซอร์'],
            ['name_en' => 'Vatnajokull', 'name_th' => 'วัทนาโยกุล'],
        ];
    }
    
    // ===== RUSSIA =====
    private function getRussiaCities(): array
    {
        return [
            ['name_en' => 'Moscow', 'name_th' => 'มอสโก', 'is_popular' => true],
            ['name_en' => 'St. Petersburg', 'name_th' => 'เซนต์ปีเตอร์สเบิร์ก', 'is_popular' => true],
            ['name_en' => 'Vladivostok', 'name_th' => 'วลาดิวอสตอก'],
            ['name_en' => 'Sochi', 'name_th' => 'โซชิ'],
            ['name_en' => 'Kazan', 'name_th' => 'คาซาน'],
            ['name_en' => 'Novosibirsk', 'name_th' => 'โนโวซีบีร์สค์'],
            ['name_en' => 'Yekaterinburg', 'name_th' => 'เยคาเตรินบูร์ก'],
            ['name_en' => 'Lake Baikal', 'name_th' => 'ทะเลสาบไบคาล', 'is_popular' => true],
            ['name_en' => 'Murmansk', 'name_th' => 'มูร์มันสค์'],
            ['name_en' => 'Kamchatka', 'name_th' => 'คัมชัตกา'],
        ];
    }
    
    // ===== POLAND =====
    private function getPolandCities(): array
    {
        return [
            ['name_en' => 'Warsaw', 'name_th' => 'วอร์ซอ', 'is_popular' => true],
            ['name_en' => 'Krakow', 'name_th' => 'คราคูฟ', 'is_popular' => true],
            ['name_en' => 'Gdansk', 'name_th' => 'กดัญสก์'],
            ['name_en' => 'Wroclaw', 'name_th' => 'วรอตซวาฟ'],
            ['name_en' => 'Poznan', 'name_th' => 'พอซนาน'],
            ['name_en' => 'Zakopane', 'name_th' => 'ซาโคปาเน'],
            ['name_en' => 'Auschwitz', 'name_th' => 'เอาช์วิทซ์', 'is_popular' => true],
            ['name_en' => 'Wieliczka Salt Mine', 'name_th' => 'เหมืองเกลือเวียลิชกา'],
        ];
    }
    
    // ===== CZECH REPUBLIC =====
    private function getCzechCities(): array
    {
        return [
            ['name_en' => 'Prague', 'name_th' => 'ปราก', 'is_popular' => true],
            ['name_en' => 'Cesky Krumlov', 'name_th' => 'เชสกี้ ครุมลอฟ', 'is_popular' => true],
            ['name_en' => 'Brno', 'name_th' => 'เบอร์โน'],
            ['name_en' => 'Karlovy Vary', 'name_th' => 'คาร์โลวี วารี'],
            ['name_en' => 'Kutna Hora', 'name_th' => 'คุตนา โฮรา'],
            ['name_en' => 'Pilsen', 'name_th' => 'พิลเซน'],
            ['name_en' => 'Olomouc', 'name_th' => 'โอโลมูค'],
        ];
    }
    
    // ===== HUNGARY =====
    private function getHungaryCities(): array
    {
        return [
            ['name_en' => 'Budapest', 'name_th' => 'บูดาเปสต์', 'is_popular' => true],
            ['name_en' => 'Lake Balaton', 'name_th' => 'ทะเลสาบบาลาตัน'],
            ['name_en' => 'Eger', 'name_th' => 'เอเกอร์'],
            ['name_en' => 'Debrecen', 'name_th' => 'เดเบรเซน'],
            ['name_en' => 'Pecs', 'name_th' => 'เปช'],
            ['name_en' => 'Szentendre', 'name_th' => 'เซนเทนเดร'],
            ['name_en' => 'Szeged', 'name_th' => 'เซเก็ด'],
        ];
    }
    
    // ===== GREECE =====
    private function getGreeceCities(): array
    {
        return [
            ['name_en' => 'Athens', 'name_th' => 'เอเธนส์', 'is_popular' => true],
            ['name_en' => 'Santorini', 'name_th' => 'ซานโตรีนี', 'is_popular' => true],
            ['name_en' => 'Mykonos', 'name_th' => 'ไมโคนอส', 'is_popular' => true],
            ['name_en' => 'Crete', 'name_th' => 'ครีต'],
            ['name_en' => 'Rhodes', 'name_th' => 'โรดส์'],
            ['name_en' => 'Corfu', 'name_th' => 'คอร์ฟู'],
            ['name_en' => 'Thessaloniki', 'name_th' => 'เทสซาโลนีกี'],
            ['name_en' => 'Delphi', 'name_th' => 'เดลฟี', 'is_popular' => true],
            ['name_en' => 'Meteora', 'name_th' => 'เมเทโอรา', 'is_popular' => true],
            ['name_en' => 'Zakynthos', 'name_th' => 'ซาคินทอส'],
            ['name_en' => 'Olympia', 'name_th' => 'โอลิมเปีย'],
            ['name_en' => 'Naxos', 'name_th' => 'นาซอส'],
            ['name_en' => 'Paros', 'name_th' => 'ปารอส'],
        ];
    }
    
    // ===== CROATIA =====
    private function getCroatiaCities(): array
    {
        return [
            ['name_en' => 'Dubrovnik', 'name_th' => 'ดูบรอฟนิก', 'is_popular' => true],
            ['name_en' => 'Zagreb', 'name_th' => 'ซาเกร็บ'],
            ['name_en' => 'Split', 'name_th' => 'สปลิท', 'is_popular' => true],
            ['name_en' => 'Plitvice Lakes', 'name_th' => 'ทะเลสาบพลิทวิเซ', 'is_popular' => true],
            ['name_en' => 'Hvar', 'name_th' => 'ฮวาร์'],
            ['name_en' => 'Rovinj', 'name_th' => 'โรวินจ์'],
            ['name_en' => 'Zadar', 'name_th' => 'ซาดาร์'],
            ['name_en' => 'Korcula', 'name_th' => 'คอร์คูลา'],
        ];
    }
    
    // ===== USA =====
    private function getUSACities(): array
    {
        return [
            ['name_en' => 'New York', 'name_th' => 'นิวยอร์ก', 'is_popular' => true],
            ['name_en' => 'Los Angeles', 'name_th' => 'ลอสแอนเจลิส', 'is_popular' => true],
            ['name_en' => 'San Francisco', 'name_th' => 'ซานฟรานซิสโก', 'is_popular' => true],
            ['name_en' => 'Las Vegas', 'name_th' => 'ลาสเวกัส', 'is_popular' => true],
            ['name_en' => 'Miami', 'name_th' => 'ไมอามี'],
            ['name_en' => 'Chicago', 'name_th' => 'ชิคาโก'],
            ['name_en' => 'Washington DC', 'name_th' => 'วอชิงตัน ดี.ซี.'],
            ['name_en' => 'Boston', 'name_th' => 'บอสตัน'],
            ['name_en' => 'Seattle', 'name_th' => 'ซีแอตเทิล'],
            ['name_en' => 'Orlando', 'name_th' => 'ออร์แลนโด', 'is_popular' => true],
            ['name_en' => 'Hawaii', 'name_th' => 'ฮาวาย', 'is_popular' => true],
            ['name_en' => 'Grand Canyon', 'name_th' => 'แกรนด์ แคนยอน', 'is_popular' => true],
            ['name_en' => 'Yellowstone', 'name_th' => 'เยลโลว์สโตน'],
            ['name_en' => 'New Orleans', 'name_th' => 'นิวออร์ลีนส์'],
            ['name_en' => 'San Diego', 'name_th' => 'ซานดิเอโก'],
            ['name_en' => 'Philadelphia', 'name_th' => 'ฟิลาเดลเฟีย'],
            ['name_en' => 'Denver', 'name_th' => 'เดนเวอร์'],
            ['name_en' => 'Nashville', 'name_th' => 'แนชวิลล์'],
            ['name_en' => 'Honolulu', 'name_th' => 'โฮโนลูลู', 'is_popular' => true],
            ['name_en' => 'Maui', 'name_th' => 'เมาอิ'],
            ['name_en' => 'Alaska', 'name_th' => 'อะแลสกา'],
            ['name_en' => 'Niagara Falls', 'name_th' => 'น้ำตกไนแอการา', 'is_popular' => true],
            ['name_en' => 'Hollywood', 'name_th' => 'ฮอลลีวูด'],
            ['name_en' => 'Disneyland', 'name_th' => 'ดิสนีย์แลนด์'],
            ['name_en' => 'Times Square', 'name_th' => 'ไทม์สแควร์'],
        ];
    }
    
    // ===== CANADA =====
    private function getCanadaCities(): array
    {
        return [
            ['name_en' => 'Vancouver', 'name_th' => 'แวนคูเวอร์', 'is_popular' => true],
            ['name_en' => 'Toronto', 'name_th' => 'โตรอนโต', 'is_popular' => true],
            ['name_en' => 'Montreal', 'name_th' => 'มอนทรีออล'],
            ['name_en' => 'Banff', 'name_th' => 'แบนฟ์', 'is_popular' => true],
            ['name_en' => 'Quebec City', 'name_th' => 'ควิเบก ซิตี้'],
            ['name_en' => 'Ottawa', 'name_th' => 'ออตตาวา'],
            ['name_en' => 'Victoria', 'name_th' => 'วิกตอเรีย'],
            ['name_en' => 'Calgary', 'name_th' => 'แคลกะรี'],
            ['name_en' => 'Niagara Falls', 'name_th' => 'น้ำตกไนแอการา', 'is_popular' => true],
            ['name_en' => 'Whistler', 'name_th' => 'วิสต์เลอร์'],
            ['name_en' => 'Jasper', 'name_th' => 'แจสเปอร์'],
            ['name_en' => 'Lake Louise', 'name_th' => 'เลค หลุยส์', 'is_popular' => true],
            ['name_en' => 'Edmonton', 'name_th' => 'เอ็ดมันตัน'],
            ['name_en' => 'Yellowknife', 'name_th' => 'เยลโลไนฟ์'],
        ];
    }
    
    // ===== MEXICO =====
    private function getMexicoCities(): array
    {
        return [
            ['name_en' => 'Mexico City', 'name_th' => 'เม็กซิโก ซิตี้', 'is_popular' => true],
            ['name_en' => 'Cancun', 'name_th' => 'แคนคูน', 'is_popular' => true],
            ['name_en' => 'Playa del Carmen', 'name_th' => 'พลายา เดล คาร์เมน'],
            ['name_en' => 'Tulum', 'name_th' => 'ตูลุม', 'is_popular' => true],
            ['name_en' => 'Los Cabos', 'name_th' => 'ลอส คาบอส'],
            ['name_en' => 'Puerto Vallarta', 'name_th' => 'เปอร์โต วายาร์ตา'],
            ['name_en' => 'Guadalajara', 'name_th' => 'กวาดาลาฮารา'],
            ['name_en' => 'Oaxaca', 'name_th' => 'โออาฮากา'],
            ['name_en' => 'Chichen Itza', 'name_th' => 'ชิเชน อิตซา', 'is_popular' => true],
            ['name_en' => 'Riviera Maya', 'name_th' => 'ริเวียรา มายา'],
            ['name_en' => 'Cozumel', 'name_th' => 'โคซูเมล'],
        ];
    }
    
    // ===== BRAZIL =====
    private function getBrazilCities(): array
    {
        return [
            ['name_en' => 'Rio de Janeiro', 'name_th' => 'รีโอ เดอ จาเนโร', 'is_popular' => true],
            ['name_en' => 'Sao Paulo', 'name_th' => 'เซาเปาโล', 'is_popular' => true],
            ['name_en' => 'Iguazu Falls', 'name_th' => 'น้ำตกอิกวาซู', 'is_popular' => true],
            ['name_en' => 'Salvador', 'name_th' => 'ซัลวาดอร์'],
            ['name_en' => 'Brasilia', 'name_th' => 'บราซิเลีย'],
            ['name_en' => 'Amazon', 'name_th' => 'อเมซอน', 'is_popular' => true],
            ['name_en' => 'Florianopolis', 'name_th' => 'ฟลอเรียนอโปลิส'],
            ['name_en' => 'Copacabana', 'name_th' => 'โคปาคาบานา'],
            ['name_en' => 'Buzios', 'name_th' => 'บูซิโอส'],
            ['name_en' => 'Fernando de Noronha', 'name_th' => 'เฟอร์นันโด เดอ โนรอนญา'],
        ];
    }
    
    // ===== ARGENTINA =====
    private function getArgentinaCities(): array
    {
        return [
            ['name_en' => 'Buenos Aires', 'name_th' => 'บัวโนสไอเรส', 'is_popular' => true],
            ['name_en' => 'Iguazu Falls', 'name_th' => 'น้ำตกอิกวาซู', 'is_popular' => true],
            ['name_en' => 'Patagonia', 'name_th' => 'ปาตาโกเนีย', 'is_popular' => true],
            ['name_en' => 'Mendoza', 'name_th' => 'เมนโดซา'],
            ['name_en' => 'El Calafate', 'name_th' => 'เอล กาลาฟาเต'],
            ['name_en' => 'Ushuaia', 'name_th' => 'อุสไวอา', 'is_popular' => true],
            ['name_en' => 'Bariloche', 'name_th' => 'บาริโลเช่'],
            ['name_en' => 'Salta', 'name_th' => 'ซัลตา'],
            ['name_en' => 'Perito Moreno', 'name_th' => 'เปริโต โมเรโน'],
        ];
    }
    
    // ===== CHILE =====
    private function getChileCities(): array
    {
        return [
            ['name_en' => 'Santiago', 'name_th' => 'ซานติอาโก', 'is_popular' => true],
            ['name_en' => 'Easter Island', 'name_th' => 'เกาะอีสเตอร์', 'is_popular' => true],
            ['name_en' => 'Valparaiso', 'name_th' => 'วัลปาไรโซ'],
            ['name_en' => 'Atacama Desert', 'name_th' => 'ทะเลทรายอาตากามา', 'is_popular' => true],
            ['name_en' => 'Torres del Paine', 'name_th' => 'ตอร์เรส เดล ไปเน', 'is_popular' => true],
            ['name_en' => 'Punta Arenas', 'name_th' => 'ปุนตา อาเรนาส'],
            ['name_en' => 'Vina del Mar', 'name_th' => 'วีญา เดล มาร์'],
        ];
    }
    
    // ===== PERU =====
    private function getPeruCities(): array
    {
        return [
            ['name_en' => 'Lima', 'name_th' => 'ลิมา', 'is_popular' => true],
            ['name_en' => 'Cusco', 'name_th' => 'กุสโก', 'is_popular' => true],
            ['name_en' => 'Machu Picchu', 'name_th' => 'มาชู ปิกชู', 'is_popular' => true],
            ['name_en' => 'Arequipa', 'name_th' => 'อาเรกิปา'],
            ['name_en' => 'Lake Titicaca', 'name_th' => 'ทะเลสาบติติกากา', 'is_popular' => true],
            ['name_en' => 'Nazca Lines', 'name_th' => 'เส้นนาซกา'],
            ['name_en' => 'Sacred Valley', 'name_th' => 'หุบเขาศักดิ์สิทธิ์'],
            ['name_en' => 'Puno', 'name_th' => 'ปูโน'],
            ['name_en' => 'Iquitos', 'name_th' => 'อิกิโตส'],
        ];
    }
    
    // ===== EGYPT =====
    private function getEgyptCities(): array
    {
        return [
            ['name_en' => 'Cairo', 'name_th' => 'ไคโร', 'is_popular' => true],
            ['name_en' => 'Giza', 'name_th' => 'กิซา', 'is_popular' => true],
            ['name_en' => 'Luxor', 'name_th' => 'ลักซอร์', 'is_popular' => true],
            ['name_en' => 'Aswan', 'name_th' => 'อัสวาน'],
            ['name_en' => 'Alexandria', 'name_th' => 'อเล็กซานเดรีย'],
            ['name_en' => 'Hurghada', 'name_th' => 'เฮอร์กาดา'],
            ['name_en' => 'Sharm El Sheikh', 'name_th' => 'ชาร์ม เอล ชีค'],
            ['name_en' => 'Abu Simbel', 'name_th' => 'อาบู ซิมเบล', 'is_popular' => true],
            ['name_en' => 'Valley of the Kings', 'name_th' => 'หุบผากษัตริย์', 'is_popular' => true],
            ['name_en' => 'Nile River', 'name_th' => 'แม่น้ำไนล์'],
        ];
    }
    
    // ===== MOROCCO =====
    private function getMoroccoCities(): array
    {
        return [
            ['name_en' => 'Marrakech', 'name_th' => 'มาร์ราเกช', 'is_popular' => true],
            ['name_en' => 'Fes', 'name_th' => 'เฟซ', 'is_popular' => true],
            ['name_en' => 'Casablanca', 'name_th' => 'คาซาบลังกา', 'is_popular' => true],
            ['name_en' => 'Chefchaouen', 'name_th' => 'เชฟชาอูน', 'is_popular' => true],
            ['name_en' => 'Rabat', 'name_th' => 'ราบัต'],
            ['name_en' => 'Tangier', 'name_th' => 'แทนเจียร์'],
            ['name_en' => 'Essaouira', 'name_th' => 'เอสซาวีรา'],
            ['name_en' => 'Sahara Desert', 'name_th' => 'ทะเลทรายสะฮารา', 'is_popular' => true],
            ['name_en' => 'Merzouga', 'name_th' => 'เมอร์ซูกา'],
            ['name_en' => 'Atlas Mountains', 'name_th' => 'เทือกเขาแอตลาส'],
        ];
    }
    
    // ===== KENYA =====
    private function getKenyaCities(): array
    {
        return [
            ['name_en' => 'Nairobi', 'name_th' => 'ไนโรบี', 'is_popular' => true],
            ['name_en' => 'Masai Mara', 'name_th' => 'มาไซ มารา', 'is_popular' => true],
            ['name_en' => 'Mombasa', 'name_th' => 'มอมบาซา'],
            ['name_en' => 'Amboseli', 'name_th' => 'อัมโบเซลี'],
            ['name_en' => 'Lake Nakuru', 'name_th' => 'ทะเลสาบนาคูรู'],
            ['name_en' => 'Diani Beach', 'name_th' => 'หาดเดียนี'],
            ['name_en' => 'Mount Kenya', 'name_th' => 'ภูเขาเคนยา'],
            ['name_en' => 'Lamu', 'name_th' => 'ลามู'],
        ];
    }
    
    // ===== SOUTH AFRICA =====
    private function getSouthAfricaCities(): array
    {
        return [
            ['name_en' => 'Cape Town', 'name_th' => 'เคปทาวน์', 'is_popular' => true],
            ['name_en' => 'Johannesburg', 'name_th' => 'โจฮันเนสเบิร์ก'],
            ['name_en' => 'Kruger National Park', 'name_th' => 'อุทยานครูเกอร์', 'is_popular' => true],
            ['name_en' => 'Durban', 'name_th' => 'เดอร์บัน'],
            ['name_en' => 'Garden Route', 'name_th' => 'การ์เดน รูท'],
            ['name_en' => 'Table Mountain', 'name_th' => 'เทเบิล เมาเทน', 'is_popular' => true],
            ['name_en' => 'Stellenbosch', 'name_th' => 'สเตลเลนบอช'],
            ['name_en' => 'Port Elizabeth', 'name_th' => 'พอร์ต อลิซาเบธ'],
            ['name_en' => 'Pretoria', 'name_th' => 'พริทอเรีย'],
            ['name_en' => 'Blyde River Canyon', 'name_th' => 'ไบลด์ ริเวอร์ แคนยอน'],
        ];
    }
    
    // ===== AUSTRALIA =====
    private function getAustraliaCities(): array
    {
        return [
            ['name_en' => 'Sydney', 'name_th' => 'ซิดนีย์', 'is_popular' => true],
            ['name_en' => 'Melbourne', 'name_th' => 'เมลเบิร์น', 'is_popular' => true],
            ['name_en' => 'Brisbane', 'name_th' => 'บริสเบน'],
            ['name_en' => 'Perth', 'name_th' => 'เพิร์ธ'],
            ['name_en' => 'Adelaide', 'name_th' => 'แอดิเลด'],
            ['name_en' => 'Gold Coast', 'name_th' => 'โกลด์ โคสต์', 'is_popular' => true],
            ['name_en' => 'Cairns', 'name_th' => 'แคนส์'],
            ['name_en' => 'Great Barrier Reef', 'name_th' => 'เกรท แบริเออร์ รีฟ', 'is_popular' => true],
            ['name_en' => 'Uluru', 'name_th' => 'อูลูรู', 'is_popular' => true],
            ['name_en' => 'Tasmania', 'name_th' => 'แทสเมเนีย'],
            ['name_en' => 'Great Ocean Road', 'name_th' => 'เกรท โอเชียน โร้ด'],
            ['name_en' => 'Whitsundays', 'name_th' => 'วิตซันเดย์'],
            ['name_en' => 'Darwin', 'name_th' => 'ดาร์วิน'],
            ['name_en' => 'Kakadu', 'name_th' => 'คาคาดู'],
            ['name_en' => 'Byron Bay', 'name_th' => 'ไบรอน เบย์'],
            ['name_en' => 'Canberra', 'name_th' => 'แคนเบอร์รา'],
            ['name_en' => 'Alice Springs', 'name_th' => 'อลิซ สปริงส์'],
            ['name_en' => 'Phillip Island', 'name_th' => 'เกาะฟิลลิป'],
        ];
    }
    
    // ===== NEW ZEALAND =====
    private function getNewZealandCities(): array
    {
        return [
            ['name_en' => 'Auckland', 'name_th' => 'โอ๊คแลนด์', 'is_popular' => true],
            ['name_en' => 'Queenstown', 'name_th' => 'ควีนส์ทาวน์', 'is_popular' => true],
            ['name_en' => 'Rotorua', 'name_th' => 'โรโตรัว', 'is_popular' => true],
            ['name_en' => 'Wellington', 'name_th' => 'เวลลิงตัน'],
            ['name_en' => 'Christchurch', 'name_th' => 'ไครสต์เชิร์ช'],
            ['name_en' => 'Milford Sound', 'name_th' => 'มิลฟอร์ด ซาวด์', 'is_popular' => true],
            ['name_en' => 'Hobbiton', 'name_th' => 'ฮอบบิตัน', 'is_popular' => true],
            ['name_en' => 'Waitomo Caves', 'name_th' => 'ถ้ำไวโตโม'],
            ['name_en' => 'Lake Tekapo', 'name_th' => 'เลค เทคาโป', 'is_popular' => true],
            ['name_en' => 'Wanaka', 'name_th' => 'วานากา'],
            ['name_en' => 'Franz Josef Glacier', 'name_th' => 'ธารน้ำแข็งฟรานซ์ โจเซฟ'],
            ['name_en' => 'Bay of Islands', 'name_th' => 'เบย์ ออฟ ไอส์แลนด์'],
            ['name_en' => 'Mount Cook', 'name_th' => 'ภูเขาคุก'],
            ['name_en' => 'Fiordland', 'name_th' => 'ฟยอร์ดแลนด์'],
            ['name_en' => 'Abel Tasman', 'name_th' => 'อาเบล ทัสมัน'],
        ];
    }
    
    // ===== FIJI =====
    private function getFijiCities(): array
    {
        return [
            ['name_en' => 'Nadi', 'name_th' => 'นาดี', 'is_popular' => true],
            ['name_en' => 'Suva', 'name_th' => 'ซูวา'],
            ['name_en' => 'Denarau Island', 'name_th' => 'เกาะเดนาราว', 'is_popular' => true],
            ['name_en' => 'Mamanuca Islands', 'name_th' => 'หมู่เกาะมามานูกา'],
            ['name_en' => 'Yasawa Islands', 'name_th' => 'หมู่เกาะยาซาวา'],
            ['name_en' => 'Coral Coast', 'name_th' => 'คอรัล โคสต์'],
            ['name_en' => 'Taveuni', 'name_th' => 'ตาเวอูนี'],
        ];
    }
}
