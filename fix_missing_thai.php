<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== อัพเดทชื่อไทยที่ขาดหายไป ===\n\n";

$updates = [
    // ประเทศไทย
    3378 => 'พระนครศรีอยุธยา',
    3397 => 'ชลบุรี',
    3427 => 'ลพบุรี',
    3433 => 'พังงา',
    
    // ประเทศจีน
    2155 => 'อันฮุย',
    2157 => 'จี๋หลิน',
    2178 => 'หูเป่ย',
    2179 => 'กานซู',
    
    // ประเทศญี่ปุ่น
    4727 => 'โคจิ',
];

$count = 0;
foreach ($updates as $id => $nameTh) {
    $city = DB::table('cities')->where('id', $id)->first();
    if ($city) {
        DB::table('cities')->where('id', $id)->update(['name_th' => $nameTh]);
        echo "✅ {$city->name_en} → {$nameTh}\n";
        $count++;
    } else {
        echo "❌ ID {$id} not found\n";
    }
}

echo "\n=== อัพเดทเสร็จ: {$count} เมือง ===\n";

// ตรวจสอบอีกครั้ง
echo "\n=== ตรวจสอบเมืองไทยที่ยังไม่มีชื่อไทย ===\n";
$thaiMissing = DB::table('cities')
    ->where('country_id', 8)
    ->where(function($q) {
        $q->whereNull('name_th')->orWhere('name_th', '');
    })
    ->get(['id', 'name_en']);

if ($thaiMissing->isEmpty()) {
    echo "✅ ครบหมดแล้ว!\n";
} else {
    foreach ($thaiMissing as $c) {
        echo "  - {$c->id} | {$c->name_en}\n";
    }
}
