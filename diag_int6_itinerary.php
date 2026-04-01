<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\TourItinerary;
use App\Models\WholesalerApiConfig;
use App\Models\WholesalerFieldMapping;

// 1. Check aggregation config for wholesaler 6
$config = WholesalerApiConfig::where('wholesaler_id', 6)->first();
if (!$config) {
    echo "No config found for wholesaler 6\n";
    exit;
}

echo "=== Config ID: {$config->id} ===\n";
echo "Sync mode: " . ($config->auth_credentials['sync_mode'] ?? 'N/A') . "\n";

$endpoints = $config->auth_credentials['endpoints'] ?? [];
echo "Itineraries endpoint: " . ($endpoints['itineraries'] ?? 'NONE') . "\n";
echo "Tours endpoint: " . ($endpoints['tours'] ?? 'NONE') . "\n";

$aggConfig = $config->aggregation_config ?? [];
$dataStructure = $aggConfig['data_structure'] ?? [];
echo "\nData structure config:\n";
echo json_encode($dataStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// 2. Check itinerary field mappings
echo "\n=== Itinerary Field Mappings ===\n";
$mappings = WholesalerFieldMapping::where('wholesaler_id', 6)
    ->where('section_name', 'itinerary')
    ->where('is_active', true)
    ->orderBy('sort_order')
    ->get();

foreach ($mappings as $m) {
    echo "  {$m->our_field} <= {$m->their_field_path} (transform: {$m->transform_type})\n";
}

// 3. Pick one tour and fetch raw API data to see the structure
$tour = Tour::where('wholesaler_id', 6)->whereNotNull('external_id')->first();
if ($tour) {
    echo "\n=== Sample Tour: {$tour->tour_code} (external_id: {$tour->external_id}) ===\n";
    
    $itinCount = TourItinerary::where('tour_id', $tour->id)->count();
    echo "Current itineraries in DB: {$itinCount}\n";
    
    // Try to fetch raw itinerary data
    $itinEndpoint = $endpoints['itineraries'] ?? null;
    if ($itinEndpoint) {
        $url = str_replace(
            ['{external_id}', '{tour_code}', '{wholesaler_tour_code}'],
            [$tour->external_id ?? '', $tour->tour_code ?? '', $tour->wholesaler_tour_code ?? ''],
            $itinEndpoint
        );
        echo "Itinerary URL: {$url}\n";
        
        // Fetch via adapter
        $adapter = App\Services\WholesalerAdapters\AdapterFactory::create(6);
        $result = $adapter->fetchItineraries($url);
        
        echo "Fetch success: " . ($result->success ? 'YES' : 'NO') . "\n";
        echo "Raw itineraries count: " . count($result->itineraries ?? []) . "\n";
        
        if (!empty($result->itineraries)) {
            echo "\nFirst raw itinerary keys: " . implode(', ', array_keys($result->itineraries[0])) . "\n";
            echo "First raw itinerary (truncated):\n";
            echo json_encode($result->itineraries[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            
            // Check for day_number or similar field to understand uniqueness
            $dayNumbers = [];
            foreach ($result->itineraries as $itin) {
                $dn = $itin['day_order'] ?? $itin['DayNumber'] ?? $itin['day_number'] ?? $itin['Day'] ?? $itin['order'] ?? 'N/A';
                $dayNumbers[] = $dn;
            }
            echo "\nAll day numbers/orders: " . implode(', ', $dayNumbers) . "\n";
        }
    } else {
        echo "No itineraries endpoint - checking inline data structure...\n";
        
        // Fetch main tour data
        $toursEndpoint = $endpoints['tours'] ?? null;
        if ($toursEndpoint) {
            echo "Tours endpoint: {$toursEndpoint}\n";
        }
    }
}
