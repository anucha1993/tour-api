<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * ข้อมูลอ้างอิงจาก tb_city.sql เดิม + เพิ่มเมืองยอดนิยม
     */
    public function run(): void
    {
        // Get country IDs by ISO2 code
        $countries = Country::pluck('id', 'iso2')->toArray();

        // Cities data: [name_en, name_th, country_iso2, is_popular]
        $cities = [
            // ===== Thailand (TH) =====
            ['Bangkok', 'กรุงเทพมหานคร', 'TH', true],
            ['Chiang Mai', 'เชียงใหม่', 'TH', true],
            ['Phuket', 'ภูเก็ต', 'TH', true],
            ['Chiang Rai', 'เชียงราย', 'TH', true],
            ['Krabi', 'กระบี่', 'TH', true],
            ['Koh Samui', 'เกาะสมุย', 'TH', true],
            ['Pattaya', 'พัทยา', 'TH', true],
            ['Hat Yai', 'หาดใหญ่', 'TH', false],
            ['Hua Hin', 'หัวหิน', 'TH', true],
            ['Ayutthaya', 'อยุธยา', 'TH', true],
            ['Sukhothai', 'สุโขทัย', 'TH', false],
            ['Kanchanaburi', 'กาญจนบุรี', 'TH', true],
            ['Pai', 'ปาย', 'TH', true],
            ['Khon Kaen', 'ขอนแก่น', 'TH', false],
            ['Udon Thani', 'อุดรธานี', 'TH', false],
            ['Nakhon Ratchasima', 'นครราชสีมา', 'TH', false],
            ['Surat Thani', 'สุราษฎร์ธานี', 'TH', false],
            ['Koh Phi Phi', 'เกาะพีพี', 'TH', true],
            ['Koh Lipe', 'เกาะหลีเป๊ะ', 'TH', true],
            ['Koh Tao', 'เกาะเต่า', 'TH', true],
            ['Koh Chang', 'เกาะช้าง', 'TH', true],
            ['Koh Lanta', 'เกาะลันตา', 'TH', true],
            
            // ===== Japan (JP) =====
            ['Tokyo', 'โตเกียว', 'JP', true],
            ['Osaka', 'โอซาก้า', 'JP', true],
            ['Kyoto', 'เกียวโต', 'JP', true],
            ['Fukuoka', 'ฟุกุโอกะ', 'JP', true],
            ['Sapporo', 'ซัปโปโร', 'JP', true],
            ['Nagoya', 'นาโกย่า', 'JP', true],
            ['Okinawa', 'โอกินาว่า', 'JP', true],
            ['Hiroshima', 'ฮิโรชิม่า', 'JP', true],
            ['Nara', 'นารา', 'JP', true],
            ['Kobe', 'โกเบ', 'JP', false],
            ['Yokohama', 'โยโกฮาม่า', 'JP', false],
            ['Hakone', 'ฮาโกเน่', 'JP', true],
            ['Nikko', 'นิกโก้', 'JP', true],
            ['Kanazawa', 'คานาซาว่า', 'JP', true],
            ['Takayama', 'ทาคายาม่า', 'JP', true],
            ['Shirakawa-go', 'ชิราคาวาโกะ', 'JP', true],
            ['Nagano', 'นากาโน่', 'JP', false],
            ['Sendai', 'เซนได', 'JP', false],
            ['Kamakura', 'คามาคุระ', 'JP', true],
            ['Mount Fuji', 'ภูเขาฟูจิ', 'JP', true],
            
            // ===== South Korea (KR) =====
            ['Seoul', 'โซล', 'KR', true],
            ['Busan', 'ปูซาน', 'KR', true],
            ['Jeju', 'เชจู', 'KR', true],
            ['Incheon', 'อินชอน', 'KR', false],
            ['Gyeongju', 'คยองจู', 'KR', true],
            ['Gangneung', 'คังนึง', 'KR', false],
            ['Jeonju', 'ชอนจู', 'KR', true],
            ['Suwon', 'ซูวอน', 'KR', false],
            ['Daegu', 'แทกู', 'KR', false],
            ['Nami Island', 'เกาะนามิ', 'KR', true],
            
            // ===== China (CN) =====
            ['Beijing', 'ปักกิ่ง', 'CN', true],
            ['Shanghai', 'เซี่ยงไฮ้', 'CN', true],
            ['Guangzhou', 'กวางโจว', 'CN', true],
            ['Shenzhen', 'เซินเจิ้น', 'CN', false],
            ['Chengdu', 'เฉิงตู', 'CN', true],
            ['Xian', 'ซีอาน', 'CN', true],
            ['Hangzhou', 'หางโจว', 'CN', true],
            ['Kunming', 'คุนหมิง', 'CN', true],
            ['Guilin', 'กุ้ยหลิน', 'CN', true],
            ['Lijiang', 'ลี่เจียง', 'CN', true],
            ['Zhangjiajie', 'จางเจียเจี้ย', 'CN', true],
            ['Suzhou', 'ซูโจว', 'CN', true],
            ['Harbin', 'ฮาร์บิน', 'CN', true],
            ['Chongqing', 'ฉงชิ่ง', 'CN', true],
            ['Xiamen', 'เซียะเหมิน', 'CN', false],
            ['Nanjing', 'หนานจิง', 'CN', false],
            
            // ===== Hong Kong (HK) =====
            ['Hong Kong', 'ฮ่องกง', 'HK', true],
            ['Kowloon', 'เกาลูน', 'HK', false],
            ['Lantau Island', 'เกาะลันเตา', 'HK', false],
            
            // ===== Macau (MO) =====
            ['Macau', 'มาเก๊า', 'MO', true],
            
            // ===== Taiwan (TW) =====
            ['Taipei', 'ไทเป', 'TW', true],
            ['Kaohsiung', 'เกาสง', 'TW', true],
            ['Taichung', 'ไถจง', 'TW', true],
            ['Tainan', 'ไถหนาน', 'TW', true],
            ['Hualien', 'ฮวาเหลียน', 'TW', true],
            ['Jiufen', 'จิ่วเฟิ่น', 'TW', true],
            ['Sun Moon Lake', 'ทะเลสาบสุริยัน จันทรา', 'TW', true],
            ['Alishan', 'อาลีซาน', 'TW', true],
            ['Kenting', 'เขินติง', 'TW', true],
            
            // ===== Vietnam (VN) =====
            ['Ho Chi Minh City', 'โฮจิมินห์', 'VN', true],
            ['Hanoi', 'ฮานอย', 'VN', true],
            ['Da Nang', 'ดานัง', 'VN', true],
            ['Hoi An', 'ฮอยอัน', 'VN', true],
            ['Ha Long Bay', 'อ่าวฮาลอง', 'VN', true],
            ['Nha Trang', 'ญาจาง', 'VN', true],
            ['Dalat', 'ดาลัด', 'VN', true],
            ['Sapa', 'ซาปา', 'VN', true],
            ['Hue', 'เว้', 'VN', true],
            ['Phu Quoc', 'เกาะฟู้โกว๊ก', 'VN', true],
            
            // ===== Singapore (SG) =====
            ['Singapore', 'สิงคโปร์', 'SG', true],
            ['Sentosa Island', 'เกาะเซ็นโตซ่า', 'SG', false],
            
            // ===== Malaysia (MY) =====
            ['Kuala Lumpur', 'กัวลาลัมเปอร์', 'MY', true],
            ['Penang', 'ปีนัง', 'MY', true],
            ['Langkawi', 'ลังกาวี', 'MY', true],
            ['Malacca', 'มะละกา', 'MY', true],
            ['Johor Bahru', 'ยะโฮร์บาห์รู', 'MY', false],
            ['Kota Kinabalu', 'โกตาคินาบาลู', 'MY', true],
            ['Cameron Highlands', 'คาเมรอนไฮแลนด์', 'MY', true],
            ['Genting Highlands', 'เก็นติ้งไฮแลนด์', 'MY', false],
            ['Ipoh', 'อิโปห์', 'MY', false],
            
            // ===== Indonesia (ID) =====
            ['Bali', 'บาหลี', 'ID', true],
            ['Jakarta', 'จาการ์ตา', 'ID', false],
            ['Yogyakarta', 'ยอกยาการ์ตา', 'ID', true],
            ['Lombok', 'ลอมบอก', 'ID', true],
            ['Ubud', 'อูบุด', 'ID', true],
            ['Seminyak', 'เซมินยัค', 'ID', true],
            ['Kuta', 'กูตา', 'ID', false],
            ['Nusa Penida', 'นูซาเปอนิดา', 'ID', true],
            ['Komodo Island', 'เกาะโคโมโด', 'ID', true],
            
            // ===== Philippines (PH) =====
            ['Manila', 'มะนิลา', 'PH', false],
            ['Cebu', 'เซบู', 'PH', true],
            ['Boracay', 'โบราเคย์', 'PH', true],
            ['Palawan', 'ปาลาวัน', 'PH', true],
            ['El Nido', 'เอลนิโด', 'PH', true],
            ['Bohol', 'โบโฮล', 'PH', true],
            ['Siargao', 'เซียร์เกา', 'PH', true],
            
            // ===== India (IN) =====
            ['New Delhi', 'นิวเดลี', 'IN', true],
            ['Mumbai', 'มุมไบ', 'IN', false],
            ['Jaipur', 'ชัยปุระ', 'IN', true],
            ['Agra', 'อัครา', 'IN', true],
            ['Varanasi', 'วารานสี', 'IN', true],
            ['Goa', 'กัว', 'IN', true],
            ['Udaipur', 'อุทัยปุระ', 'IN', true],
            ['Jodhpur', 'โชธปุระ', 'IN', true],
            ['Rishikesh', 'ฤษีเกศ', 'IN', true],
            ['Kerala', 'เกรละ', 'IN', true],
            
            // ===== Myanmar (MM) =====
            ['Yangon', 'ย่างกุ้ง', 'MM', true],
            ['Mandalay', 'มัณฑะเลย์', 'MM', true],
            ['Bagan', 'พุกาม', 'MM', true],
            ['Inle Lake', 'ทะเลสาบอินเล', 'MM', true],
            ['Naypyidaw', 'เนปยีดอ', 'MM', false],
            
            // ===== Cambodia (KH) =====
            ['Phnom Penh', 'พนมเปญ', 'KH', true],
            ['Siem Reap', 'เสียมเรียบ', 'KH', true],
            ['Sihanoukville', 'สีหนุวิลล์', 'KH', true],
            ['Battambang', 'พระตะบอง', 'KH', false],
            
            // ===== Laos (LA) =====
            ['Vientiane', 'เวียงจันทน์', 'LA', true],
            ['Luang Prabang', 'หลวงพระบาง', 'LA', true],
            ['Vang Vieng', 'วังเวียง', 'LA', true],
            ['Pakse', 'ปากเซ', 'LA', false],
            
            // ===== UAE (AE) =====
            ['Dubai', 'ดูไบ', 'AE', true],
            ['Abu Dhabi', 'อาบูดาบี', 'AE', true],
            
            // ===== Qatar (QA) =====
            ['Doha', 'โดฮา', 'QA', true],
            
            // ===== Turkey (TR) =====
            ['Istanbul', 'อิสตันบูล', 'TR', true],
            ['Cappadocia', 'คัปปาโดเกีย', 'TR', true],
            ['Antalya', 'อันตัลยา', 'TR', true],
            ['Pamukkale', 'ปามุคคาเล', 'TR', true],
            ['Bodrum', 'โบดรุม', 'TR', false],
            ['Izmir', 'อิซเมียร์', 'TR', false],
            ['Ephesus', 'เอเฟซัส', 'TR', true],
            
            // ===== United Kingdom (GB) =====
            ['London', 'ลอนดอน', 'GB', true],
            ['Edinburgh', 'เอดินบะระ', 'GB', true],
            ['Manchester', 'แมนเชสเตอร์', 'GB', false],
            ['Liverpool', 'ลิเวอร์พูล', 'GB', false],
            ['Oxford', 'อ็อกซ์ฟอร์ด', 'GB', true],
            ['Cambridge', 'เคมบริดจ์', 'GB', true],
            ['Bath', 'บาธ', 'GB', true],
            ['Stonehenge', 'สโตนเฮนจ์', 'GB', true],
            ['Cotswolds', 'คอตส์โวลด์', 'GB', true],
            
            // ===== France (FR) =====
            ['Paris', 'ปารีส', 'FR', true],
            ['Nice', 'นีซ', 'FR', true],
            ['Lyon', 'ลียง', 'FR', false],
            ['Marseille', 'มาร์กเซย', 'FR', false],
            ['Bordeaux', 'บอร์โด', 'FR', true],
            ['Strasbourg', 'สตราสบูร์ก', 'FR', true],
            ['Mont Saint-Michel', 'มงแซ็ง-มีแชล', 'FR', true],
            ['Provence', 'โพรวองซ์', 'FR', true],
            ['Versailles', 'แวร์ซายส์', 'FR', true],
            ['Cannes', 'คานส์', 'FR', false],
            
            // ===== Italy (IT) =====
            ['Rome', 'โรม', 'IT', true],
            ['Milan', 'มิลาน', 'IT', true],
            ['Venice', 'เวนิส', 'IT', true],
            ['Florence', 'ฟลอเรนซ์', 'IT', true],
            ['Naples', 'เนเปิลส์', 'IT', false],
            ['Amalfi Coast', 'อมาลฟี โคสต์', 'IT', true],
            ['Cinque Terre', 'ชิงเกว แตร์เร', 'IT', true],
            ['Tuscany', 'ทัสคานี', 'IT', true],
            ['Pisa', 'ปิซา', 'IT', true],
            ['Verona', 'เวโรนา', 'IT', true],
            ['Lake Como', 'ทะเลสาบโคโม', 'IT', true],
            ['Pompeii', 'ปอมเปอี', 'IT', true],
            
            // ===== Spain (ES) =====
            ['Barcelona', 'บาร์เซโลนา', 'ES', true],
            ['Madrid', 'มาดริด', 'ES', true],
            ['Seville', 'เซบียา', 'ES', true],
            ['Granada', 'กรานาดา', 'ES', true],
            ['Valencia', 'บาเลนเซีย', 'ES', false],
            ['Ibiza', 'อิบิซา', 'ES', true],
            ['Mallorca', 'มายอร์กา', 'ES', true],
            ['San Sebastian', 'ซานเซบาสเตียน', 'ES', false],
            ['Toledo', 'โตเลโด', 'ES', true],
            
            // ===== Germany (DE) =====
            ['Berlin', 'เบอร์ลิน', 'DE', true],
            ['Munich', 'มิวนิก', 'DE', true],
            ['Frankfurt', 'แฟรงก์เฟิร์ต', 'DE', false],
            ['Hamburg', 'ฮัมบูร์ก', 'DE', false],
            ['Cologne', 'โคโลญ', 'DE', false],
            ['Heidelberg', 'ไฮเดลเบิร์ก', 'DE', true],
            ['Neuschwanstein', 'นอยชวานสไตน์', 'DE', true],
            ['Black Forest', 'แบล็คฟอเรสต์', 'DE', true],
            ['Rothenburg', 'โรเทนบวร์ก', 'DE', true],
            
            // ===== Netherlands (NL) =====
            ['Amsterdam', 'อัมสเตอร์ดัม', 'NL', true],
            ['Rotterdam', 'รอตเตอร์ดัม', 'NL', false],
            ['The Hague', 'เดอะเฮก', 'NL', false],
            ['Keukenhof', 'สวนเคอเคนฮอฟ', 'NL', true],
            ['Giethoorn', 'กีธูร์น', 'NL', true],
            
            // ===== Belgium (BE) =====
            ['Brussels', 'บรัสเซลส์', 'BE', true],
            ['Bruges', 'บรูจส์', 'BE', true],
            ['Ghent', 'เกนท์', 'BE', true],
            ['Antwerp', 'แอนต์เวิร์ป', 'BE', false],
            
            // ===== Austria (AT) =====
            ['Vienna', 'เวียนนา', 'AT', true],
            ['Salzburg', 'ซาลซ์บูร์ก', 'AT', true],
            ['Hallstatt', 'ฮัลล์ชตัทท์', 'AT', true],
            ['Innsbruck', 'อินส์บรุค', 'AT', true],
            
            // ===== Switzerland (CH) =====
            ['Zurich', 'ซูริค', 'CH', true],
            ['Lucerne', 'ลูเซิร์น', 'CH', true],
            ['Geneva', 'เจนีวา', 'CH', true],
            ['Interlaken', 'อินเทอร์ลาเคิน', 'CH', true],
            ['Zermatt', 'เซอร์แมท', 'CH', true],
            ['Jungfrau', 'ยุงเฟรา', 'CH', true],
            ['Bern', 'เบิร์น', 'CH', false],
            ['Grindelwald', 'กรินเดลวาลด์', 'CH', true],
            
            // ===== Czech Republic (CZ) =====
            ['Prague', 'ปราก', 'CZ', true],
            ['Cesky Krumlov', 'เชสกี้ ครุมลอฟ', 'CZ', true],
            ['Karlovy Vary', 'คาร์โลวี วารี', 'CZ', false],
            
            // ===== Hungary (HU) =====
            ['Budapest', 'บูดาเปสต์', 'HU', true],
            
            // ===== Poland (PL) =====
            ['Krakow', 'คราคูฟ', 'PL', true],
            ['Warsaw', 'วอร์ซอ', 'PL', false],
            ['Gdansk', 'กดัญสก์', 'PL', false],
            ['Wroclaw', 'วรอตสวัฟ', 'PL', false],
            
            // ===== Portugal (PT) =====
            ['Lisbon', 'ลิสบอน', 'PT', true],
            ['Porto', 'ปอร์โต', 'PT', true],
            ['Sintra', 'ซินตรา', 'PT', true],
            ['Algarve', 'อัลการ์ฟ', 'PT', true],
            
            // ===== Greece (GR) =====
            ['Athens', 'เอเธนส์', 'GR', true],
            ['Santorini', 'ซานโตรินี', 'GR', true],
            ['Mykonos', 'มิโคนอส', 'GR', true],
            ['Crete', 'เกาะครีต', 'GR', true],
            ['Rhodes', 'โรดส์', 'GR', false],
            ['Meteora', 'เมเทโอรา', 'GR', true],
            
            // ===== Croatia (HR) =====
            ['Dubrovnik', 'ดูบรอฟนิก', 'HR', true],
            ['Split', 'สปลิท', 'HR', true],
            ['Zagreb', 'ซาเกร็บ', 'HR', false],
            ['Plitvice Lakes', 'ทะเลสาบพลิตวิเซ', 'HR', true],
            
            // ===== Iceland (IS) =====
            ['Reykjavik', 'เรคยาวิก', 'IS', true],
            ['Blue Lagoon', 'บลูลากูน', 'IS', true],
            ['Golden Circle', 'โกลเด้นเซอร์เคิล', 'IS', true],
            
            // ===== Norway (NO) =====
            ['Oslo', 'ออสโล', 'NO', true],
            ['Bergen', 'แบร์เกน', 'NO', true],
            ['Tromso', 'ทรอมโซ', 'NO', true],
            ['Lofoten', 'โลโฟเทน', 'NO', true],
            
            // ===== Sweden (SE) =====
            ['Stockholm', 'สตอกโฮล์ม', 'SE', true],
            ['Gothenburg', 'โกเธนเบิร์ก', 'SE', false],
            
            // ===== Finland (FI) =====
            ['Helsinki', 'เฮลซิงกิ', 'FI', true],
            ['Rovaniemi', 'โรวาเนียมี', 'FI', true],
            ['Lapland', 'แลปแลนด์', 'FI', true],
            
            // ===== Denmark (DK) =====
            ['Copenhagen', 'โคเปนเฮเกน', 'DK', true],
            
            // ===== Ireland (IE) =====
            ['Dublin', 'ดับลิน', 'IE', true],
            ['Galway', 'กัลเวย์', 'IE', false],
            ['Ring of Kerry', 'ริงออฟเคอร์รี', 'IE', true],
            
            // ===== Russia (RU) =====
            ['Moscow', 'มอสโก', 'RU', true],
            ['St. Petersburg', 'เซนต์ปีเตอร์สเบิร์ก', 'RU', true],
            
            // ===== Australia (AU) =====
            ['Sydney', 'ซิดนีย์', 'AU', true],
            ['Melbourne', 'เมลเบิร์น', 'AU', true],
            ['Brisbane', 'บริสเบน', 'AU', false],
            ['Perth', 'เพิร์ธ', 'AU', false],
            ['Gold Coast', 'โกลด์โคสต์', 'AU', true],
            ['Cairns', 'แคนส์', 'AU', true],
            ['Great Barrier Reef', 'เกรทแบริเออร์รีฟ', 'AU', true],
            ['Uluru', 'อูลูรู', 'AU', true],
            
            // ===== New Zealand (NZ) =====
            ['Auckland', 'โอ๊คแลนด์', 'NZ', true],
            ['Queenstown', 'ควีนส์ทาวน์', 'NZ', true],
            ['Rotorua', 'โรโตรัว', 'NZ', true],
            ['Milford Sound', 'มิลฟอร์ดซาวด์', 'NZ', true],
            ['Wellington', 'เวลลิงตัน', 'NZ', false],
            ['Christchurch', 'ไครสต์เชิร์ช', 'NZ', false],
            ['Hobbiton', 'ฮอบบิตัน', 'NZ', true],
            
            // ===== United States (US) =====
            ['New York', 'นิวยอร์ก', 'US', true],
            ['Los Angeles', 'ลอสแอนเจลิส', 'US', true],
            ['San Francisco', 'ซานฟรานซิสโก', 'US', true],
            ['Las Vegas', 'ลาสเวกัส', 'US', true],
            ['Miami', 'ไมอามี', 'US', true],
            ['Orlando', 'ออร์แลนโด', 'US', true],
            ['Hawaii', 'ฮาวาย', 'US', true],
            ['Chicago', 'ชิคาโก', 'US', false],
            ['Washington D.C.', 'วอชิงตัน ดี.ซี.', 'US', true],
            ['Grand Canyon', 'แกรนด์แคนยอน', 'US', true],
            ['Yellowstone', 'เยลโลว์สโตน', 'US', true],
            ['Boston', 'บอสตัน', 'US', false],
            ['Seattle', 'ซีแอตเทิล', 'US', false],
            ['San Diego', 'ซานดิเอโก', 'US', false],
            
            // ===== Canada (CA) =====
            ['Vancouver', 'แวนคูเวอร์', 'CA', true],
            ['Toronto', 'โทรอนโต', 'CA', true],
            ['Montreal', 'มอนทรีออล', 'CA', false],
            ['Banff', 'แบนฟ์', 'CA', true],
            ['Niagara Falls', 'น้ำตกไนแองการา', 'CA', true],
            ['Quebec City', 'ควิเบกซิตี้', 'CA', true],
            
            // ===== Maldives (MV) =====
            ['Male', 'มาเล่', 'MV', true],
            ['Maldives Islands', 'หมู่เกาะมัลดีฟส์', 'MV', true],
            
            // ===== Sri Lanka (LK) =====
            ['Colombo', 'โคลัมโบ', 'LK', true],
            ['Kandy', 'แคนดี้', 'LK', true],
            ['Sigiriya', 'สิกิริยา', 'LK', true],
            ['Ella', 'เอลลา', 'LK', true],
            ['Galle', 'กอลล์', 'LK', true],
            
            // ===== Nepal (NP) =====
            ['Kathmandu', 'กาฐมาณฑุ', 'NP', true],
            ['Pokhara', 'โปขรา', 'NP', true],
            ['Everest Base Camp', 'เอเวอเรสต์เบสแคมป์', 'NP', true],
            
            // ===== Bhutan (BT) =====
            ['Thimphu', 'ทิมพู', 'BT', true],
            ['Paro', 'พาโร', 'BT', true],
            
            // ===== Egypt (EG) =====
            ['Cairo', 'ไคโร', 'EG', true],
            ['Luxor', 'ลักซอร์', 'EG', true],
            ['Aswan', 'อัสวาน', 'EG', true],
            ['Giza', 'กีซา', 'EG', true],
            ['Alexandria', 'อเล็กซานเดรีย', 'EG', false],
            ['Hurghada', 'เฮอร์กาดา', 'EG', true],
            ['Sharm El Sheikh', 'ชาร์มเอลชีค', 'EG', true],
            
            // ===== South Africa (ZA) =====
            ['Cape Town', 'เคปทาวน์', 'ZA', true],
            ['Johannesburg', 'โจฮันเนสเบิร์ก', 'ZA', false],
            ['Kruger National Park', 'อุทยานครูเกอร์', 'ZA', true],
            ['Garden Route', 'การ์เดนรูท', 'ZA', true],
            
            // ===== Morocco (MA) =====
            ['Marrakech', 'มาราเกช', 'MA', true],
            ['Casablanca', 'คาซาบลังกา', 'MA', false],
            ['Fes', 'เฟส', 'MA', true],
            ['Chefchaouen', 'เชฟชาอูน', 'MA', true],
            ['Sahara Desert', 'ทะเลทรายซาฮารา', 'MA', true],
            
            // ===== Kenya (KE) =====
            ['Nairobi', 'ไนโรบี', 'KE', true],
            ['Masai Mara', 'มาไซมารา', 'KE', true],
            
            // ===== Tanzania (TZ) =====
            ['Serengeti', 'เซเรนเกติ', 'TZ', true],
            ['Zanzibar', 'แซนซิบาร์', 'TZ', true],
            ['Kilimanjaro', 'คิลิมันจาโร', 'TZ', true],
            
            // ===== Jordan (JO) =====
            ['Amman', 'อัมมาน', 'JO', true],
            ['Petra', 'เปตรา', 'JO', true],
            ['Dead Sea', 'ทะเลเดดซี', 'JO', true],
            ['Wadi Rum', 'วาดีรัม', 'JO', true],
            
            // ===== Israel (IL) =====
            ['Jerusalem', 'เยรูซาเลม', 'IL', true],
            ['Tel Aviv', 'เทลอาวีฟ', 'IL', true],
            
            // ===== Mexico (MX) =====
            ['Mexico City', 'เม็กซิโกซิตี้', 'MX', true],
            ['Cancun', 'แคนคูน', 'MX', true],
            ['Playa del Carmen', 'ปลายาเดลคาร์เมน', 'MX', true],
            ['Tulum', 'ตูลุม', 'MX', true],
            ['Chichen Itza', 'ชิเชนอิตซา', 'MX', true],
            
            // ===== Cuba (CU) =====
            ['Havana', 'ฮาวานา', 'CU', true],
            ['Varadero', 'วาราเดโร', 'CU', true],
            
            // ===== Brazil (BR) =====
            ['Rio de Janeiro', 'รีโอเดจาเนโร', 'BR', true],
            ['Sao Paulo', 'เซาเปาลู', 'BR', false],
            ['Iguazu Falls', 'น้ำตกอีกวาซู', 'BR', true],
            
            // ===== Argentina (AR) =====
            ['Buenos Aires', 'บัวโนสไอเรส', 'AR', true],
            ['Patagonia', 'ปาตาโกเนีย', 'AR', true],
            
            // ===== Peru (PE) =====
            ['Lima', 'ลิมา', 'PE', false],
            ['Cusco', 'กุสโก', 'PE', true],
            ['Machu Picchu', 'มาชูปิกชู', 'PE', true],
            
            // ===== Chile (CL) =====
            ['Santiago', 'ซันติอาโก', 'CL', false],
            ['Atacama Desert', 'ทะเลทรายอาตากามา', 'CL', true],
            ['Easter Island', 'เกาะอีสเตอร์', 'CL', true],
        ];

        $count = 0;
        $skipped = 0;

        foreach ($cities as $cityData) {
            [$nameEn, $nameTh, $countryIso, $isPopular] = $cityData;
            
            if (!isset($countries[$countryIso])) {
                $this->command->warn("Country {$countryIso} not found, skipping {$nameEn}");
                $skipped++;
                continue;
            }

            $slug = Str::slug($nameEn);
            
            // Ensure unique slug
            $originalSlug = $slug;
            $counter = 1;
            while (City::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }

            City::updateOrCreate(
                ['name_en' => $nameEn, 'country_id' => $countries[$countryIso]],
                [
                    'name_th' => $nameTh,
                    'slug' => $slug,
                    'country_id' => $countries[$countryIso],
                    'is_popular' => $isPopular,
                    'is_active' => true,
                ]
            );
            
            $count++;
        }

        $this->command->info("Cities seeded: {$count} cities created/updated, {$skipped} skipped");
    }
}
