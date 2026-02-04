<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Services\UnifiedSearchService;

$wholesalerId = $argv[1] ?? 6;
$config = WholesalerApiConfig::where('wholesaler_id', $wholesalerId)->first();

if (!$config) {
    echo "Config not found!\n";
    exit;
}

$searchService = new UnifiedSearchService();

echo "=== Testing Filters for Wholesaler #{$wholesalerId} ===\n\n";

// Test 1: No filters
echo "1. No filters:\n";
$result = $searchService->searchWholesaler($config, []);
echo "   Total tours: " . count($result['tours']) . "\n\n";

// Test 2: Date filter
echo "2. With date filter (departure_from: 2026-03-01):\n";
$result = $searchService->searchWholesaler($config, [
    'departure_from' => '2026-03-01'
]);
echo "   Total tours: " . count($result['tours']) . "\n";
if (!empty($result['tours'][0]['periods'])) {
    $firstPeriod = $result['tours'][0]['periods'][0];
    echo "   First period date: " . ($firstPeriod['start_date'] ?? $firstPeriod['departure_date'] ?? 'N/A') . "\n";
}
echo "\n";

// Test 3: Price filter
echo "3. With price filter (max_price: 30000):\n";
$result = $searchService->searchWholesaler($config, [
    'max_price' => 30000
]);
echo "   Total tours: " . count($result['tours']) . "\n";
if (!empty($result['tours'][0]['periods'])) {
    $firstPeriod = $result['tours'][0]['periods'][0];
    echo "   First period price: " . ($firstPeriod['price_adult'] ?? 'N/A') . "\n";
}
echo "\n";

// Test 4: Keyword filter
echo "4. With keyword filter (keyword: 'ญี่ปุ่น'):\n";
$result = $searchService->searchWholesaler($config, [
    'keyword' => 'ญี่ปุ่น'
]);
echo "   Total tours: " . count($result['tours']) . "\n";
foreach (array_slice($result['tours'], 0, 3) as $tour) {
    echo "   - " . ($tour['title'] ?? 'N/A') . "\n";
}
echo "\n";

// Test 5: Country filter
echo "5. With country filter (country: 'JP'):\n";
$result = $searchService->searchWholesaler($config, [
    'country' => 'JP'
]);
echo "   Total tours: " . count($result['tours']) . "\n";
foreach (array_slice($result['tours'], 0, 3) as $tour) {
    echo "   - " . ($tour['title'] ?? 'N/A') . " [" . ($tour['primary_country_id'] ?? '?') . "]\n";
}
