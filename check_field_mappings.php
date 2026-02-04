<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== โครงสร้างตาราง wholesaler_field_mappings ===\n";
$columns = DB::select("DESCRIBE wholesaler_field_mappings");
foreach ($columns as $col) {
    echo "  {$col->Field} ({$col->Type})\n";
}

echo "\n=== Field Mappings ทั้งหมด ===\n";
$mappings = DB::table('wholesaler_field_mappings')->get();
echo "Total: " . $mappings->count() . "\n\n";

foreach ($mappings as $m) {
    print_r($m);
    echo "---\n";
}
