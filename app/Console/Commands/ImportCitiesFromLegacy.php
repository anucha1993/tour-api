<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Country;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportCitiesFromLegacy extends Command
{
    protected $signature = 'cities:import-legacy {--dry-run : Show what would be imported without actually importing}';
    protected $description = 'Import cities from legacy tb_city.sql file';

    // Legacy country_id => ISO2 mapping (จาก tb_country เดิม)
    // Mapping นี้อ้างอิงจากข้อมูลตัวอย่างใน tb_city.sql
    private array $countryMapping = [
        1 => 'AF',    // Afghanistan (Ghazni)
        3 => 'AL',    // Albania (Skrapar District)
        4 => 'DZ',    // Algeria (Djelfa)
        6 => 'AD',    // Andorra (Encamp)
        7 => 'AO',    // Angola (Bié Province, Huambo)
        10 => 'AG',   // Antigua and Barbuda (Redonda)
        11 => 'AR',   // Argentina (San Juan)
        12 => 'AM',   // Armenia (Aragatsotn Region)
        14 => 'AT',   // Austria (Hallstatt)
        15 => 'AT',   // Austria duplicate - Carinthia
        16 => 'AZ',   // Azerbaijan (Shaki)
        17 => 'BS',   // Bahamas (Berry Islands)
        18 => 'BH',   // Bahrain (Capital)
        19 => 'BD',   // Bangladesh (Rangpur Division)
        20 => 'BB',   // Barbados (Saint Philip)
        21 => 'BY',   // Belarus (Mogilev Region)
        22 => 'BE',   // Belgium (Brussels)
        23 => 'BZ',   // Belize
        24 => 'BJ',   // Benin (Collines Department)
        25 => 'BM',   // Bermuda (Devonshire)
        26 => 'BT',   // Bhutan (Thimphu, Paro)
        27 => 'BO',   // Bolivia (Beni Department)
        28 => 'BA',   // Bosnia and Herzegovina (Brčko District)
        29 => 'BW',   // Botswana (Ngamiland)
        31 => 'BR',   // Brazil (Rio de Janeiro)
        33 => 'BN',   // Brunei (Brunei-Muara District)
        34 => 'BG',   // Bulgaria (Gabrovo Province)
        35 => 'BF',   // Burkina Faso (Kénédougou Province)
        36 => 'BI',   // Burundi (Rumonge Province)
        37 => 'KH',   // Cambodia (Svay Rieng)
        38 => 'CM',   // Cameroon (Far North)
        39 => 'CA',   // Canada (Ontario)
        40 => 'CV',   // Cape Verde (Ribeira Brava Municipality)
        42 => 'CF',   // Central African Republic (Sangha-Mbaéré)
        43 => 'TD',   // Chad (Moyen-Chari)
        44 => 'CL',   // Chile (Atacama)
        45 => 'CN',   // China (Beijing, Shanghai, Zhangjiajie)
        48 => 'CO',   // Colombia (Quindío)
        49 => 'KM',   // Comoros (Mohéli)
        50 => 'CG',   // Congo (Plateaux Department)
        51 => 'CD',   // DR Congo (Tshuapa)
        53 => 'CR',   // Costa Rica (Guanacaste Province)
        54 => 'CI',   // Côte d'Ivoire (Savanes Region)
        55 => 'HR',   // Croatia (Požega-Slavonia)
        56 => 'CU',   // Cuba (Havana)
        57 => 'CY',   // Cyprus (Kyrenia District)
        58 => 'CZ',   // Czech Republic (Břeclav)
        59 => 'DK',   // Denmark (Region Zealand)
        60 => 'DJ',   // Djibouti (Obock Region)
        61 => 'DM',   // Dominica (Saint John Parish)
        62 => 'DO',   // Dominican Republic (El Seibo Province)
        63 => 'TL',   // Timor-Leste (Viqueque Municipality)
        64 => 'EC',   // Ecuador (Galápagos)
        65 => 'EG',   // Egypt (Cairo)
        66 => 'SV',   // El Salvador (San Vicente Department)
        67 => 'GQ',   // Equatorial Guinea (Río Muni)
        68 => 'ER',   // Eritrea (Northern Red Sea Region)
        69 => 'EE',   // Estonia (Hiiu County)
        70 => 'ET',   // Ethiopia (Addis Ababa)
        73 => 'FJ',   // Fiji (Lomaiviti)
        74 => 'FI',   // Finland (Tavastia Proper)
        75 => 'FR',   // France (Paris, Mont Saint Michel)
        79 => 'GA',   // Gabon (Woleu-Ntem Province)
        80 => 'GM',   // Gambia (Banjul)
        81 => 'GE',   // Georgia (Gudauri)
        82 => 'DE',   // Germany (Munich)
        83 => 'GH',   // Ghana (Accra, Ashanti)
        85 => 'GR',   // Greece (Karditsa Regional Unit)
        87 => 'GD',   // Grenada (Saint Patrick Parish)
        90 => 'GT',   // Guatemala (Quiché Department)
        92 => 'GN',   // Guinea (Beyla Prefecture)
        93 => 'GW',   // Guinea-Bissau (Tombali Region)
        94 => 'GY',   // Guyana (Cuyuni-Mazaruni)
        95 => 'HT',   // Haiti (Nord)
        97 => 'HN',   // Honduras (Choluteca Department)
        98 => 'HK',   // Hong Kong (Yuen Long)
        99 => 'HU',   // Hungary (Hódmezővásárhely)
        100 => 'IS',  // Iceland (Southern Peninsula Region)
        101 => 'IN',  // India (Mumbai, Kashmir, Jaipur)
        102 => 'ID',  // Indonesia (Sumatera Utara)
        103 => 'IR',  // Iran (Markazi)
        104 => 'IQ',  // Iraq (Dhi Qar)
        105 => 'IE',  // Ireland (Tipperary)
        106 => 'IL',  // Israel (Northern District)
        107 => 'IT',  // Italy (Milan, Venice, Rome)
        108 => 'JM',  // Jamaica (Westmoreland Parish)
        109 => 'JP',  // Japan (Tokyo, Osaka, Nagoya, Sapporo)
        111 => 'JO',  // Jordan (Karak)
        112 => 'KZ',  // Kazakhstan (Almaty, Nur-Sultan)
        113 => 'KE',  // Kenya (Nairobi, Mombasa)
        114 => 'KI',  // Kiribati (Phoenix Islands)
        115 => 'KP',  // North Korea (North Hamgyong Province)
        116 => 'KR',  // South Korea (Seoul, Busan, Yongin)
        117 => 'KW',  // Kuwait (Al Jahra)
        118 => 'KG',  // Kyrgyzstan (Talas Region)
        119 => 'LA',  // Laos (Vientiane, Vang Vieng)
        120 => 'LV',  // Latvia (Salacgrīva Municipality)
        121 => 'LB',  // Lebanon (South)
        122 => 'LS',  // Lesotho (Mafeteng District)
        123 => 'LR',  // Liberia (Montserrado County)
        124 => 'LY',  // Libya (Murqub)
        125 => 'LI',  // Liechtenstein (Vaduz)
        126 => 'LT',  // Lithuania (Plungė District Municipality)
        127 => 'LU',  // Luxembourg (Canton of Diekirch)
        129 => 'MK',  // North Macedonia (Sveti Nikole Municipality)
        130 => 'MG',  // Madagascar (Fianarantsoa Province)
        131 => 'MW',  // Malawi (Machinga District)
        132 => 'MY',  // Malaysia (Labuan)
        133 => 'MV',  // Maldives (Vaavu Atoll)
        134 => 'ML',  // Mali (Bamako)
        135 => 'MT',  // Malta (Valletta)
        137 => 'MH',  // Marshall Islands (Ratak Chain)
        139 => 'MR',  // Mauritania (Hodh Ech Chargui)
        140 => 'MU',  // Mauritius (Agalega Islands)
        142 => 'MX',  // Mexico (Chihuahua)
        143 => 'FM',  // Micronesia (Chuuk State)
        144 => 'MD',  // Moldova (Cimișlia District)
        145 => 'MC',  // Monaco (La Colle)
        146 => 'MN',  // Mongolia (Ulaanbaatar)
        147 => 'ME',  // Montenegro (Podgorica)
        149 => 'MA',  // Morocco (Guelmim)
        150 => 'MZ',  // Mozambique (Cabo Delgado Province)
        151 => 'MM',  // Myanmar (Yangon, Bagan)
        152 => 'NA',  // Namibia
        153 => 'NR',  // Nauru (Ewa District)
        154 => 'NP',  // Nepal (Kathmandu)
        155 => 'NL',  // Netherlands - Bonaire
        156 => 'NL',  // Netherlands (Amsterdam)
        158 => 'NZ',  // New Zealand (Northland Region)
        159 => 'NI',  // Nicaragua (Chontales)
        160 => 'NE',  // Niger
        161 => 'NG',  // Nigeria (Lagos, Abuja)
        165 => 'NO',  // Norway (Trøndelag)
        166 => 'OM',  // Oman (Ad Dhahirah)
        167 => 'PK',  // Pakistan (Islamabad Capital Territory)
        168 => 'PW',  // Palau (Peleliu)
        169 => 'PS',  // Palestine (Bethlehem)
        170 => 'PA',  // Panama (Darién Province)
        171 => 'PG',  // Papua New Guinea (West New Britain Province)
        172 => 'PY',  // Paraguay (Asuncion)
        173 => 'PE',  // Peru (Madre de Dios)
        174 => 'PH',  // Philippines (Romblon)
        176 => 'PL',  // Poland (Opole Voivodeship)
        177 => 'PT',  // Portugal (Lisbon)
        178 => 'PR',  // Puerto Rico (San Juan)
        179 => 'QA',  // Qatar (Al Rayyan Municipality)
        181 => 'RO',  // Romania (Suceava County)
        182 => 'RU',  // Russia (Moscow, St. Petersburg, Baikal)
        183 => 'RW',  // Rwanda (Kigali)
        185 => 'KN',  // Saint Kitts and Nevis (Saint Peter Basseterre Parish)
        186 => 'LC',  // Saint Lucia (Dennery Quarter)
        188 => 'VC',  // Saint Vincent and the Grenadines (Saint George Parish)
        191 => 'WS',  // Samoa (Aiga-i-le-Tai)
        192 => 'SM',  // San Marino
        193 => 'ST',  // São Tomé and Príncipe
        194 => 'SA',  // Saudi Arabia (Riyadh)
        195 => 'SN',  // Senegal (Dakar)
        196 => 'RS',  // Serbia (South Bačka District)
        197 => 'SC',  // Seychelles (Mont Buxton)
        198 => 'SL',  // Sierra Leone (Northern Province)
        199 => 'SG',  // Singapore (North East)
        200 => 'SK',  // Slovakia (Banská Bystrica Region)
        201 => 'SI',  // Slovenia (Braslovče Municipality)
        202 => 'SB',  // Solomon Islands (Honiara)
        203 => 'SO',  // Somalia (Hiran)
        204 => 'ZA',  // South Africa (Northern Cape)
        206 => 'SS',  // South Sudan (Unity)
        207 => 'ES',  // Spain (Burgos)
        208 => 'LK',  // Sri Lanka (Jaffna District)
        209 => 'SD',  // Sudan (White Nile)
        210 => 'SR',  // Suriname (Commewijne District)
        212 => 'SZ',  // Eswatini (Manzini District)
        213 => 'SE',  // Sweden (Gävleborg County)
        214 => 'CH',  // Switzerland (Zurich, Interlaken, Zermatt)
        215 => 'SY',  // Syria (Hama)
        216 => 'TW',  // Taiwan (Yilan)
        217 => 'TJ',  // Tajikistan (Districts of Republican Subordination)
        218 => 'TZ',  // Tanzania (Shinyanga)
        219 => 'TH',  // Thailand (Bangkok, Phuket, Chiang Mai)
        220 => 'TG',  // Togo (Centrale Region)
        222 => 'TO',  // Tonga (Vavaʻu)
        223 => 'TT',  // Trinidad and Tobago (Western Tobago)
        224 => 'TN',  // Tunisia (Ariana)
        225 => 'TR',  // Turkey (Istanbul, Cappadocia)
        226 => 'TM',  // Turkmenistan (Mary Region)
        228 => 'TV',  // Tuvalu (Niutao Island Council)
        229 => 'UG',  // Uganda (Kampala)
        230 => 'UA',  // Ukraine (Zhytomyrska oblast)
        231 => 'AE',  // United Arab Emirates (Sharjah Emirate)
        232 => 'GB',  // United Kingdom (London)
        233 => 'US',  // United States (New York, LA, San Francisco)
        234 => 'UM',  // US Minor Outlying Islands
        235 => 'UY',  // Uruguay (Flores Department)
        236 => 'UZ',  // Uzbekistan (Tashkent)
        237 => 'VU',  // Vanuatu (Torba)
        239 => 'VE',  // Venezuela (Cojedes)
        240 => 'VN',  // Vietnam (Hanoi, Ho Chi Minh, Sapa)
        242 => 'VI',  // US Virgin Islands (Saint Thomas)
        245 => 'YE',  // Yemen (Amanat Al Asimah)
        246 => 'ZM',  // Zambia (Northern Province)
        247 => 'ZW',  // Zimbabwe (Mashonaland East Province)
        248 => 'XK',  // Kosovo (Prizren District)
    ];

    public function handle(): int
    {
        $sqlPath = database_path('ฐานข้อมูลเดิม/tb_city.sql');
        
        if (!file_exists($sqlPath)) {
            $this->error("SQL file not found: {$sqlPath}");
            return 1;
        }

        $this->info('Reading SQL file...');
        $content = file_get_contents($sqlPath);
        
        // Parse INSERT statements
        preg_match_all('/\((\d+),\s*(\d+),\s*(?:NULL|\'([^\']*)\'),\s*\'([^\']*)\',\s*\'(on|off)\',\s*\d+,\s*\'([^\']*)\'/u', $content, $matches, PREG_SET_ORDER);
        
        $this->info("Found " . count($matches) . " cities in SQL file");
        
        // Get country ID mapping (iso2 => id)
        $countries = Country::pluck('id', 'iso2')->toArray();
        $this->info("Found " . count($countries) . " countries in database");
        
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No data will be imported');
        } else {
            // ลบข้อมูลเดิมก่อน
            $this->info('Clearing existing cities...');
            \DB::statement('SET FOREIGN_KEY_CHECKS=0');
            City::truncate();
            \DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
        
        $imported = 0;
        $skipped = 0;
        $unknownCountries = [];
        
        $bar = $this->output->createProgressBar(count($matches));
        $bar->start();
        
        $citiesToInsert = [];
        
        foreach ($matches as $match) {
            $legacyId = $match[1];
            $legacyCountryId = $match[2];
            $nameTh = !empty($match[3]) ? $match[3] : null;
            $nameEn = $match[4];
            $status = $match[5];
            $slug = $match[6];
            
            // Map legacy country_id to iso2
            $iso2 = $this->countryMapping[$legacyCountryId] ?? null;
            
            if (!$iso2) {
                if (!isset($unknownCountries[$legacyCountryId])) {
                    $unknownCountries[$legacyCountryId] = $nameEn;
                }
                $skipped++;
                $bar->advance();
                continue;
            }
            
            // Get new country_id
            $countryId = $countries[$iso2] ?? null;
            
            if (!$countryId) {
                $skipped++;
                $bar->advance();
                continue;
            }
            
            // Clean up name
            $nameTh = $nameTh ? html_entity_decode($nameTh, ENT_QUOTES, 'UTF-8') : null;
            $nameEn = html_entity_decode($nameEn, ENT_QUOTES, 'UTF-8');
            
            // Generate unique slug
            $baseSlug = Str::slug($nameEn);
            if (empty($baseSlug)) {
                $baseSlug = $slug;
            }
            
            $citiesToInsert[] = [
                'code' => null,
                'name_en' => $nameEn,
                'name_th' => $nameTh,
                'slug' => $baseSlug,
                'country_id' => $countryId,
                'timezone' => null,
                'image' => null,
                'description' => null,
                'is_popular' => false,
                'is_active' => $status === 'on',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            $imported++;
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        if (!$isDryRun && count($citiesToInsert) > 0) {
            $this->info('Inserting cities...');
            
            // Make slugs unique
            $slugCounts = [];
            foreach ($citiesToInsert as &$city) {
                $baseSlug = $city['slug'];
                if (isset($slugCounts[$baseSlug])) {
                    $slugCounts[$baseSlug]++;
                    $city['slug'] = $baseSlug . '-' . $slugCounts[$baseSlug];
                } else {
                    $slugCounts[$baseSlug] = 0;
                }
            }
            
            // Insert in chunks
            $chunks = array_chunk($citiesToInsert, 500);
            foreach ($chunks as $chunk) {
                City::insert($chunk);
            }
        }
        
        $this->info("Imported: {$imported} cities");
        $this->info("Skipped: {$skipped} cities");
        
        if (count($unknownCountries) > 0) {
            $this->warn("\nUnknown country IDs (need mapping):");
            foreach ($unknownCountries as $id => $example) {
                $this->line("  {$id} => '??',  // Example city: {$example}");
            }
        }
        
        return 0;
    }
}
