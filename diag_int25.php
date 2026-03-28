<?php
/**
 * Diagnostic: trace the full sync path for integration 25
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Models\WholesalerFieldMapping;
use App\Services\WholesalerAdapters\AdapterFactory;

$config = WholesalerApiConfig::find(25);
echo "=== Integration 25 Diagnostic ===" . PHP_EOL;

// 1. Check adapter
$adapter = AdapterFactory::create($config->wholesaler_id);
echo "Adapter: " . get_class($adapter) . PHP_EOL;

// 2. Fetch 1 page (limit=2 to be quick)
echo PHP_EOL . "=== Fetching tours ===" . PHP_EOL;
$result = $adapter->fetchTours('1');
echo "Success: " . ($result->success ? 'YES' : 'NO') . PHP_EOL;
echo "Tours count: " . count($result->tours) . PHP_EOL;
echo "Has more: " . ($result->hasMore ? 'YES' : 'NO') . PHP_EOL;
echo "Next cursor: " . ($result->nextCursor ?? 'null') . PHP_EOL;
echo "Current page: " . ($result->currentPage ?? 'null') . PHP_EOL;
echo "Last page: " . ($result->lastPage ?? 'null') . PHP_EOL;

if (!$result->success) {
    echo "Error: " . $result->errorMessage . PHP_EOL;
    exit;
}

if (empty($result->tours)) {
    echo "NO TOURS RETURNED!" . PHP_EOL;
    exit;
}

// Show first tour raw data
$rawTour = $result->tours[0];
echo PHP_EOL . "=== First raw tour ===" . PHP_EOL;
echo "Keys: " . implode(', ', array_keys($rawTour)) . PHP_EOL;
echo "id: " . ($rawTour['id'] ?? 'N/A') . PHP_EOL;
echo "code: " . ($rawTour['code'] ?? 'N/A') . PHP_EOL;
echo "name: " . ($rawTour['name'] ?? 'N/A') . PHP_EOL;
echo "period count: " . (isset($rawTour['period']) ? count($rawTour['period']) : 'N/A') . PHP_EOL;

// 3. Load field mappings
$mappings = WholesalerFieldMapping::where('wholesaler_id', 57)
    ->where('is_active', true)
    ->get()
    ->groupBy('section_name');

echo PHP_EOL . "=== Field mappings by section ===" . PHP_EOL;
foreach ($mappings as $section => $sectionMaps) {
    echo "$section: " . $sectionMaps->count() . " mappings" . PHP_EOL;
}

// 4. Now simulate transformTourData manually
echo PHP_EOL . "=== Manual transform simulation ===" . PHP_EOL;

// Tour section
echo "--- TOUR section ---" . PHP_EOL;
foreach ($mappings['tour'] ?? [] as $m) {
    $path = $m->their_field_path ?? $m->their_field;
    $value = null;
    // Simple extraction
    if (strpos($path, '.') !== false) {
        $keys = explode('.', $path);
        $value = $rawTour;
        foreach ($keys as $k) {
            $value = $value[$k] ?? null;
            if ($value === null) break;
        }
    } else {
        $value = $rawTour[$path] ?? null;
    }
    $displayVal = is_array($value) ? '[ARRAY]' : ($value === null ? 'NULL' : mb_substr((string)$value, 0, 80));
    echo "  {$m->our_field} <= {$path}: '{$displayVal}' ({$m->transform_type})" . PHP_EOL;
}

// Media section
echo "--- MEDIA section ---" . PHP_EOL;
foreach ($mappings['media'] ?? [] as $m) {
    $path = $m->their_field_path ?? $m->their_field;
    $value = $rawTour[$path] ?? null;
    $displayVal = is_array($value) ? '[ARRAY]' : ($value === null ? 'NULL' : mb_substr((string)$value, 0, 80));
    echo "  {$m->our_field} <= {$path}: '{$displayVal}' ({$m->transform_type})" . PHP_EOL;
}

// Content section
echo "--- CONTENT section ---" . PHP_EOL;
foreach ($mappings['content'] ?? [] as $m) {
    $path = $m->their_field_path ?? $m->their_field;
    $value = $rawTour[$path] ?? null;
    $displayVal = is_array($value) ? '[ARRAY]' : ($value === null ? 'NULL' : mb_substr((string)$value, 0, 80));
    echo "  {$m->our_field} <= {$path}: '{$displayVal}' ({$m->transform_type})" . PHP_EOL;
}

// SEO section
echo "--- SEO section ---" . PHP_EOL;
foreach ($mappings['seo'] ?? [] as $m) {
    $path = $m->their_field_path ?? $m->their_field;
    $value = $rawTour[$path] ?? null;
    $displayVal = is_array($value) ? '[ARRAY]' : ($value === null ? 'NULL' : mb_substr((string)$value, 0, 80));
    echo "  {$m->our_field} <= {$path}: '{$displayVal}' ({$m->transform_type})" . PHP_EOL;
}

// Departure section (first period)
echo "--- DEPARTURE section ---" . PHP_EOL;
$periods = $rawTour['period'] ?? [];
echo "Found " . count($periods) . " periods in API data" . PHP_EOL;
if (!empty($periods)) {
    $firstPeriod = $periods[0];
    foreach ($mappings['departure'] ?? [] as $m) {
        $path = $m->their_field_path ?? $m->their_field;
        // Clean: remove period[]. prefix
        $cleanPath = preg_replace('/^[Pp]eriods?\[\]\./', '', $path);
        $value = $firstPeriod[$cleanPath] ?? null;
        $displayVal = is_array($value) ? '[ARRAY]' : ($value === null ? 'NULL' : mb_substr((string)$value, 0, 80));
        echo "  {$m->our_field} <= {$path} (clean: {$cleanPath}): '{$displayVal}' ({$m->transform_type})" . PHP_EOL;
    }
}

// 5. Check the date format issue
echo PHP_EOL . "=== Date format check ===" . PHP_EOL;
$dateGo = $firstPeriod['dateGo'] ?? null;
$dateBack = $firstPeriod['dateBack'] ?? null;
echo "Raw dateGo: " . $dateGo . PHP_EOL;
echo "Raw dateBack: " . $dateBack . PHP_EOL;
echo "strtotime(dateGo): " . date('Y-m-d', strtotime($dateGo)) . PHP_EOL;
echo "strtotime(dateBack): " . date('Y-m-d', strtotime($dateBack)) . PHP_EOL;
echo "PHP interprets '04/04/2026' correctly as April 4: " . (date('Y-m-d', strtotime('04/04/2026')) === '2026-04-04' ? 'YES' : 'NO') . PHP_EOL;

// 6. Check duration parsing from "time" field
$timeStr = $rawTour['time'] ?? null;
echo PHP_EOL . "=== Duration parsing ===" . PHP_EOL;
echo "time field: " . $timeStr . PHP_EOL;
if (preg_match('/(\d+)\s*วัน/', $timeStr, $dm)) {
    echo "duration_days: " . $dm[1] . PHP_EOL;
}
if (preg_match('/(\d+)\s*คืน/', $timeStr, $nm)) {
    echo "duration_nights: " . $nm[1] . PHP_EOL;
}

// 7. Check if content/seo mappings use [].name which is wrong
echo PHP_EOL . "=== Problematic mappings ===" . PHP_EOL;
foreach ($mappings as $section => $sectionMaps) {
    foreach ($sectionMaps as $m) {
        $path = $m->their_field_path ?? $m->their_field;
        if (strpos($path, '[].') === 0) {
            echo "  PROBLEM: {$section}.{$m->our_field} => '{$path}' — starts with []." . PHP_EOL;
        }
    }
}
