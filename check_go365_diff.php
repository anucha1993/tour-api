<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== วิเคราะห์ความแตกต่างของ Wholesaler 6 (GO365) ===\n\n";

// Wholesaler 3 mappings
echo "=== Wholesaler 3 (Saver Travel) - โครงสร้าง ===\n";
$w3 = DB::table('wholesaler_field_mappings')
    ->where('wholesaler_id', 3)
    ->get()
    ->groupBy('section_name');

foreach ($w3 as $section => $mappings) {
    echo "  [$section]\n";
    foreach ($mappings as $m) {
        echo "    {$m->our_field} ← {$m->their_field}\n";
    }
}

echo "\n\n=== Wholesaler 6 (GO365) - โครงสร้าง ===\n";
$w6 = DB::table('wholesaler_field_mappings')
    ->where('wholesaler_id', 6)
    ->get()
    ->groupBy('section_name');

foreach ($w6 as $section => $mappings) {
    echo "  [$section]\n";
    foreach ($mappings as $m) {
        echo "    {$m->our_field} ← {$m->their_field}\n";
    }
}

echo "\n\n=== ความแตกต่างหลัก ===\n";
echo "1. GO365 ใช้ nested structure ที่ลึกกว่า:\n";
echo "   - periods[].tour_period[].period_id (3 ชั้น)\n";
echo "   - periods[].tour_daily[].day_list[].day_title (4 ชั้น)\n";
echo "   - Saver Travel ใช้: periods[].id (2 ชั้น)\n\n";

echo "2. GO365 departure อยู่ใน periods[].tour_period[] ไม่ใช่ periods[] โดยตรง\n";
echo "3. GO365 itinerary อยู่ใน periods[].tour_daily[].day_list[] ไม่ใช่ plans[]\n";

echo "\n\n=== ตรวจสอบ SyncToursJob รองรับ nested หรือไม่ ===\n";

// Check if there's sample data from GO365 in sync_logs
$syncLog = DB::table('sync_logs')
    ->where('wholesaler_id', 6)
    ->orderBy('id', 'desc')
    ->first();

if ($syncLog) {
    echo "Last Sync: {$syncLog->created_at}\n";
    echo "Status: {$syncLog->status}\n";
} else {
    echo "ไม่มี sync log สำหรับ GO365\n";
}

// Check sync_error_logs
$errors = DB::table('sync_error_logs')
    ->where('wholesaler_id', 6)
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

echo "\n=== Error Logs ล่าสุด ===\n";
foreach ($errors as $err) {
    echo "- {$err->created_at}: {$err->error_type}\n";
    echo "  {$err->error_message}\n\n";
}

echo "\n=== Tours จาก GO365 ในระบบ ===\n";
$tours = DB::table('tours')->where('wholesaler_id', 6)->count();
echo "Total tours: {$tours}\n";
