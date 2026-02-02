<?php

/**
 * Test Two-Phase Sync for Wholesaler ID 2
 * 
 * Run: php test_two_phase_sync.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

echo "=== Two-Phase Sync Test ===\n\n";

// 1. Get Wholesaler API Config for ID 2
$config = \App\Models\WholesalerApiConfig::where('wholesaler_id', 2)->first();

if (!$config) {
    echo "ERROR: No API config found for wholesaler_id = 2\n";
    exit(1);
}

echo "1. API Config found:\n";
echo "   - Sync Mode: " . ($config->sync_mode ?? 'N/A') . "\n";
echo "   - Auth Type: " . ($config->auth_type ?? 'N/A') . "\n";

$credentials = $config->auth_credentials ?? [];
$endpoints = $credentials['endpoints'] ?? [];

echo "   - Endpoints:\n";
foreach ($endpoints as $key => $url) {
    echo "     * {$key}: {$url}\n";
}

// 2. Get a tour from this wholesaler OR create mock with external_id 66
$tour = \App\Models\Tour::where('wholesaler_id', 2)
    ->whereNotNull('external_id')
    ->first();

if (!$tour) {
    echo "\n2. No tour found for wholesaler_id = 2, using mock tour with external_id = 66\n";
    
    // Create a mock tour object for testing (external_id 66 from log)
    $tour = new \App\Models\Tour();
    $tour->id = 999;
    $tour->tour_code = 'TEST';
    $tour->external_id = '66';  // From log: tour/series/66/schedules
    $tour->wholesaler_tour_code = null;
}

echo "\n2. Test Tour:\n";
echo "   - ID: " . $tour->id . "\n";
echo "   - Tour Code: " . $tour->tour_code . "\n";
echo "   - External ID: " . $tour->external_id . "\n";
echo "   - Wholesaler Tour Code: " . ($tour->wholesaler_tour_code ?? 'N/A') . "\n";

// 3. Build Periods URL
$periodsEndpoint = $endpoints['periods'] ?? null;
if (!$periodsEndpoint) {
    echo "\nERROR: No periods endpoint configured\n";
    exit(1);
}

$periodsUrl = str_replace(
    ['{external_id}', '{tour_code}', '{wholesaler_tour_code}'],
    [$tour->external_id ?? '', $tour->tour_code ?? '', $tour->wholesaler_tour_code ?? ''],
    $periodsEndpoint
);

echo "\n3. Periods URL: {$periodsUrl}\n";

// 4. Create Adapter and fetch periods
echo "\n4. Creating adapter and fetching periods...\n";

try {
    $adapter = \App\Services\WholesalerAdapters\AdapterFactory::create(2);
    echo "   - Adapter created: " . get_class($adapter) . "\n";
    
    $fetchResult = $adapter->fetchPeriods($periodsUrl);
    
    echo "   - Fetch success: " . ($fetchResult->success ? 'YES' : 'NO') . "\n";
    
    if (!$fetchResult->success) {
        echo "   - Error: " . ($fetchResult->error ?? 'Unknown') . "\n";
        exit(1);
    }
    
    $periods = $fetchResult->periods ?? [];
    echo "   - Periods count: " . count($periods) . "\n";
    
    if (count($periods) > 0) {
        echo "\n5. Sample Period Data (first item):\n";
        echo json_encode($periods[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    
} catch (\Exception $e) {
    echo "   - Exception: " . $e->getMessage() . "\n";
    echo "   - File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

// 5. Get field mappings for departure section
echo "\n6. Field Mappings for 'departure' section (wholesaler_id = 2):\n";

$mappings = \App\Models\WholesalerFieldMapping::where('wholesaler_id', 2)
    ->where('section_name', 'departure')
    ->where('is_active', true)
    ->get();

echo "   - Total mappings: " . $mappings->count() . "\n";

if ($mappings->isEmpty()) {
    echo "   WARNING: No active departure mappings found!\n";
} else {
    echo "   - Mappings:\n";
    foreach ($mappings as $m) {
        echo "     * our_field: {$m->our_field} <- their_field_path: " . ($m->their_field_path ?? $m->their_field ?? 'NULL') . "\n";
    }
}

// 6. Try to transform the first period
if (count($periods) > 0 && $mappings->isNotEmpty()) {
    echo "\n7. Transform Test (first period):\n";
    
    $rawPeriod = $periods[0];
    $periodData = [];
    
    foreach ($mappings as $mapping) {
        $fieldPath = $mapping->their_field_path ?? $mapping->their_field ?? null;
        if (empty($fieldPath)) {
            continue;
        }
        
        echo "   - Processing: {$mapping->our_field} <- {$fieldPath}\n";
        
        // Strip array prefix
        $cleanPath = preg_replace('/^[Pp]eriods\[\]\./', '', $fieldPath);
        $cleanPath = preg_replace('/^[Ss]chedules\[\]\./', '', $cleanPath);
        $cleanPath = preg_replace('/^[Dd]epartures\[\]\./', '', $cleanPath);
        
        echo "     Clean path: {$cleanPath}\n";
        
        $value = extractValueFromPath($rawPeriod, $cleanPath);
        echo "     Value: " . json_encode($value) . "\n";
        
        $periodData[$mapping->our_field] = $value;
    }
    
    echo "\n8. Transformed Period Data:\n";
    echo json_encode($periodData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    // Check for start_date
    if (empty($periodData['start_date']) && !empty($periodData['departure_date'])) {
        echo "\n   NOTE: Mapping departure_date -> start_date\n";
        $periodData['start_date'] = $periodData['departure_date'];
    }
    
    if (empty($periodData['start_date'])) {
        echo "\n   WARNING: No start_date found! Period will be skipped.\n";
    } else {
        echo "\n   OK: start_date = " . $periodData['start_date'] . "\n";
    }
}

// 7. Test Itineraries
echo "\n\n=== Itineraries Test ===\n";

$itinerariesEndpoint = $endpoints['itineraries'] ?? null;
if (!$itinerariesEndpoint) {
    echo "No itineraries endpoint configured\n";
} else {
    $itinerariesUrl = str_replace(
        ['{external_id}', '{tour_code}', '{wholesaler_tour_code}'],
        [$tour->external_id ?? '', $tour->tour_code ?? '', $tour->wholesaler_tour_code ?? ''],
        $itinerariesEndpoint
    );
    
    echo "Itineraries URL: {$itinerariesUrl}\n";
    
    try {
        $fetchResult = $adapter->fetchItineraries($itinerariesUrl);
        
        echo "Fetch success: " . ($fetchResult->success ? 'YES' : 'NO') . "\n";
        
        if ($fetchResult->success) {
            $itineraries = $fetchResult->itineraries ?? [];
            echo "Itineraries count: " . count($itineraries) . "\n";
            
            if (count($itineraries) > 0) {
                echo "\nSample Itinerary Data (first item):\n";
                echo json_encode($itineraries[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        } else {
            echo "Error: " . ($fetchResult->error ?? 'Unknown') . "\n";
        }
        
    } catch (\Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }
    
    // Get itinerary mappings
    $itinMappings = \App\Models\WholesalerFieldMapping::where('wholesaler_id', 2)
        ->where('section_name', 'itinerary')
        ->where('is_active', true)
        ->get();
    
    echo "\nItinerary Mappings count: " . $itinMappings->count() . "\n";
    foreach ($itinMappings as $m) {
        echo "  * our_field: {$m->our_field} <- their_field_path: " . ($m->their_field_path ?? $m->their_field ?? 'NULL') . "\n";
    }
}

echo "\n=== Test Complete ===\n";

/**
 * Helper function to extract value from nested array
 */
function extractValueFromPath(array $data, ?string $path): mixed
{
    if (empty($path)) {
        return null;
    }
    
    $parts = explode('.', $path);
    $current = $data;
    
    foreach ($parts as $part) {
        // Handle array notation like "items[]"
        if (str_ends_with($part, '[]')) {
            $key = substr($part, 0, -2);
            if (!isset($current[$key]) || !is_array($current[$key])) {
                return null;
            }
            $current = $current[$key];
        } else {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }
    }
    
    return $current;
}
