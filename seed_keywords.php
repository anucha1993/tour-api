<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SearchKeyword;

$keywords = [
    'ญี่ปุ่น', 'เกาหลี', 'จีน', 'ไต้หวัน', 'เวียดนาม',
    'สิงคโปร์', 'ฮ่องกง', 'มาเก๊า', 'ตุรกี', 'จอร์เจีย',
    'คาซัคสถาน', 'ซากุระ', 'ใบไม้เปลี่ยนสี', 'ปีใหม่', 'สงกรานต์',
    'ทัวร์ราคาถูก', 'ทัวร์ส่วนตัว', 'ทัวร์กรุ๊ปเล็ก',
    'ซากุระ ญี่ปุ่น', 'ทัวร์ญี่ปุ่น', 'ทัวร์เกาหลี', 'ทัวร์จีน',
];

foreach ($keywords as $kw) {
    SearchKeyword::updateOrCreate(
        ['keyword' => mb_strtolower($kw)],
        ['search_count' => rand(5, 50), 'result_count' => rand(1, 20), 'last_searched_at' => now()]
    );
}

echo "Seeded " . count($keywords) . " keywords\n";
