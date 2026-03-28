<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 1. Check tour_type column definition
$col = DB::select("SHOW COLUMNS FROM tours WHERE Field = 'tour_type'");
echo "=== tour_type column definition ===\n";
foreach ($col as $c) {
    echo "Type: {$c->Type}\n";
    echo "Null: {$c->Null}\n";
    echo "Default: {$c->Default}\n";
}

// 2. Check existing tour_type values
echo "\n=== Existing tour_type values ===\n";
$values = DB::table('tours')->select('tour_type', DB::raw('count(*) as cnt'))->groupBy('tour_type')->get();
foreach ($values as $v) {
    echo "'{$v->tour_type}': {$v->cnt}\n";
}

// 3. Check field mapping for tour_type in integration 6
echo "\n=== Field mapping for tour_type (integration 6) ===\n";
$mappingCols = Schema::getColumnListing('wholesaler_field_mappings');
echo "Field mapping columns: " . implode(', ', $mappingCols) . "\n";

// Find config_id column
$configCol = null;
foreach ($mappingCols as $c) {
    if (str_contains($c, 'config') || str_contains($c, 'wholesaler')) {
        $configCol = $c;
        break;
    }
}
echo "Config column: {$configCol}\n";

$mapping = DB::table('wholesaler_field_mappings')
    ->where($configCol, 6)
    ->where('our_field', 'tour_type')
    ->first();
if ($mapping) {
    foreach ((array)$mapping as $col => $val) {
        echo "{$col}: {$val}\n";
    }
} else {
    echo "No explicit mapping found for tour_type\n";
    $allMappings = DB::table('wholesaler_field_mappings')
        ->where($configCol, 6)
        ->where('section_name', 'tour')
        ->get();
    echo "\nAll tour section mappings:\n";
    foreach ($allMappings as $m) {
        echo "  {$m->their_field} -> {$m->our_field} (transform: " . ($m->transform_type ?? 'none') . ", default: " . ($m->default_value ?? 'null') . ")\n";
    }
}

// 4. Check what value API sends for tour_type - look at raw_data in error log
echo "\n=== Sample raw_data tour section ===\n";
$errRow = DB::table('sync_error_logs')->where('sync_log_id', 3826)->first();
if ($errRow && $errRow->raw_data) {
    $raw = json_decode($errRow->raw_data, true);
    if (isset($raw['tour'])) {
        echo "Tour section keys: " . implode(', ', array_keys($raw['tour'])) . "\n";
        echo "tour_type value: " . var_export($raw['tour']['tour_type'] ?? 'NOT SET', true) . "\n";
        // Check for any type-related fields
        foreach ($raw['tour'] as $k => $v) {
            if (str_contains(strtolower($k), 'type')) {
                echo "  {$k}: " . var_export($v, true) . "\n";
            }
        }
    }
    // Show tour keys from raw API data (before mapping)
    if (isset($raw['_original'])) {
        echo "\nOriginal API keys: " . implode(', ', array_keys($raw['_original'])) . "\n";
    }
}
