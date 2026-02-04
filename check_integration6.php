<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== ค้นหาตารางที่เกี่ยวข้อง ===\n";
$tables = DB::select('SHOW TABLES');
foreach ($tables as $table) {
    $name = array_values((array)$table)[0];
    if (strpos($name, 'wholesaler') !== false || strpos($name, 'integrat') !== false || strpos($name, 'api') !== false || strpos($name, 'sync') !== false || strpos($name, 'mapping') !== false) {
        echo "  - $name\n";
    }
}

echo "\n=== Wholesaler ID: 6 ===\n";
$wholesaler = DB::table('wholesalers')->where('id', 6)->first();
print_r($wholesaler);

echo "\n=== Tour packages จาก Wholesaler 6 (ตัวอย่าง 5 รายการ) ===\n";
$packages = DB::table('tour_packages')
    ->where('wholesaler_id', 6)
    ->limit(5)
    ->get();

foreach ($packages as $pkg) {
    echo "ID: {$pkg->id} | Name: " . substr($pkg->name, 0, 60) . "...\n";
    echo "  External ID: {$pkg->external_id}\n";
    echo "  Wholesaler ID: {$pkg->wholesaler_id}\n";
    echo "  Countries: {$pkg->primary_country_id}\n";
    echo "  Transport: {$pkg->transport_id}\n";
    echo "\n";
}

echo "\n=== เปรียบเทียบกับ Wholesaler อื่น ===\n";
$wholesalers = DB::table('wholesalers')
    ->select('id', 'name', 'code', 'is_active')
    ->get();

foreach ($wholesalers as $w) {
    $pkgCount = DB::table('tour_packages')->where('wholesaler_id', $w->id)->count();
    $active = $w->is_active ? '✓' : '✗';
    echo "ID: {$w->id} | {$active} | {$w->name} ({$w->code}) - {$pkgCount} packages\n";
}

echo "\n=== ตรวจสอบ API config ของ Wholesaler 6 ===\n";
if ($wholesaler) {
    echo "API URL: " . ($wholesaler->api_url ?? 'N/A') . "\n";
    echo "API Key: " . ($wholesaler->api_key ? '***SET***' : 'N/A') . "\n";
    echo "Type: " . ($wholesaler->type ?? 'N/A') . "\n";
}
