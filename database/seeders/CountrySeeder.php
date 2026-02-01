<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            // Asia - East
            ['iso2' => 'JP', 'iso3' => 'JPN', 'name_en' => 'Japan', 'name_th' => 'ญี่ปุ่น', 'slug' => 'japan', 'region' => 'Asia', 'flag_emoji' => '🇯🇵'],
            ['iso2' => 'KR', 'iso3' => 'KOR', 'name_en' => 'South Korea', 'name_th' => 'เกาหลีใต้', 'slug' => 'south-korea', 'region' => 'Asia', 'flag_emoji' => '🇰🇷'],
            ['iso2' => 'CN', 'iso3' => 'CHN', 'name_en' => 'China', 'name_th' => 'จีน', 'slug' => 'china', 'region' => 'Asia', 'flag_emoji' => '🇨🇳'],
            ['iso2' => 'TW', 'iso3' => 'TWN', 'name_en' => 'Taiwan', 'name_th' => 'ไต้หวัน', 'slug' => 'taiwan', 'region' => 'Asia', 'flag_emoji' => '🇹🇼'],
            ['iso2' => 'HK', 'iso3' => 'HKG', 'name_en' => 'Hong Kong', 'name_th' => 'ฮ่องกง', 'slug' => 'hong-kong', 'region' => 'Asia', 'flag_emoji' => '🇭🇰'],
            ['iso2' => 'MO', 'iso3' => 'MAC', 'name_en' => 'Macau', 'name_th' => 'มาเก๊า', 'slug' => 'macau', 'region' => 'Asia', 'flag_emoji' => '🇲🇴'],
            ['iso2' => 'MN', 'iso3' => 'MNG', 'name_en' => 'Mongolia', 'name_th' => 'มองโกเลีย', 'slug' => 'mongolia', 'region' => 'Asia', 'flag_emoji' => '🇲🇳'],

            // Asia - Southeast
            ['iso2' => 'TH', 'iso3' => 'THA', 'name_en' => 'Thailand', 'name_th' => 'ไทย', 'slug' => 'thailand', 'region' => 'Asia', 'flag_emoji' => '🇹🇭'],
            ['iso2' => 'VN', 'iso3' => 'VNM', 'name_en' => 'Vietnam', 'name_th' => 'เวียดนาม', 'slug' => 'vietnam', 'region' => 'Asia', 'flag_emoji' => '🇻🇳'],
            ['iso2' => 'SG', 'iso3' => 'SGP', 'name_en' => 'Singapore', 'name_th' => 'สิงคโปร์', 'slug' => 'singapore', 'region' => 'Asia', 'flag_emoji' => '🇸🇬'],
            ['iso2' => 'MY', 'iso3' => 'MYS', 'name_en' => 'Malaysia', 'name_th' => 'มาเลเซีย', 'slug' => 'malaysia', 'region' => 'Asia', 'flag_emoji' => '🇲🇾'],
            ['iso2' => 'ID', 'iso3' => 'IDN', 'name_en' => 'Indonesia', 'name_th' => 'อินโดนีเซีย', 'slug' => 'indonesia', 'region' => 'Asia', 'flag_emoji' => '🇮🇩'],
            ['iso2' => 'PH', 'iso3' => 'PHL', 'name_en' => 'Philippines', 'name_th' => 'ฟิลิปปินส์', 'slug' => 'philippines', 'region' => 'Asia', 'flag_emoji' => '🇵🇭'],
            ['iso2' => 'MM', 'iso3' => 'MMR', 'name_en' => 'Myanmar', 'name_th' => 'เมียนมาร์', 'slug' => 'myanmar', 'region' => 'Asia', 'flag_emoji' => '🇲🇲'],
            ['iso2' => 'LA', 'iso3' => 'LAO', 'name_en' => 'Laos', 'name_th' => 'ลาว', 'slug' => 'laos', 'region' => 'Asia', 'flag_emoji' => '🇱🇦'],
            ['iso2' => 'KH', 'iso3' => 'KHM', 'name_en' => 'Cambodia', 'name_th' => 'กัมพูชา', 'slug' => 'cambodia', 'region' => 'Asia', 'flag_emoji' => '🇰🇭'],
            ['iso2' => 'BN', 'iso3' => 'BRN', 'name_en' => 'Brunei', 'name_th' => 'บรูไน', 'slug' => 'brunei', 'region' => 'Asia', 'flag_emoji' => '🇧🇳'],
            ['iso2' => 'TL', 'iso3' => 'TLS', 'name_en' => 'Timor-Leste', 'name_th' => 'ติมอร์-เลสเต', 'slug' => 'timor-leste', 'region' => 'Asia', 'flag_emoji' => '🇹🇱'],

            // Asia - South
            ['iso2' => 'IN', 'iso3' => 'IND', 'name_en' => 'India', 'name_th' => 'อินเดีย', 'slug' => 'india', 'region' => 'Asia', 'flag_emoji' => '🇮🇳'],
            ['iso2' => 'LK', 'iso3' => 'LKA', 'name_en' => 'Sri Lanka', 'name_th' => 'ศรีลังกา', 'slug' => 'sri-lanka', 'region' => 'Asia', 'flag_emoji' => '🇱🇰'],
            ['iso2' => 'NP', 'iso3' => 'NPL', 'name_en' => 'Nepal', 'name_th' => 'เนปาล', 'slug' => 'nepal', 'region' => 'Asia', 'flag_emoji' => '🇳🇵'],
            ['iso2' => 'BT', 'iso3' => 'BTN', 'name_en' => 'Bhutan', 'name_th' => 'ภูฏาน', 'slug' => 'bhutan', 'region' => 'Asia', 'flag_emoji' => '🇧🇹'],
            ['iso2' => 'BD', 'iso3' => 'BGD', 'name_en' => 'Bangladesh', 'name_th' => 'บังกลาเทศ', 'slug' => 'bangladesh', 'region' => 'Asia', 'flag_emoji' => '🇧🇩'],
            ['iso2' => 'PK', 'iso3' => 'PAK', 'name_en' => 'Pakistan', 'name_th' => 'ปากีสถาน', 'slug' => 'pakistan', 'region' => 'Asia', 'flag_emoji' => '🇵🇰'],
            ['iso2' => 'MV', 'iso3' => 'MDV', 'name_en' => 'Maldives', 'name_th' => 'มัลดีฟส์', 'slug' => 'maldives', 'region' => 'Asia', 'flag_emoji' => '🇲🇻'],
            ['iso2' => 'AF', 'iso3' => 'AFG', 'name_en' => 'Afghanistan', 'name_th' => 'อัฟกานิสถาน', 'slug' => 'afghanistan', 'region' => 'Asia', 'flag_emoji' => '🇦🇫'],

            // Asia - Central
            ['iso2' => 'KZ', 'iso3' => 'KAZ', 'name_en' => 'Kazakhstan', 'name_th' => 'คาซัคสถาน', 'slug' => 'kazakhstan', 'region' => 'Asia', 'flag_emoji' => '🇰🇿'],
            ['iso2' => 'UZ', 'iso3' => 'UZB', 'name_en' => 'Uzbekistan', 'name_th' => 'อุซเบกิสถาน', 'slug' => 'uzbekistan', 'region' => 'Asia', 'flag_emoji' => '🇺🇿'],
            ['iso2' => 'TJ', 'iso3' => 'TJK', 'name_en' => 'Tajikistan', 'name_th' => 'ทาจิกิสถาน', 'slug' => 'tajikistan', 'region' => 'Asia', 'flag_emoji' => '🇹🇯'],
            ['iso2' => 'KG', 'iso3' => 'KGZ', 'name_en' => 'Kyrgyzstan', 'name_th' => 'คีร์กีซสถาน', 'slug' => 'kyrgyzstan', 'region' => 'Asia', 'flag_emoji' => '🇰🇬'],
            ['iso2' => 'TM', 'iso3' => 'TKM', 'name_en' => 'Turkmenistan', 'name_th' => 'เติร์กเมนิสถาน', 'slug' => 'turkmenistan', 'region' => 'Asia', 'flag_emoji' => '🇹🇲'],

            // Asia - West / Middle East
            ['iso2' => 'AE', 'iso3' => 'ARE', 'name_en' => 'United Arab Emirates', 'name_th' => 'สหรัฐอาหรับเอมิเรตส์', 'slug' => 'united-arab-emirates', 'region' => 'Middle East', 'flag_emoji' => '🇦🇪'],
            ['iso2' => 'SA', 'iso3' => 'SAU', 'name_en' => 'Saudi Arabia', 'name_th' => 'ซาอุดีอาระเบีย', 'slug' => 'saudi-arabia', 'region' => 'Middle East', 'flag_emoji' => '🇸🇦'],
            ['iso2' => 'QA', 'iso3' => 'QAT', 'name_en' => 'Qatar', 'name_th' => 'กาตาร์', 'slug' => 'qatar', 'region' => 'Middle East', 'flag_emoji' => '🇶🇦'],
            ['iso2' => 'KW', 'iso3' => 'KWT', 'name_en' => 'Kuwait', 'name_th' => 'คูเวต', 'slug' => 'kuwait', 'region' => 'Middle East', 'flag_emoji' => '🇰🇼'],
            ['iso2' => 'BH', 'iso3' => 'BHR', 'name_en' => 'Bahrain', 'name_th' => 'บาห์เรน', 'slug' => 'bahrain', 'region' => 'Middle East', 'flag_emoji' => '🇧🇭'],
            ['iso2' => 'OM', 'iso3' => 'OMN', 'name_en' => 'Oman', 'name_th' => 'โอมาน', 'slug' => 'oman', 'region' => 'Middle East', 'flag_emoji' => '🇴🇲'],
            ['iso2' => 'JO', 'iso3' => 'JOR', 'name_en' => 'Jordan', 'name_th' => 'จอร์แดน', 'slug' => 'jordan', 'region' => 'Middle East', 'flag_emoji' => '🇯🇴'],
            ['iso2' => 'IL', 'iso3' => 'ISR', 'name_en' => 'Israel', 'name_th' => 'อิสราเอล', 'slug' => 'israel', 'region' => 'Middle East', 'flag_emoji' => '🇮🇱'],
            ['iso2' => 'LB', 'iso3' => 'LBN', 'name_en' => 'Lebanon', 'name_th' => 'เลบานอน', 'slug' => 'lebanon', 'region' => 'Middle East', 'flag_emoji' => '🇱🇧'],
            ['iso2' => 'TR', 'iso3' => 'TUR', 'name_en' => 'Turkey', 'name_th' => 'ตุรกี', 'slug' => 'turkey', 'region' => 'Middle East', 'flag_emoji' => '🇹🇷'],
            ['iso2' => 'IR', 'iso3' => 'IRN', 'name_en' => 'Iran', 'name_th' => 'อิหร่าน', 'slug' => 'iran', 'region' => 'Middle East', 'flag_emoji' => '🇮🇷'],
            ['iso2' => 'IQ', 'iso3' => 'IRQ', 'name_en' => 'Iraq', 'name_th' => 'อิรัก', 'slug' => 'iraq', 'region' => 'Middle East', 'flag_emoji' => '🇮🇶'],
            ['iso2' => 'SY', 'iso3' => 'SYR', 'name_en' => 'Syria', 'name_th' => 'ซีเรีย', 'slug' => 'syria', 'region' => 'Middle East', 'flag_emoji' => '🇸🇾'],
            ['iso2' => 'YE', 'iso3' => 'YEM', 'name_en' => 'Yemen', 'name_th' => 'เยเมน', 'slug' => 'yemen', 'region' => 'Middle East', 'flag_emoji' => '🇾🇪'],
            ['iso2' => 'CY', 'iso3' => 'CYP', 'name_en' => 'Cyprus', 'name_th' => 'ไซปรัส', 'slug' => 'cyprus', 'region' => 'Middle East', 'flag_emoji' => '🇨🇾'],
            ['iso2' => 'GE', 'iso3' => 'GEO', 'name_en' => 'Georgia', 'name_th' => 'จอร์เจีย', 'slug' => 'georgia', 'region' => 'Asia', 'flag_emoji' => '🇬🇪'],
            ['iso2' => 'AM', 'iso3' => 'ARM', 'name_en' => 'Armenia', 'name_th' => 'อาร์เมเนีย', 'slug' => 'armenia', 'region' => 'Asia', 'flag_emoji' => '🇦🇲'],
            ['iso2' => 'AZ', 'iso3' => 'AZE', 'name_en' => 'Azerbaijan', 'name_th' => 'อาเซอร์ไบจาน', 'slug' => 'azerbaijan', 'region' => 'Asia', 'flag_emoji' => '🇦🇿'],

            // Europe - Western
            ['iso2' => 'GB', 'iso3' => 'GBR', 'name_en' => 'United Kingdom', 'name_th' => 'สหราชอาณาจักร', 'slug' => 'united-kingdom', 'region' => 'Europe', 'flag_emoji' => '🇬🇧'],
            ['iso2' => 'FR', 'iso3' => 'FRA', 'name_en' => 'France', 'name_th' => 'ฝรั่งเศส', 'slug' => 'france', 'region' => 'Europe', 'flag_emoji' => '🇫🇷'],
            ['iso2' => 'DE', 'iso3' => 'DEU', 'name_en' => 'Germany', 'name_th' => 'เยอรมนี', 'slug' => 'germany', 'region' => 'Europe', 'flag_emoji' => '🇩🇪'],
            ['iso2' => 'IT', 'iso3' => 'ITA', 'name_en' => 'Italy', 'name_th' => 'อิตาลี', 'slug' => 'italy', 'region' => 'Europe', 'flag_emoji' => '🇮🇹'],
            ['iso2' => 'ES', 'iso3' => 'ESP', 'name_en' => 'Spain', 'name_th' => 'สเปน', 'slug' => 'spain', 'region' => 'Europe', 'flag_emoji' => '🇪🇸'],
            ['iso2' => 'PT', 'iso3' => 'PRT', 'name_en' => 'Portugal', 'name_th' => 'โปรตุเกส', 'slug' => 'portugal', 'region' => 'Europe', 'flag_emoji' => '🇵🇹'],
            ['iso2' => 'NL', 'iso3' => 'NLD', 'name_en' => 'Netherlands', 'name_th' => 'เนเธอร์แลนด์', 'slug' => 'netherlands', 'region' => 'Europe', 'flag_emoji' => '🇳🇱'],
            ['iso2' => 'BE', 'iso3' => 'BEL', 'name_en' => 'Belgium', 'name_th' => 'เบลเยียม', 'slug' => 'belgium', 'region' => 'Europe', 'flag_emoji' => '🇧🇪'],
            ['iso2' => 'LU', 'iso3' => 'LUX', 'name_en' => 'Luxembourg', 'name_th' => 'ลักเซมเบิร์ก', 'slug' => 'luxembourg', 'region' => 'Europe', 'flag_emoji' => '🇱🇺'],
            ['iso2' => 'CH', 'iso3' => 'CHE', 'name_en' => 'Switzerland', 'name_th' => 'สวิตเซอร์แลนด์', 'slug' => 'switzerland', 'region' => 'Europe', 'flag_emoji' => '🇨🇭'],
            ['iso2' => 'AT', 'iso3' => 'AUT', 'name_en' => 'Austria', 'name_th' => 'ออสเตรีย', 'slug' => 'austria', 'region' => 'Europe', 'flag_emoji' => '🇦🇹'],
            ['iso2' => 'IE', 'iso3' => 'IRL', 'name_en' => 'Ireland', 'name_th' => 'ไอร์แลนด์', 'slug' => 'ireland', 'region' => 'Europe', 'flag_emoji' => '🇮🇪'],
            ['iso2' => 'MC', 'iso3' => 'MCO', 'name_en' => 'Monaco', 'name_th' => 'โมนาโก', 'slug' => 'monaco', 'region' => 'Europe', 'flag_emoji' => '🇲🇨'],
            ['iso2' => 'LI', 'iso3' => 'LIE', 'name_en' => 'Liechtenstein', 'name_th' => 'ลิกเตนสไตน์', 'slug' => 'liechtenstein', 'region' => 'Europe', 'flag_emoji' => '🇱🇮'],
            ['iso2' => 'AD', 'iso3' => 'AND', 'name_en' => 'Andorra', 'name_th' => 'อันดอร์รา', 'slug' => 'andorra', 'region' => 'Europe', 'flag_emoji' => '🇦🇩'],

            // Europe - Northern
            ['iso2' => 'SE', 'iso3' => 'SWE', 'name_en' => 'Sweden', 'name_th' => 'สวีเดน', 'slug' => 'sweden', 'region' => 'Europe', 'flag_emoji' => '🇸🇪'],
            ['iso2' => 'NO', 'iso3' => 'NOR', 'name_en' => 'Norway', 'name_th' => 'นอร์เวย์', 'slug' => 'norway', 'region' => 'Europe', 'flag_emoji' => '🇳🇴'],
            ['iso2' => 'DK', 'iso3' => 'DNK', 'name_en' => 'Denmark', 'name_th' => 'เดนมาร์ก', 'slug' => 'denmark', 'region' => 'Europe', 'flag_emoji' => '🇩🇰'],
            ['iso2' => 'FI', 'iso3' => 'FIN', 'name_en' => 'Finland', 'name_th' => 'ฟินแลนด์', 'slug' => 'finland', 'region' => 'Europe', 'flag_emoji' => '🇫🇮'],
            ['iso2' => 'IS', 'iso3' => 'ISL', 'name_en' => 'Iceland', 'name_th' => 'ไอซ์แลนด์', 'slug' => 'iceland', 'region' => 'Europe', 'flag_emoji' => '🇮🇸'],
            ['iso2' => 'EE', 'iso3' => 'EST', 'name_en' => 'Estonia', 'name_th' => 'เอสโตเนีย', 'slug' => 'estonia', 'region' => 'Europe', 'flag_emoji' => '🇪🇪'],
            ['iso2' => 'LV', 'iso3' => 'LVA', 'name_en' => 'Latvia', 'name_th' => 'ลัตเวีย', 'slug' => 'latvia', 'region' => 'Europe', 'flag_emoji' => '🇱🇻'],
            ['iso2' => 'LT', 'iso3' => 'LTU', 'name_en' => 'Lithuania', 'name_th' => 'ลิทัวเนีย', 'slug' => 'lithuania', 'region' => 'Europe', 'flag_emoji' => '🇱🇹'],

            // Europe - Eastern
            ['iso2' => 'RU', 'iso3' => 'RUS', 'name_en' => 'Russia', 'name_th' => 'รัสเซีย', 'slug' => 'russia', 'region' => 'Europe', 'flag_emoji' => '🇷🇺'],
            ['iso2' => 'PL', 'iso3' => 'POL', 'name_en' => 'Poland', 'name_th' => 'โปแลนด์', 'slug' => 'poland', 'region' => 'Europe', 'flag_emoji' => '🇵🇱'],
            ['iso2' => 'CZ', 'iso3' => 'CZE', 'name_en' => 'Czech Republic', 'name_th' => 'สาธารณรัฐเช็ก', 'slug' => 'czech-republic', 'region' => 'Europe', 'flag_emoji' => '🇨🇿'],
            ['iso2' => 'SK', 'iso3' => 'SVK', 'name_en' => 'Slovakia', 'name_th' => 'สโลวาเกีย', 'slug' => 'slovakia', 'region' => 'Europe', 'flag_emoji' => '🇸🇰'],
            ['iso2' => 'HU', 'iso3' => 'HUN', 'name_en' => 'Hungary', 'name_th' => 'ฮังการี', 'slug' => 'hungary', 'region' => 'Europe', 'flag_emoji' => '🇭🇺'],
            ['iso2' => 'RO', 'iso3' => 'ROU', 'name_en' => 'Romania', 'name_th' => 'โรมาเนีย', 'slug' => 'romania', 'region' => 'Europe', 'flag_emoji' => '🇷🇴'],
            ['iso2' => 'BG', 'iso3' => 'BGR', 'name_en' => 'Bulgaria', 'name_th' => 'บัลแกเรีย', 'slug' => 'bulgaria', 'region' => 'Europe', 'flag_emoji' => '🇧🇬'],
            ['iso2' => 'UA', 'iso3' => 'UKR', 'name_en' => 'Ukraine', 'name_th' => 'ยูเครน', 'slug' => 'ukraine', 'region' => 'Europe', 'flag_emoji' => '🇺🇦'],
            ['iso2' => 'BY', 'iso3' => 'BLR', 'name_en' => 'Belarus', 'name_th' => 'เบลารุส', 'slug' => 'belarus', 'region' => 'Europe', 'flag_emoji' => '🇧🇾'],
            ['iso2' => 'MD', 'iso3' => 'MDA', 'name_en' => 'Moldova', 'name_th' => 'มอลโดวา', 'slug' => 'moldova', 'region' => 'Europe', 'flag_emoji' => '🇲🇩'],

            // Europe - Southern / Balkans
            ['iso2' => 'GR', 'iso3' => 'GRC', 'name_en' => 'Greece', 'name_th' => 'กรีซ', 'slug' => 'greece', 'region' => 'Europe', 'flag_emoji' => '🇬🇷'],
            ['iso2' => 'HR', 'iso3' => 'HRV', 'name_en' => 'Croatia', 'name_th' => 'โครเอเชีย', 'slug' => 'croatia', 'region' => 'Europe', 'flag_emoji' => '🇭🇷'],
            ['iso2' => 'SI', 'iso3' => 'SVN', 'name_en' => 'Slovenia', 'name_th' => 'สโลวีเนีย', 'slug' => 'slovenia', 'region' => 'Europe', 'flag_emoji' => '🇸🇮'],
            ['iso2' => 'RS', 'iso3' => 'SRB', 'name_en' => 'Serbia', 'name_th' => 'เซอร์เบีย', 'slug' => 'serbia', 'region' => 'Europe', 'flag_emoji' => '🇷🇸'],
            ['iso2' => 'BA', 'iso3' => 'BIH', 'name_en' => 'Bosnia and Herzegovina', 'name_th' => 'บอสเนียและเฮอร์เซโกวีนา', 'slug' => 'bosnia-and-herzegovina', 'region' => 'Europe', 'flag_emoji' => '🇧🇦'],
            ['iso2' => 'ME', 'iso3' => 'MNE', 'name_en' => 'Montenegro', 'name_th' => 'มอนเตเนโกร', 'slug' => 'montenegro', 'region' => 'Europe', 'flag_emoji' => '🇲🇪'],
            ['iso2' => 'MK', 'iso3' => 'MKD', 'name_en' => 'North Macedonia', 'name_th' => 'นอร์ทมาซิโดเนีย', 'slug' => 'north-macedonia', 'region' => 'Europe', 'flag_emoji' => '🇲🇰'],
            ['iso2' => 'AL', 'iso3' => 'ALB', 'name_en' => 'Albania', 'name_th' => 'แอลเบเนีย', 'slug' => 'albania', 'region' => 'Europe', 'flag_emoji' => '🇦🇱'],
            ['iso2' => 'XK', 'iso3' => 'XKX', 'name_en' => 'Kosovo', 'name_th' => 'โคโซโว', 'slug' => 'kosovo', 'region' => 'Europe', 'flag_emoji' => '🇽🇰'],
            ['iso2' => 'MT', 'iso3' => 'MLT', 'name_en' => 'Malta', 'name_th' => 'มอลตา', 'slug' => 'malta', 'region' => 'Europe', 'flag_emoji' => '🇲🇹'],
            ['iso2' => 'SM', 'iso3' => 'SMR', 'name_en' => 'San Marino', 'name_th' => 'ซานมารีโน', 'slug' => 'san-marino', 'region' => 'Europe', 'flag_emoji' => '🇸🇲'],
            ['iso2' => 'VA', 'iso3' => 'VAT', 'name_en' => 'Vatican City', 'name_th' => 'นครรัฐวาติกัน', 'slug' => 'vatican-city', 'region' => 'Europe', 'flag_emoji' => '🇻🇦'],

            // North America
            ['iso2' => 'US', 'iso3' => 'USA', 'name_en' => 'United States', 'name_th' => 'สหรัฐอเมริกา', 'slug' => 'united-states', 'region' => 'North America', 'flag_emoji' => '🇺🇸'],
            ['iso2' => 'CA', 'iso3' => 'CAN', 'name_en' => 'Canada', 'name_th' => 'แคนาดา', 'slug' => 'canada', 'region' => 'North America', 'flag_emoji' => '🇨🇦'],
            ['iso2' => 'MX', 'iso3' => 'MEX', 'name_en' => 'Mexico', 'name_th' => 'เม็กซิโก', 'slug' => 'mexico', 'region' => 'North America', 'flag_emoji' => '🇲🇽'],
            ['iso2' => 'GT', 'iso3' => 'GTM', 'name_en' => 'Guatemala', 'name_th' => 'กัวเตมาลา', 'slug' => 'guatemala', 'region' => 'North America', 'flag_emoji' => '🇬🇹'],
            ['iso2' => 'BZ', 'iso3' => 'BLZ', 'name_en' => 'Belize', 'name_th' => 'เบลีซ', 'slug' => 'belize', 'region' => 'North America', 'flag_emoji' => '🇧🇿'],
            ['iso2' => 'HN', 'iso3' => 'HND', 'name_en' => 'Honduras', 'name_th' => 'ฮอนดูรัส', 'slug' => 'honduras', 'region' => 'North America', 'flag_emoji' => '🇭🇳'],
            ['iso2' => 'SV', 'iso3' => 'SLV', 'name_en' => 'El Salvador', 'name_th' => 'เอลซัลวาดอร์', 'slug' => 'el-salvador', 'region' => 'North America', 'flag_emoji' => '🇸🇻'],
            ['iso2' => 'NI', 'iso3' => 'NIC', 'name_en' => 'Nicaragua', 'name_th' => 'นิการากัว', 'slug' => 'nicaragua', 'region' => 'North America', 'flag_emoji' => '🇳🇮'],
            ['iso2' => 'CR', 'iso3' => 'CRI', 'name_en' => 'Costa Rica', 'name_th' => 'คอสตาริกา', 'slug' => 'costa-rica', 'region' => 'North America', 'flag_emoji' => '🇨🇷'],
            ['iso2' => 'PA', 'iso3' => 'PAN', 'name_en' => 'Panama', 'name_th' => 'ปานามา', 'slug' => 'panama', 'region' => 'North America', 'flag_emoji' => '🇵🇦'],

            // Caribbean
            ['iso2' => 'CU', 'iso3' => 'CUB', 'name_en' => 'Cuba', 'name_th' => 'คิวบา', 'slug' => 'cuba', 'region' => 'Caribbean', 'flag_emoji' => '🇨🇺'],
            ['iso2' => 'JM', 'iso3' => 'JAM', 'name_en' => 'Jamaica', 'name_th' => 'จาเมกา', 'slug' => 'jamaica', 'region' => 'Caribbean', 'flag_emoji' => '🇯🇲'],
            ['iso2' => 'HT', 'iso3' => 'HTI', 'name_en' => 'Haiti', 'name_th' => 'เฮติ', 'slug' => 'haiti', 'region' => 'Caribbean', 'flag_emoji' => '🇭🇹'],
            ['iso2' => 'DO', 'iso3' => 'DOM', 'name_en' => 'Dominican Republic', 'name_th' => 'สาธารณรัฐโดมินิกัน', 'slug' => 'dominican-republic', 'region' => 'Caribbean', 'flag_emoji' => '🇩🇴'],
            ['iso2' => 'PR', 'iso3' => 'PRI', 'name_en' => 'Puerto Rico', 'name_th' => 'เปอร์โตริโก', 'slug' => 'puerto-rico', 'region' => 'Caribbean', 'flag_emoji' => '🇵🇷'],
            ['iso2' => 'BS', 'iso3' => 'BHS', 'name_en' => 'Bahamas', 'name_th' => 'บาฮามาส', 'slug' => 'bahamas', 'region' => 'Caribbean', 'flag_emoji' => '🇧🇸'],
            ['iso2' => 'BB', 'iso3' => 'BRB', 'name_en' => 'Barbados', 'name_th' => 'บาร์เบโดส', 'slug' => 'barbados', 'region' => 'Caribbean', 'flag_emoji' => '🇧🇧'],
            ['iso2' => 'TT', 'iso3' => 'TTO', 'name_en' => 'Trinidad and Tobago', 'name_th' => 'ตรินิแดดและโตเบโก', 'slug' => 'trinidad-and-tobago', 'region' => 'Caribbean', 'flag_emoji' => '🇹🇹'],
            ['iso2' => 'LC', 'iso3' => 'LCA', 'name_en' => 'Saint Lucia', 'name_th' => 'เซนต์ลูเซีย', 'slug' => 'saint-lucia', 'region' => 'Caribbean', 'flag_emoji' => '🇱🇨'],
            ['iso2' => 'VC', 'iso3' => 'VCT', 'name_en' => 'Saint Vincent and the Grenadines', 'name_th' => 'เซนต์วินเซนต์และเกรนาดีนส์', 'slug' => 'saint-vincent-and-the-grenadines', 'region' => 'Caribbean', 'flag_emoji' => '🇻🇨'],
            ['iso2' => 'GD', 'iso3' => 'GRD', 'name_en' => 'Grenada', 'name_th' => 'เกรเนดา', 'slug' => 'grenada', 'region' => 'Caribbean', 'flag_emoji' => '🇬🇩'],
            ['iso2' => 'AG', 'iso3' => 'ATG', 'name_en' => 'Antigua and Barbuda', 'name_th' => 'แอนติกาและบาร์บูดา', 'slug' => 'antigua-and-barbuda', 'region' => 'Caribbean', 'flag_emoji' => '🇦🇬'],
            ['iso2' => 'DM', 'iso3' => 'DMA', 'name_en' => 'Dominica', 'name_th' => 'โดมินิกา', 'slug' => 'dominica', 'region' => 'Caribbean', 'flag_emoji' => '🇩🇲'],
            ['iso2' => 'KN', 'iso3' => 'KNA', 'name_en' => 'Saint Kitts and Nevis', 'name_th' => 'เซนต์คิตส์และเนวิส', 'slug' => 'saint-kitts-and-nevis', 'region' => 'Caribbean', 'flag_emoji' => '🇰🇳'],

            // South America
            ['iso2' => 'BR', 'iso3' => 'BRA', 'name_en' => 'Brazil', 'name_th' => 'บราซิล', 'slug' => 'brazil', 'region' => 'South America', 'flag_emoji' => '🇧🇷'],
            ['iso2' => 'AR', 'iso3' => 'ARG', 'name_en' => 'Argentina', 'name_th' => 'อาร์เจนตินา', 'slug' => 'argentina', 'region' => 'South America', 'flag_emoji' => '🇦🇷'],
            ['iso2' => 'CL', 'iso3' => 'CHL', 'name_en' => 'Chile', 'name_th' => 'ชิลี', 'slug' => 'chile', 'region' => 'South America', 'flag_emoji' => '🇨🇱'],
            ['iso2' => 'PE', 'iso3' => 'PER', 'name_en' => 'Peru', 'name_th' => 'เปรู', 'slug' => 'peru', 'region' => 'South America', 'flag_emoji' => '🇵🇪'],
            ['iso2' => 'CO', 'iso3' => 'COL', 'name_en' => 'Colombia', 'name_th' => 'โคลอมเบีย', 'slug' => 'colombia', 'region' => 'South America', 'flag_emoji' => '🇨🇴'],
            ['iso2' => 'VE', 'iso3' => 'VEN', 'name_en' => 'Venezuela', 'name_th' => 'เวเนซุเอลา', 'slug' => 'venezuela', 'region' => 'South America', 'flag_emoji' => '🇻🇪'],
            ['iso2' => 'EC', 'iso3' => 'ECU', 'name_en' => 'Ecuador', 'name_th' => 'เอกวาดอร์', 'slug' => 'ecuador', 'region' => 'South America', 'flag_emoji' => '🇪🇨'],
            ['iso2' => 'BO', 'iso3' => 'BOL', 'name_en' => 'Bolivia', 'name_th' => 'โบลิเวีย', 'slug' => 'bolivia', 'region' => 'South America', 'flag_emoji' => '🇧🇴'],
            ['iso2' => 'PY', 'iso3' => 'PRY', 'name_en' => 'Paraguay', 'name_th' => 'ปารากวัย', 'slug' => 'paraguay', 'region' => 'South America', 'flag_emoji' => '🇵🇾'],
            ['iso2' => 'UY', 'iso3' => 'URY', 'name_en' => 'Uruguay', 'name_th' => 'อุรุกวัย', 'slug' => 'uruguay', 'region' => 'South America', 'flag_emoji' => '🇺🇾'],
            ['iso2' => 'GY', 'iso3' => 'GUY', 'name_en' => 'Guyana', 'name_th' => 'กายอานา', 'slug' => 'guyana', 'region' => 'South America', 'flag_emoji' => '🇬🇾'],
            ['iso2' => 'SR', 'iso3' => 'SUR', 'name_en' => 'Suriname', 'name_th' => 'ซูรินาเม', 'slug' => 'suriname', 'region' => 'South America', 'flag_emoji' => '🇸🇷'],
            ['iso2' => 'GF', 'iso3' => 'GUF', 'name_en' => 'French Guiana', 'name_th' => 'เฟรนช์เกียนา', 'slug' => 'french-guiana', 'region' => 'South America', 'flag_emoji' => '🇬🇫'],

            // Africa - North
            ['iso2' => 'EG', 'iso3' => 'EGY', 'name_en' => 'Egypt', 'name_th' => 'อียิปต์', 'slug' => 'egypt', 'region' => 'Africa', 'flag_emoji' => '🇪🇬'],
            ['iso2' => 'MA', 'iso3' => 'MAR', 'name_en' => 'Morocco', 'name_th' => 'โมร็อกโก', 'slug' => 'morocco', 'region' => 'Africa', 'flag_emoji' => '🇲🇦'],
            ['iso2' => 'TN', 'iso3' => 'TUN', 'name_en' => 'Tunisia', 'name_th' => 'ตูนิเซีย', 'slug' => 'tunisia', 'region' => 'Africa', 'flag_emoji' => '🇹🇳'],
            ['iso2' => 'DZ', 'iso3' => 'DZA', 'name_en' => 'Algeria', 'name_th' => 'แอลจีเรีย', 'slug' => 'algeria', 'region' => 'Africa', 'flag_emoji' => '🇩🇿'],
            ['iso2' => 'LY', 'iso3' => 'LBY', 'name_en' => 'Libya', 'name_th' => 'ลิเบีย', 'slug' => 'libya', 'region' => 'Africa', 'flag_emoji' => '🇱🇾'],
            ['iso2' => 'SD', 'iso3' => 'SDN', 'name_en' => 'Sudan', 'name_th' => 'ซูดาน', 'slug' => 'sudan', 'region' => 'Africa', 'flag_emoji' => '🇸🇩'],

            // Africa - East
            ['iso2' => 'KE', 'iso3' => 'KEN', 'name_en' => 'Kenya', 'name_th' => 'เคนยา', 'slug' => 'kenya', 'region' => 'Africa', 'flag_emoji' => '🇰🇪'],
            ['iso2' => 'TZ', 'iso3' => 'TZA', 'name_en' => 'Tanzania', 'name_th' => 'แทนซาเนีย', 'slug' => 'tanzania', 'region' => 'Africa', 'flag_emoji' => '🇹🇿'],
            ['iso2' => 'UG', 'iso3' => 'UGA', 'name_en' => 'Uganda', 'name_th' => 'ยูกันดา', 'slug' => 'uganda', 'region' => 'Africa', 'flag_emoji' => '🇺🇬'],
            ['iso2' => 'RW', 'iso3' => 'RWA', 'name_en' => 'Rwanda', 'name_th' => 'รวันดา', 'slug' => 'rwanda', 'region' => 'Africa', 'flag_emoji' => '🇷🇼'],
            ['iso2' => 'ET', 'iso3' => 'ETH', 'name_en' => 'Ethiopia', 'name_th' => 'เอธิโอเปีย', 'slug' => 'ethiopia', 'region' => 'Africa', 'flag_emoji' => '🇪🇹'],
            ['iso2' => 'SO', 'iso3' => 'SOM', 'name_en' => 'Somalia', 'name_th' => 'โซมาเลีย', 'slug' => 'somalia', 'region' => 'Africa', 'flag_emoji' => '🇸🇴'],
            ['iso2' => 'DJ', 'iso3' => 'DJI', 'name_en' => 'Djibouti', 'name_th' => 'จิบูตี', 'slug' => 'djibouti', 'region' => 'Africa', 'flag_emoji' => '🇩🇯'],
            ['iso2' => 'ER', 'iso3' => 'ERI', 'name_en' => 'Eritrea', 'name_th' => 'เอริเทรีย', 'slug' => 'eritrea', 'region' => 'Africa', 'flag_emoji' => '🇪🇷'],
            ['iso2' => 'SS', 'iso3' => 'SSD', 'name_en' => 'South Sudan', 'name_th' => 'ซูดานใต้', 'slug' => 'south-sudan', 'region' => 'Africa', 'flag_emoji' => '🇸🇸'],
            ['iso2' => 'BI', 'iso3' => 'BDI', 'name_en' => 'Burundi', 'name_th' => 'บุรุนดี', 'slug' => 'burundi', 'region' => 'Africa', 'flag_emoji' => '🇧🇮'],
            ['iso2' => 'MG', 'iso3' => 'MDG', 'name_en' => 'Madagascar', 'name_th' => 'มาดากัสการ์', 'slug' => 'madagascar', 'region' => 'Africa', 'flag_emoji' => '🇲🇬'],
            ['iso2' => 'MU', 'iso3' => 'MUS', 'name_en' => 'Mauritius', 'name_th' => 'มอริเชียส', 'slug' => 'mauritius', 'region' => 'Africa', 'flag_emoji' => '🇲🇺'],
            ['iso2' => 'SC', 'iso3' => 'SYC', 'name_en' => 'Seychelles', 'name_th' => 'เซเชลส์', 'slug' => 'seychelles', 'region' => 'Africa', 'flag_emoji' => '🇸🇨'],
            ['iso2' => 'KM', 'iso3' => 'COM', 'name_en' => 'Comoros', 'name_th' => 'คอโมโรส', 'slug' => 'comoros', 'region' => 'Africa', 'flag_emoji' => '🇰🇲'],

            // Africa - West
            ['iso2' => 'NG', 'iso3' => 'NGA', 'name_en' => 'Nigeria', 'name_th' => 'ไนจีเรีย', 'slug' => 'nigeria', 'region' => 'Africa', 'flag_emoji' => '🇳🇬'],
            ['iso2' => 'GH', 'iso3' => 'GHA', 'name_en' => 'Ghana', 'name_th' => 'กานา', 'slug' => 'ghana', 'region' => 'Africa', 'flag_emoji' => '🇬🇭'],
            ['iso2' => 'CI', 'iso3' => 'CIV', 'name_en' => "Côte d'Ivoire", 'name_th' => 'โกตดิวัวร์', 'slug' => 'cote-divoire', 'region' => 'Africa', 'flag_emoji' => '🇨🇮'],
            ['iso2' => 'SN', 'iso3' => 'SEN', 'name_en' => 'Senegal', 'name_th' => 'เซเนกัล', 'slug' => 'senegal', 'region' => 'Africa', 'flag_emoji' => '🇸🇳'],
            ['iso2' => 'ML', 'iso3' => 'MLI', 'name_en' => 'Mali', 'name_th' => 'มาลี', 'slug' => 'mali', 'region' => 'Africa', 'flag_emoji' => '🇲🇱'],
            ['iso2' => 'BF', 'iso3' => 'BFA', 'name_en' => 'Burkina Faso', 'name_th' => 'บูร์กินาฟาโซ', 'slug' => 'burkina-faso', 'region' => 'Africa', 'flag_emoji' => '🇧🇫'],
            ['iso2' => 'NE', 'iso3' => 'NER', 'name_en' => 'Niger', 'name_th' => 'ไนเจอร์', 'slug' => 'niger', 'region' => 'Africa', 'flag_emoji' => '🇳🇪'],
            ['iso2' => 'GN', 'iso3' => 'GIN', 'name_en' => 'Guinea', 'name_th' => 'กินี', 'slug' => 'guinea', 'region' => 'Africa', 'flag_emoji' => '🇬🇳'],
            ['iso2' => 'BJ', 'iso3' => 'BEN', 'name_en' => 'Benin', 'name_th' => 'เบนิน', 'slug' => 'benin', 'region' => 'Africa', 'flag_emoji' => '🇧🇯'],
            ['iso2' => 'TG', 'iso3' => 'TGO', 'name_en' => 'Togo', 'name_th' => 'โตโก', 'slug' => 'togo', 'region' => 'Africa', 'flag_emoji' => '🇹🇬'],
            ['iso2' => 'SL', 'iso3' => 'SLE', 'name_en' => 'Sierra Leone', 'name_th' => 'เซียร์ราลีโอน', 'slug' => 'sierra-leone', 'region' => 'Africa', 'flag_emoji' => '🇸🇱'],
            ['iso2' => 'LR', 'iso3' => 'LBR', 'name_en' => 'Liberia', 'name_th' => 'ไลบีเรีย', 'slug' => 'liberia', 'region' => 'Africa', 'flag_emoji' => '🇱🇷'],
            ['iso2' => 'MR', 'iso3' => 'MRT', 'name_en' => 'Mauritania', 'name_th' => 'มอริเตเนีย', 'slug' => 'mauritania', 'region' => 'Africa', 'flag_emoji' => '🇲🇷'],
            ['iso2' => 'GM', 'iso3' => 'GMB', 'name_en' => 'Gambia', 'name_th' => 'แกมเบีย', 'slug' => 'gambia', 'region' => 'Africa', 'flag_emoji' => '🇬🇲'],
            ['iso2' => 'GW', 'iso3' => 'GNB', 'name_en' => 'Guinea-Bissau', 'name_th' => 'กินี-บิสเซา', 'slug' => 'guinea-bissau', 'region' => 'Africa', 'flag_emoji' => '🇬🇼'],
            ['iso2' => 'CV', 'iso3' => 'CPV', 'name_en' => 'Cabo Verde', 'name_th' => 'กาบูเวร์ดี', 'slug' => 'cabo-verde', 'region' => 'Africa', 'flag_emoji' => '🇨🇻'],

            // Africa - Central
            ['iso2' => 'CD', 'iso3' => 'COD', 'name_en' => 'Democratic Republic of the Congo', 'name_th' => 'สาธารณรัฐประชาธิปไตยคองโก', 'slug' => 'democratic-republic-of-the-congo', 'region' => 'Africa', 'flag_emoji' => '🇨🇩'],
            ['iso2' => 'CG', 'iso3' => 'COG', 'name_en' => 'Republic of the Congo', 'name_th' => 'สาธารณรัฐคองโก', 'slug' => 'republic-of-the-congo', 'region' => 'Africa', 'flag_emoji' => '🇨🇬'],
            ['iso2' => 'CM', 'iso3' => 'CMR', 'name_en' => 'Cameroon', 'name_th' => 'แคเมอรูน', 'slug' => 'cameroon', 'region' => 'Africa', 'flag_emoji' => '🇨🇲'],
            ['iso2' => 'CF', 'iso3' => 'CAF', 'name_en' => 'Central African Republic', 'name_th' => 'สาธารณรัฐแอฟริกากลาง', 'slug' => 'central-african-republic', 'region' => 'Africa', 'flag_emoji' => '🇨🇫'],
            ['iso2' => 'TD', 'iso3' => 'TCD', 'name_en' => 'Chad', 'name_th' => 'ชาด', 'slug' => 'chad', 'region' => 'Africa', 'flag_emoji' => '🇹🇩'],
            ['iso2' => 'GA', 'iso3' => 'GAB', 'name_en' => 'Gabon', 'name_th' => 'กาบอง', 'slug' => 'gabon', 'region' => 'Africa', 'flag_emoji' => '🇬🇦'],
            ['iso2' => 'GQ', 'iso3' => 'GNQ', 'name_en' => 'Equatorial Guinea', 'name_th' => 'อิเควทอเรียลกินี', 'slug' => 'equatorial-guinea', 'region' => 'Africa', 'flag_emoji' => '🇬🇶'],
            ['iso2' => 'ST', 'iso3' => 'STP', 'name_en' => 'São Tomé and Príncipe', 'name_th' => 'เซาตูเมและปรินซีปี', 'slug' => 'sao-tome-and-principe', 'region' => 'Africa', 'flag_emoji' => '🇸🇹'],
            ['iso2' => 'AO', 'iso3' => 'AGO', 'name_en' => 'Angola', 'name_th' => 'แองโกลา', 'slug' => 'angola', 'region' => 'Africa', 'flag_emoji' => '🇦🇴'],

            // Africa - Southern
            ['iso2' => 'ZA', 'iso3' => 'ZAF', 'name_en' => 'South Africa', 'name_th' => 'แอฟริกาใต้', 'slug' => 'south-africa', 'region' => 'Africa', 'flag_emoji' => '🇿🇦'],
            ['iso2' => 'NA', 'iso3' => 'NAM', 'name_en' => 'Namibia', 'name_th' => 'นามิเบีย', 'slug' => 'namibia', 'region' => 'Africa', 'flag_emoji' => '🇳🇦'],
            ['iso2' => 'BW', 'iso3' => 'BWA', 'name_en' => 'Botswana', 'name_th' => 'บอตสวานา', 'slug' => 'botswana', 'region' => 'Africa', 'flag_emoji' => '🇧🇼'],
            ['iso2' => 'ZW', 'iso3' => 'ZWE', 'name_en' => 'Zimbabwe', 'name_th' => 'ซิมบับเว', 'slug' => 'zimbabwe', 'region' => 'Africa', 'flag_emoji' => '🇿🇼'],
            ['iso2' => 'ZM', 'iso3' => 'ZMB', 'name_en' => 'Zambia', 'name_th' => 'แซมเบีย', 'slug' => 'zambia', 'region' => 'Africa', 'flag_emoji' => '🇿🇲'],
            ['iso2' => 'MW', 'iso3' => 'MWI', 'name_en' => 'Malawi', 'name_th' => 'มาลาวี', 'slug' => 'malawi', 'region' => 'Africa', 'flag_emoji' => '🇲🇼'],
            ['iso2' => 'MZ', 'iso3' => 'MOZ', 'name_en' => 'Mozambique', 'name_th' => 'โมซัมบิก', 'slug' => 'mozambique', 'region' => 'Africa', 'flag_emoji' => '🇲🇿'],
            ['iso2' => 'SZ', 'iso3' => 'SWZ', 'name_en' => 'Eswatini', 'name_th' => 'เอสวาตีนี', 'slug' => 'eswatini', 'region' => 'Africa', 'flag_emoji' => '🇸🇿'],
            ['iso2' => 'LS', 'iso3' => 'LSO', 'name_en' => 'Lesotho', 'name_th' => 'เลโซโท', 'slug' => 'lesotho', 'region' => 'Africa', 'flag_emoji' => '🇱🇸'],

            // Oceania
            ['iso2' => 'AU', 'iso3' => 'AUS', 'name_en' => 'Australia', 'name_th' => 'ออสเตรเลีย', 'slug' => 'australia', 'region' => 'Oceania', 'flag_emoji' => '🇦🇺'],
            ['iso2' => 'NZ', 'iso3' => 'NZL', 'name_en' => 'New Zealand', 'name_th' => 'นิวซีแลนด์', 'slug' => 'new-zealand', 'region' => 'Oceania', 'flag_emoji' => '🇳🇿'],
            ['iso2' => 'FJ', 'iso3' => 'FJI', 'name_en' => 'Fiji', 'name_th' => 'ฟิจิ', 'slug' => 'fiji', 'region' => 'Oceania', 'flag_emoji' => '🇫🇯'],
            ['iso2' => 'PG', 'iso3' => 'PNG', 'name_en' => 'Papua New Guinea', 'name_th' => 'ปาปัวนิวกินี', 'slug' => 'papua-new-guinea', 'region' => 'Oceania', 'flag_emoji' => '🇵🇬'],
            ['iso2' => 'SB', 'iso3' => 'SLB', 'name_en' => 'Solomon Islands', 'name_th' => 'หมู่เกาะโซโลมอน', 'slug' => 'solomon-islands', 'region' => 'Oceania', 'flag_emoji' => '🇸🇧'],
            ['iso2' => 'VU', 'iso3' => 'VUT', 'name_en' => 'Vanuatu', 'name_th' => 'วานูอาตู', 'slug' => 'vanuatu', 'region' => 'Oceania', 'flag_emoji' => '🇻🇺'],
            ['iso2' => 'NC', 'iso3' => 'NCL', 'name_en' => 'New Caledonia', 'name_th' => 'นิวแคลิโดเนีย', 'slug' => 'new-caledonia', 'region' => 'Oceania', 'flag_emoji' => '🇳🇨'],
            ['iso2' => 'PF', 'iso3' => 'PYF', 'name_en' => 'French Polynesia', 'name_th' => 'เฟรนช์โปลินีเซีย', 'slug' => 'french-polynesia', 'region' => 'Oceania', 'flag_emoji' => '🇵🇫'],
            ['iso2' => 'WS', 'iso3' => 'WSM', 'name_en' => 'Samoa', 'name_th' => 'ซามัว', 'slug' => 'samoa', 'region' => 'Oceania', 'flag_emoji' => '🇼🇸'],
            ['iso2' => 'TO', 'iso3' => 'TON', 'name_en' => 'Tonga', 'name_th' => 'ตองกา', 'slug' => 'tonga', 'region' => 'Oceania', 'flag_emoji' => '🇹🇴'],
            ['iso2' => 'KI', 'iso3' => 'KIR', 'name_en' => 'Kiribati', 'name_th' => 'คิริบาส', 'slug' => 'kiribati', 'region' => 'Oceania', 'flag_emoji' => '🇰🇮'],
            ['iso2' => 'FM', 'iso3' => 'FSM', 'name_en' => 'Micronesia', 'name_th' => 'ไมโครนีเซีย', 'slug' => 'micronesia', 'region' => 'Oceania', 'flag_emoji' => '🇫🇲'],
            ['iso2' => 'MH', 'iso3' => 'MHL', 'name_en' => 'Marshall Islands', 'name_th' => 'หมู่เกาะมาร์แชลล์', 'slug' => 'marshall-islands', 'region' => 'Oceania', 'flag_emoji' => '🇲🇭'],
            ['iso2' => 'PW', 'iso3' => 'PLW', 'name_en' => 'Palau', 'name_th' => 'ปาเลา', 'slug' => 'palau', 'region' => 'Oceania', 'flag_emoji' => '🇵🇼'],
            ['iso2' => 'NR', 'iso3' => 'NRU', 'name_en' => 'Nauru', 'name_th' => 'นาอูรู', 'slug' => 'nauru', 'region' => 'Oceania', 'flag_emoji' => '🇳🇷'],
            ['iso2' => 'TV', 'iso3' => 'TUV', 'name_en' => 'Tuvalu', 'name_th' => 'ตูวาลู', 'slug' => 'tuvalu', 'region' => 'Oceania', 'flag_emoji' => '🇹🇻'],
            ['iso2' => 'GU', 'iso3' => 'GUM', 'name_en' => 'Guam', 'name_th' => 'กวม', 'slug' => 'guam', 'region' => 'Oceania', 'flag_emoji' => '🇬🇺'],
        ];

        $now = now();
        foreach ($countries as &$country) {
            $country['is_active'] = true;
            $country['created_at'] = $now;
            $country['updated_at'] = $now;
        }

        // Insert in chunks to avoid memory issues
        foreach (array_chunk($countries, 50) as $chunk) {
            DB::table('countries')->insert($chunk);
        }
    }
}
