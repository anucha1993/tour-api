<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== เมืองที่ไม่มีชื่อไทย ===\n\n";

$total = DB::table('cities')->count();
$withThai = DB::table('cities')->whereNotNull('name_th')->where('name_th', '!=', '')->count();
$withoutThai = $total - $withThai;

echo "รวม: {$total} เมือง\n";
echo "มีชื่อไทย: {$withThai} เมือง\n";
echo "ไม่มีชื่อไทย: {$withoutThai} เมือง\n\n";

// แยกตามประเทศ
echo "=== เมืองไม่มีชื่อไทย แยกตามประเทศ (Top 15) ===\n";
$byCountry = DB::table('cities')
    ->select('country_id', DB::raw('COUNT(*) as cnt'))
    ->where(function($q) {
        $q->whereNull('name_th')->orWhere('name_th', '');
    })
    ->groupBy('country_id')
    ->orderByDesc('cnt')
    ->limit(15)
    ->get();

foreach ($byCountry as $row) {
    $country = DB::table('countries')->where('id', $row->country_id)->first();
    $countryName = $country ? $country->name_en : 'Unknown';
    echo "  {$countryName} (ID: {$row->country_id}): {$row->cnt} เมือง\n";
}

// ดูเมืองที่ไม่มีชื่อไทยในประเทศหลัก
echo "\n=== เมืองไทยที่ไม่มีชื่อไทย ===\n";
$thaiMissing = DB::table('cities')
    ->where('country_id', 8)
    ->where(function($q) {
        $q->whereNull('name_th')->orWhere('name_th', '');
    })
    ->get(['id', 'name_en']);

if ($thaiMissing->isEmpty()) {
    echo "  ✅ ไม่มี - ครบหมดแล้ว!\n";
} else {
    foreach ($thaiMissing as $c) {
        echo "  - {$c->id} | {$c->name_en}\n";
    }
}

echo "\n=== เมืองจีนที่ไม่มีชื่อไทย ===\n";
$chinaMissing = DB::table('cities')
    ->where('country_id', 3)
    ->where(function($q) {
        $q->whereNull('name_th')->orWhere('name_th', '');
    })
    ->limit(20)
    ->get(['id', 'name_en']);

foreach ($chinaMissing as $c) {
    echo "  - {$c->id} | {$c->name_en}\n";
}

echo "\n=== เมืองญี่ปุ่นที่ไม่มีชื่อไทย ===\n";
$japanMissing = DB::table('cities')
    ->where('country_id', 1)
    ->where(function($q) {
        $q->whereNull('name_th')->orWhere('name_th', '');
    })
    ->limit(20)
    ->get(['id', 'name_en']);

if ($japanMissing->isEmpty()) {
    echo "  ✅ ไม่มี - ครบหมดแล้ว!\n";
} else {
    foreach ($japanMissing as $c) {
        echo "  - {$c->id} | {$c->name_en}\n";
    }
}
