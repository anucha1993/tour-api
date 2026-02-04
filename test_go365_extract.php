<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Simulate GO365 API response structure
$sampleGO365 = [
    'tour_id' => 12345,
    'tour_code' => 'GO-JAPAN-001',
    'tour_name' => 'ทัวร์ญี่ปุ่น โตเกียว โอซาก้า 5วัน4คืน',
    'tour_num_day' => 5,
    'tour_num_night' => 4,
    'tour_description' => 'ทัวร์ญี่ปุ่นพรีเมียม',
    'tour_cover_image' => 'https://example.com/image.jpg',
    'tour_country' => [
        ['country_code_2' => 'JP', 'country_name' => 'Japan']
    ],
    'tour_file' => [
        'file_pdf' => 'https://example.com/tour.pdf'
    ],
    'periods' => [
        [
            'tour_period' => [
                [
                    'period_id' => 'P001',
                    'period_date' => '2026-03-01',
                    'period_back' => '2026-03-05',
                    'period_quota' => 30,
                    'period_available' => 25,
                    'period_visible' => '1',
                    'period_rate_adult_twn' => 39900,
                    'period_rate_adult_sgl' => 45900,
                    'period_rate_child_sgl' => 35900,
                    'period_flight' => [
                        ['flight_airline_name' => 'All Nippon Airways (NH)']
                    ],
                    'period_rate' => [
                        ['rate_deposit' => 10000, 'rate_commission' => 5]
                    ]
                ],
                [
                    'period_id' => 'P002',
                    'period_date' => '2026-03-15',
                    'period_back' => '2026-03-19',
                    'period_quota' => 30,
                    'period_available' => 10,
                    'period_visible' => '1',
                    'period_rate_adult_twn' => 42900,
                    'period_rate_adult_sgl' => 48900,
                    'period_rate_child_sgl' => 38900,
                    'period_flight' => [
                        ['flight_airline_name' => 'Japan Airlines (JL)']
                    ],
                    'period_rate' => [
                        ['rate_deposit' => 15000, 'rate_commission' => 5]
                    ]
                ]
            ],
            'tour_daily' => [
                [
                    'day_num' => 1,
                    'day_list' => [
                        ['day_title' => 'กรุงเทพฯ – โตเกียว', 'day_description' => 'เดินทางสู่สนามบิน...']
                    ]
                ],
                [
                    'day_num' => 2,
                    'day_list' => [
                        ['day_title' => 'โตเกียว ดิสนีย์แลนด์ (เช้า/กลางวัน/ค่ำ)', 'day_description' => 'เที่ยวดิสนีย์...']
                    ]
                ]
            ],
            'tour_menu' => [
                [
                    'menu_sub' => [
                        ['menu_sub_name_th' => 'เอเชียตะวันออก']
                    ]
                ]
            ]
        ]
    ]
];

echo "=== ทดสอบ extractValue function ===\n\n";

// Copy extractValue function from SyncToursJob
$extractValue = function($data, $path) use (&$extractValue) {
    if (empty($path)) return null;
    
    // Handle fallback paths with | separator
    if (strpos($path, '|') !== false) {
        $paths = explode('|', $path);
        foreach ($paths as $singlePath) {
            $value = $extractValue($data, trim($singlePath));
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        return null;
    }
    
    // Handle array notation like "Periods[]" or "countries[].code"
    if (strpos($path, '[]') !== false) {
        $parts = explode('[].', $path);
        $arrayKey = $parts[0];
        $fieldPath = $parts[1] ?? null;
        
        if (!isset($data[$arrayKey]) || !is_array($data[$arrayKey])) return null;
        if (empty($data[$arrayKey])) return null;
        
        // Get first element from array
        $firstItem = $data[$arrayKey][0] ?? null;
        if (!$firstItem) return null;
        
        if ($fieldPath) {
            // Recursively get nested field from first item
            return $extractValue($firstItem, $fieldPath);
        }
        return $firstItem;
    }
    
    // Normal dot notation path
    $keys = explode('.', $path);
    $value = $data;
    
    foreach ($keys as $key) {
        if (!is_array($value) || !isset($value[$key])) return null;
        $value = $value[$key];
    }
    
    return $value;
};

// Test paths from GO365 mappings
$testPaths = [
    'tour_id',
    'tour_code',
    'tour_name',
    'tour_country[].country_code_2',
    'periods[].tour_period[].period_id',
    'periods[].tour_period[].period_date',
    'periods[].tour_period[].period_available',
    'periods[].tour_period[].period_flight[].flight_airline_name',
    'periods[].tour_period[].period_rate[].rate_deposit',
    'periods[].tour_daily[].day_num',
    'periods[].tour_daily[].day_list[].day_title',
    'periods[].tour_daily[].day_list[].day_description',
    'periods[].tour_menu[].menu_sub[].menu_sub_name_th',
    'tour_file.file_pdf',
];

foreach ($testPaths as $path) {
    $value = $extractValue($sampleGO365, $path);
    $display = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
    echo "Path: {$path}\n";
    echo "  → Value: {$display}\n\n";
}

echo "\n=== ปัญหาที่พบ ===\n";
echo "1. extractValue สามารถ recursive nested arrays ได้\n";
echo "2. แต่ปัญหาคือ departure mapping ทำได้แค่ 1 ชั้น!\n\n";

// Check the departure processing section
echo "=== ทดสอบ Departure Extraction ===\n";
$periods = $sampleGO365['periods'] ?? [];
echo "periods count: " . count($periods) . "\n";

foreach ($periods as $idx => $period) {
    echo "\nPeriod[$idx]:\n";
    
    // Current SyncToursJob removes prefix and looks directly in $period
    $testPath = 'periods[].tour_period[].period_id';
    $cleanPath = preg_replace('/^[Pp]eriods\[\]\./', '', $testPath);
    echo "  Original path: {$testPath}\n";
    echo "  Cleaned path: {$cleanPath}\n";
    
    // This will NOT work because tour_period[] is an array!
    $directLookup = $period[$cleanPath] ?? 'NOT FOUND';
    echo "  Direct lookup: " . (is_array($directLookup) ? json_encode($directLookup) : $directLookup) . "\n";
    
    // We need to iterate tour_period[] 
    if (isset($period['tour_period'])) {
        echo "  tour_period count: " . count($period['tour_period']) . "\n";
        foreach ($period['tour_period'] as $tpIdx => $tourPeriod) {
            echo "    tour_period[$tpIdx]: period_id = {$tourPeriod['period_id']}\n";
        }
    }
}

echo "\n\n=== สรุป ===\n";
echo "ปัญหา: SyncToursJob รองรับ nested array แบบ Saver Travel:\n";
echo "  - periods[].id → วนลูป periods[] แต่ละตัวแล้วดึง id\n";
echo "\nแต่ GO365 มี nested อีกชั้น:\n";
echo "  - periods[].tour_period[].period_id\n";
echo "  - ต้องวนลูป periods[] แล้ววน tour_period[] อีกที\n";
echo "\nทำให้ 1 periods[] มีหลาย departures (tour_period[])\n";
