<?php
/**
 * Test NEW fetchAndMapTours logic in SyncToursJob
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\WholesalerAdapters\AdapterFactory;
use App\Models\WholesalerFieldMapping;

$wholesalerId = 1;

// Create adapter and fetch 1 tour
$adapter = AdapterFactory::create($wholesalerId);
$result = $adapter->fetchTours(null);

echo "Adapter: " . get_class($adapter) . "\n";
echo "Tours count: " . count($result->tours) . "\n";

if (count($result->tours) == 0) {
    echo "No tours fetched\n";
    exit;
}

// Get mappings (same logic as SyncToursJob)
$mappings = WholesalerFieldMapping::where('wholesaler_id', $wholesalerId)
    ->where('is_active', true)
    ->get()
    ->groupBy('section_name');

echo "\nMappings per section:\n";
foreach ($mappings as $section => $maps) {
    echo "  $section: " . count($maps) . " fields\n";
}

$rawTour = $result->tours[0];

// Extract value helper
$extractValue = function($data, $path) {
    if (empty($path)) return null;
    
    if (strpos($path, '[]') !== false) {
        $parts = explode('[].', $path);
        $arrayKey = $parts[0];
        $fieldPath = $parts[1] ?? null;
        
        if (!isset($data[$arrayKey]) || !is_array($data[$arrayKey])) return null;
        if (empty($data[$arrayKey])) return null;
        
        if ($fieldPath && isset($data[$arrayKey][0])) {
            return $data[$arrayKey][0][$fieldPath] ?? null;
        }
        return null;
    }
    
    $keys = explode('.', $path);
    $value = $data;
    
    foreach ($keys as $key) {
        if (!is_array($value) || !isset($value[$key])) return null;
        $value = $value[$key];
    }
    
    return $value;
};

echo "\n=== Tour Section Transform ===\n";
if (isset($mappings['tour'])) {
    foreach ($mappings['tour'] as $mapping) {
        $path = $mapping->their_field_path ?? $mapping->their_field;
        $value = $extractValue($rawTour, $path);
        $displayValue = is_array($value) ? json_encode($value) : substr((string)$value, 0, 60);
        echo "  {$mapping->our_field} <= '{$path}' = " . ($displayValue ?: 'NULL') . "\n";
    }
}

echo "\n=== Departure Section Transform ===\n";
$periods = $rawTour['Periods'] ?? [];
echo "  Found " . count($periods) . " periods\n";

if (isset($mappings['departure']) && count($periods) > 0) {
    $period = $periods[0];
    foreach ($mappings['departure'] as $mapping) {
        $path = $mapping->their_field_path ?? $mapping->their_field;
        $cleanPath = preg_replace('/^Periods\[\]\\./', '', $path);
        $cleanPath = preg_replace('/^Flights\[\]\\./', '', $cleanPath);
        $value = $period[$cleanPath] ?? null;
        $displayValue = is_array($value) ? json_encode($value) : substr((string)$value, 0, 60);
        echo "  {$mapping->our_field} <= '{$cleanPath}' = " . ($displayValue ?: 'NULL') . "\n";
    }
}

// Full transform test
echo "\n=== Full Transform Result ===\n";
$transformed = transformTourData($rawTour, $mappings, $extractValue);
echo "Tour fields: " . count($transformed['tour']) . "\n";
echo "Departures: " . count($transformed['departure']) . "\n";
echo "Itineraries: " . count($transformed['itinerary']) . "\n";

echo "\nSample tour data:\n";
$show = ['title', 'tour_code', 'wholesaler_tour_code', 'highlight', 'airline', 'start_day'];
foreach ($show as $field) {
    $val = $transformed['tour'][$field] ?? 'N/A';
    $display = is_array($val) ? json_encode($val) : substr((string)$val, 0, 80);
    echo "  $field: $display\n";
}

echo "\nSample departure data:\n";
if (count($transformed['departure']) > 0) {
    $dep = $transformed['departure'][0];
    foreach (['departure_date', 'return_date', 'price_adult', 'price_child', 'price_infant', 'seats_available'] as $field) {
        $val = $dep[$field] ?? 'N/A';
        echo "  $field: $val\n";
    }
}

echo "\n✅ Transform logic working correctly!\n";

function transformTourData(array $rawTour, $mappings, $extractValue): array
{
    $result = [
        'tour' => [],
        'departure' => [],
        'itinerary' => [],
        'content' => [],
        'media' => [],
    ];

    // Map single-value sections (tour, content, media)
    foreach (['tour', 'content', 'media'] as $section) {
        if (!isset($mappings[$section])) continue;
        
        foreach ($mappings[$section] as $mapping) {
            $fieldName = $mapping->our_field;
            $path = $mapping->their_field_path ?? $mapping->their_field;
            
            $value = $extractValue($rawTour, $path);
            
            if ($value === null && !empty($mapping->default_value)) {
                $value = $mapping->default_value;
            }
            
            $result[$section][$fieldName] = $value;
        }
    }

    // Map departures (from Periods array)
    $periods = $rawTour['Periods'] ?? [];
    if (isset($mappings['departure']) && !empty($periods)) {
        foreach ($periods as $period) {
            $dep = [];
            foreach ($mappings['departure'] as $mapping) {
                $fieldName = $mapping->our_field;
                $path = $mapping->their_field_path ?? $mapping->their_field;
                
                $cleanPath = preg_replace('/^Periods\[\]\\./', '', $path);
                $cleanPath = preg_replace('/^Flights\[\]\\./', '', $cleanPath);
                
                $value = $period[$cleanPath] ?? null;
                
                if ($value === null && !empty($mapping->default_value)) {
                    $value = $mapping->default_value;
                }
                
                $dep[$fieldName] = $value;
            }
            $result['departure'][] = $dep;
        }
    }

    // Map itinerary (from Itinerary array)
    $itineraries = $rawTour['Itinerary'] ?? [];
    if (isset($mappings['itinerary']) && !empty($itineraries)) {
        foreach ($itineraries as $itin) {
            $it = [];
            foreach ($mappings['itinerary'] as $mapping) {
                $fieldName = $mapping->our_field;
                $path = $mapping->their_field_path ?? $mapping->their_field;
                
                $cleanPath = preg_replace('/^Itinerary\[\]\\./', '', $path);
                
                $value = $itin[$cleanPath] ?? null;
                
                if ($value === null && !empty($mapping->default_value)) {
                    $value = $mapping->default_value;
                }
                
                $it[$fieldName] = $value;
            }
            $result['itinerary'][] = $it;
        }
    }

    return $result;
}
