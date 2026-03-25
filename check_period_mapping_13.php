<?php
/**
 * ตรวจสอบ Mapping vs ข้อมูลจริงจาก API — Integration 13
 * Usage: php check_period_mapping_13.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WholesalerFieldMapping;
use App\Services\WholesalerAdapters\AdapterFactory;

echo "=== Period Mapping Check: Integration 13 (wholesaler_id=14) ===\n\n";

// ─── Fetch 1 tour from API ───
$adapter = AdapterFactory::create(14);
$result = $adapter->fetchTours(null);
$firstTour = $result->tours[0] ?? null;

if (!$firstTour) {
    echo "❌ ไม่สามารถดึงข้อมูลทัวร์ได้\n";
    exit(1);
}

echo "── Tour keys จาก API ──\n";
foreach ($firstTour as $key => $value) {
    if (is_array($value)) {
        echo "  [{$key}] → array(" . count($value) . " items)\n";
        if (!empty($value[0]) && is_array($value[0])) {
            echo "    First item keys: " . implode(', ', array_keys($value[0])) . "\n";
            // Show sample values of first item
            foreach ($value[0] as $k => $v) {
                if (!is_array($v)) {
                    echo "      {$k}: " . var_export($v, true) . "\n";
                }
            }
        }
    } else {
        echo "  {$key}: " . var_export($value, true) . "\n";
    }
}

// ─── Check period key ───
echo "\n── Period/Departure data key detection ──\n";
$periodKeys = ['Periods', 'periods', 'Schedules', 'schedules', 'Departures', 'departures', 'period', 'Period'];
$foundKey = null;
foreach ($periodKeys as $k) {
    if (isset($firstTour[$k])) {
        echo "✅ Found key '{$k}' → " . count($firstTour[$k]) . " items\n";
        $foundKey = $k;
    } else {
        echo "   ❌ '{$k}' not found\n";
    }
}

if (!$foundKey) {
    echo "\n⚠️ ไม่พบ period key ที่รู้จัก! ต้องตั้ง aggregation_config.data_structure.departures.path\n";
} else {
    echo "\n── ตรวจสอบว่า Mapping paths ตรงกับ field จริงไหม ──\n";
    // Get departure mappings
    $mappings = WholesalerFieldMapping::where('wholesaler_id', 14)
        ->where('section_name', 'departure')
        ->where('is_active', true)
        ->get();

    $firstPeriod = $firstTour[$foundKey][0] ?? [];
    echo "Period item keys: " . implode(', ', array_keys($firstPeriod)) . "\n\n";

    foreach ($mappings as $m) {
        $path = $m->their_field_path ?? $m->their_field ?? '';
        // Strip array prefix like "period[].fieldname" → "fieldname"
        $cleanPath = preg_replace('/^[^.]+\[\]\./', '', $path);
        $exists = isset($firstPeriod[$cleanPath]) || array_key_exists($cleanPath, $firstPeriod);
        $value = $firstPeriod[$cleanPath] ?? '(not found)';
        $icon = $exists ? '✅' : '❌';
        echo "  {$icon} {$m->our_field} ← {$path} (clean: {$cleanPath}) → " . var_export($value, true) . "\n";
    }
}

// ─── Tour mappings check ───
echo "\n── ตรวจสอบ Tour Mappings ──\n";
$tourMappings = WholesalerFieldMapping::where('wholesaler_id', 14)
    ->where('section_name', 'tour')
    ->where('is_active', true)
    ->get();

foreach ($tourMappings as $m) {
    $path = $m->their_field_path ?? $m->their_field ?? '';
    $exists = isset($firstTour[$path]) || array_key_exists($path, $firstTour);
    $value = $firstTour[$path] ?? '(not found)';
    $icon = $exists ? '✅' : '❌';
    $displayVal = is_array($value) ? '[array]' : var_export($value, true);
    $transform = $m->transform_type ?? 'direct';
    echo "  {$icon} {$m->our_field} ← {$path} [{$transform}] → {$displayVal}\n";
}
