<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\WholesalerAdapters\AdapterFactory;

$wholesalerId = $argv[1] ?? 3;

// Get mappings grouped by section
$mappings = \App\Models\WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
    ->where('is_active', true)
    ->get()
    ->groupBy('section_name');

echo "=== Mappings by Section ===\n";
foreach ($mappings as $section => $fields) {
    echo "  {$section}: " . $fields->count() . " fields\n";
}

// Fetch from API
$adapter = AdapterFactory::create($wholesalerId);
$result = $adapter->fetchTours();
$tours = $result->tours;

if (empty($tours)) {
    echo "No data from API\n";
    exit;
}

$rawTour = $tours[0];

echo "\n=== Raw Tour Keys ===\n";
echo implode(', ', array_keys($rawTour)) . "\n";

// Check if plans exists
if (isset($rawTour['plans'])) {
    echo "\n=== plans[] array ===\n";
    echo "Count: " . count($rawTour['plans']) . "\n";
    echo "First item keys: " . implode(', ', array_keys($rawTour['plans'][0])) . "\n";
}

// Simulate mapTourData for itinerary section
echo "\n=== Testing Itinerary Mapping ===\n";

$itineraries = [];
$itinCandidates = ['Itinerary', 'itinerary', 'Itineraries', 'itineraries', 'Days', 'days', 'Programs', 'programs', 'plans', 'Plans'];
foreach ($itinCandidates as $key) {
    if (isset($rawTour[$key]) && is_array($rawTour[$key])) {
        $itineraries = $rawTour[$key];
        echo "Found itineraries in key: {$key}\n";
        break;
    }
}

if (empty($itineraries)) {
    echo "❌ No itineraries found in raw tour data\n";
    exit;
}

echo "Itineraries found: " . count($itineraries) . "\n";

// Check if itinerary section exists in mappings
if (!isset($mappings['itinerary'])) {
    echo "❌ No 'itinerary' section in mappings!\n";
    echo "Available sections: " . implode(', ', $mappings->keys()->toArray()) . "\n";
    exit;
}

echo "\n=== Mapped Itinerary Data ===\n";
$dayIndex = 1;
foreach ($itineraries as $i => $itin) {
    $it = [];
    foreach ($mappings['itinerary'] as $mapping) {
        $fieldName = $mapping->our_field;
        $path = $mapping->their_field_path ?? $mapping->their_field;
        
        // Remove prefix
        $cleanPath = preg_replace('/^[Pp]lans\[\]\./', '', $path);
        
        $value = $itin[$cleanPath] ?? null;
        $it[$fieldName] = $value ? mb_substr((string)$value, 0, 50) . '...' : null;
    }
    
    // Auto-generate day_number if not mapped
    if (empty($it['day_number'])) {
        $it['day_number'] = $dayIndex;
    }
    $dayIndex++;
    
    echo "Day {$it['day_number']}: title=" . ($it['title'] ?? 'N/A') . "\n";
}
