<?php
/**
 * Fix integration 25 (BEST) field mappings and config
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Models\WholesalerFieldMapping;

$wholesalerId = 57;
$configId = 25;

echo "=== Fixing Integration 25 field mappings ===" . PHP_EOL;

// 0. Add 'date_format' to transform_type ENUM if not present
DB::statement("ALTER TABLE wholesaler_field_mappings MODIFY COLUMN transform_type ENUM('direct','value_map','formula','split','concat','lookup','custom','date_format') DEFAULT 'direct'");
echo "  ENUM: Added date_format to transform_type" . PHP_EOL;

// 1. Fix date format mappings: start_date, end_date
$dateFields = ['start_date', 'end_date'];
foreach ($dateFields as $field) {
    $mapping = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
        ->where('section_name', 'departure')
        ->where('our_field', $field)
        ->first();
    
    if ($mapping) {
        $mapping->update([
            'transform_type' => 'date_format',
            'transform_config' => ['output_format' => 'Y-m-d'],
        ]);
        echo "  FIXED: departure.{$field} -> date_format (Y-m-d)" . PHP_EOL;
    } else {
        echo "  NOT FOUND: departure.{$field}" . PHP_EOL;
    }
}

// 2. Fix content.description path: [].name -> name
$mapping = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
    ->where('section_name', 'content')
    ->where('our_field', 'description')
    ->first();
if ($mapping) {
    $mapping->update([
        'their_field' => 'name',
        'their_field_path' => 'name',
    ]);
    echo "  FIXED: content.description path -> name" . PHP_EOL;
}

// 3. Fix seo paths
$seoFixes = [
    'slug' => 'code',
    'meta_title' => 'name',
    'meta_description' => 'name',
    'keywords' => 'tagss',
];
foreach ($seoFixes as $ourField => $theirField) {
    $mapping = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
        ->where('section_name', 'seo')
        ->where('our_field', $ourField)
        ->first();
    if ($mapping) {
        $mapping->update([
            'their_field' => $theirField,
            'their_field_path' => $theirField,
        ]);
        echo "  FIXED: seo.{$ourField} path -> {$theirField}" . PHP_EOL;
    }
}

// 4. Add tour.duration_days mapping (time -> duration_days, parsed in code)
$existing = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
    ->where('section_name', 'tour')
    ->where('our_field', 'duration_days')
    ->first();
if (!$existing) {
    WholesalerFieldMapping::create([
        'wholesaler_id' => $wholesalerId,
        'section_name' => 'tour',
        'our_field' => 'duration_days',
        'their_field' => 'time',
        'their_field_path' => 'time',
        'transform_type' => 'direct',
        'is_active' => true,
        'sort_order' => 10,
    ]);
    echo "  ADDED: tour.duration_days <= time" . PHP_EOL;
} else {
    echo "  EXISTS: tour.duration_days" . PHP_EOL;
}

// 5. Enable extract_countries_from_name
$config = WholesalerApiConfig::find($configId);
$config->update([
    'extract_countries_from_name' => true,
]);
echo "  ENABLED: extract_countries_from_name" . PHP_EOL;

// 6. Verify all mappings
echo PHP_EOL . "=== Final mappings for wholesaler 57 ===" . PHP_EOL;
$maps = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
    ->where('is_active', true)
    ->orderBy('section_name')
    ->orderBy('sort_order')
    ->get();

foreach ($maps as $m) {
    $transformInfo = $m->transform_type;
    if ($m->transform_config) {
        $transformInfo .= ' ' . json_encode($m->transform_config, JSON_UNESCAPED_UNICODE);
    }
    echo "  {$m->section_name}.{$m->our_field} <= " . ($m->their_field_path ?? $m->their_field) . " ({$transformInfo})" . PHP_EOL;
}

echo PHP_EOL . "Done! Total mappings: " . $maps->count() . PHP_EOL;
