<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Wholesaler ID: 6 (GO365 Travel) ===\n";
$wholesaler = DB::table('wholesalers')->where('id', 6)->first();
echo "Name: {$wholesaler->name}\n";
echo "Code: {$wholesaler->code}\n";
echo "Active: " . ($wholesaler->is_active ? 'Yes' : 'No') . "\n";

echo "\n=== API Config ของ Wholesaler 6 ===\n";
$apiConfigs = DB::table('wholesaler_api_configs')->where('wholesaler_id', 6)->get();
if ($apiConfigs->isEmpty()) {
    echo "ไม่มี API config\n";
} else {
    foreach ($apiConfigs as $config) {
        print_r($config);
    }
}

echo "\n=== Field Mappings ของ Wholesaler 6 ===\n";
$mappings = DB::table('wholesaler_field_mappings')->where('wholesaler_id', 6)->get();
if ($mappings->isEmpty()) {
    echo "ไม่มี field mappings!\n";
} else {
    foreach ($mappings as $mapping) {
        echo "  {$mapping->source_field} → {$mapping->target_field}\n";
    }
}

echo "\n=== เปรียบเทียบกับ Wholesaler อื่น ===\n";
$allWholesalers = DB::table('wholesalers')->get();
foreach ($allWholesalers as $w) {
    $mappingCount = DB::table('wholesaler_field_mappings')->where('wholesaler_id', $w->id)->count();
    $apiCount = DB::table('wholesaler_api_configs')->where('wholesaler_id', $w->id)->count();
    $tourCount = DB::table('tours')->where('wholesaler_id', $w->id)->count();
    $active = $w->is_active ? '✓' : '✗';
    echo "ID: {$w->id} | {$active} | {$w->code} - {$w->name}\n";
    echo "     Tours: {$tourCount} | API Configs: {$apiCount} | Field Mappings: {$mappingCount}\n";
}

echo "\n=== ตัวอย่าง Tours จาก Wholesaler 6 ===\n";
$tours = DB::table('tours')->where('wholesaler_id', 6)->limit(3)->get();
if ($tours->isEmpty()) {
    echo "ไม่มี tours\n";
} else {
    foreach ($tours as $tour) {
        echo "ID: {$tour->id}\n";
        echo "  Name: " . mb_substr($tour->name, 0, 50) . "...\n";
        echo "  External ID: {$tour->external_id}\n";
        echo "\n";
    }
}

echo "\n=== โครงสร้าง wholesaler_field_mappings ===\n";
$columns = DB::select("DESCRIBE wholesaler_field_mappings");
foreach ($columns as $col) {
    echo "  {$col->Field} ({$col->Type})\n";
}

echo "\n=== ตัวอย่าง Field Mappings จาก Wholesaler อื่น ===\n";
$sampleMappings = DB::table('wholesaler_field_mappings')->limit(10)->get();
foreach ($sampleMappings as $m) {
    echo "W{$m->wholesaler_id}: {$m->source_field} → {$m->target_field}";
    if (!empty($m->transformer)) {
        echo " [transformer: {$m->transformer}]";
    }
    echo "\n";
}
