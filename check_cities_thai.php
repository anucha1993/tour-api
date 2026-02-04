<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== ตรวจสอบชื่อไทยในตาราง Cities ===\n\n";

// เช็คว่ามี name_th ที่มีค่าจริงกี่รายการ
$withThaiName = DB::table('cities')->whereNotNull('name_th')->where('name_th', '!=', '')->count();
$totalCities = DB::table('cities')->count();

echo "Cities ที่มีชื่อไทย: {$withThaiName} / {$totalCities}\n\n";

// แสดงเมืองในประเทศไทย
echo "=== เมืองในประเทศไทย (Thailand ID: 8) ===\n";
$thaiCities = DB::table('cities')
    ->where('country_id', 8)
    ->limit(20)
    ->get();

foreach ($thaiCities as $city) {
    $thName = $city->name_th ?: '(ไม่มี)';
    echo "  ID: {$city->id} | EN: {$city->name_en} | TH: {$thName}\n";
}

echo "\n=== เมืองในประเทศจีน (China ID: 3) ===\n";
$chinaCities = DB::table('cities')
    ->where('country_id', 3)
    ->limit(10)
    ->get();

foreach ($chinaCities as $city) {
    $thName = $city->name_th ?: '(ไม่มี)';
    echo "  ID: {$city->id} | EN: {$city->name_en} | TH: {$thName}\n";
}

echo "\n=== เมืองในประเทศญี่ปุ่น (Japan ID: 1) ===\n";
$japanCities = DB::table('cities')
    ->where('country_id', 1)
    ->limit(10)
    ->get();

foreach ($japanCities as $city) {
    $thName = $city->name_th ?: '(ไม่มี)';
    echo "  ID: {$city->id} | EN: {$city->name_en} | TH: {$thName}\n";
}

echo "\n=== ตัวอย่างเมืองที่มีชื่อไทย ===\n";
$citiesWithThai = DB::table('cities')
    ->whereNotNull('name_th')
    ->where('name_th', '!=', '')
    ->limit(15)
    ->get();

if ($citiesWithThai->isEmpty()) {
    echo "  ไม่พบเมืองที่มีชื่อไทย\n";
} else {
    foreach ($citiesWithThai as $city) {
        echo "  ID: {$city->id} | EN: {$city->name_en} | TH: {$city->name_th}\n";
    }
}
