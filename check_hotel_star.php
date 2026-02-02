<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\AdapterFactory;
use App\Models\TourItinerary;
use App\Models\Tour;

$config = WholesalerApiConfig::where('wholesaler_id', 2)->first();

// Check latest tours from wholesaler 2 and recalculate
echo "=== Latest Tours from Wholesaler 2 ===\n";
$tours = Tour::where('wholesaler_id', 2)->orderByDesc('id')->limit(3)->get();
foreach ($tours as $t) {
    echo "ID: {$t->id} - {$t->title}\n";
    echo "  BEFORE: hotel_star=" . var_export($t->hotel_star, true) . ", min=" . var_export($t->hotel_star_min, true) . ", max=" . var_export($t->hotel_star_max, true) . "\n";
    
    // Recalculate
    $t->recalculateAggregates();
    $t->refresh();
    
    echo "  AFTER:  hotel_star=" . var_export($t->hotel_star, true) . ", min=" . var_export($t->hotel_star_min, true) . ", max=" . var_export($t->hotel_star_max, true) . "\n\n";
}

exit;
$creds = $config->auth_credentials;

echo "=== Check Hotel Star Sync ===\n\n";

// 1. Check API endpoint
$endpoints = $creds['endpoints'] ?? [];
$itinEndpoint = $endpoints['itineraries'] ?? null;
echo "Itineraries Endpoint: " . ($itinEndpoint ?? 'NOT SET') . "\n\n";

// 2. Fetch from API
if ($itinEndpoint) {
    $url = str_replace('{external_id}', 'T24JCR21', $itinEndpoint);
    echo "Test URL: $url\n\n";
    
    $adapter = AdapterFactory::create(2);
    $result = $adapter->fetchItineraries($url);
    
    if ($result->success) {
        echo "Fetched " . count($result->itineraries) . " itineraries\n\n";
        
        if (!empty($result->itineraries)) {
            echo "First itinerary raw data:\n";
            print_r($result->itineraries[0]);
            
            // Check for hotel rating fields
            echo "\n=== Looking for hotel star fields ===\n";
            $first = $result->itineraries[0];
            $hotelFields = ['hotelRating', 'hotel_rating', 'hotelStar', 'hotel_star', 'HotelStar', 'HotelRating', 'star', 'stars'];
            foreach ($hotelFields as $field) {
                if (isset($first[$field])) {
                    echo "Found: $field = " . $first[$field] . "\n";
                }
            }
        }
    } else {
        echo "Failed to fetch: " . ($result->error ?? 'Unknown') . "\n";
    }
}

// 3. Check what's in database
echo "\n=== Database Tour Itineraries (Wholesaler 2) ===\n";
$tours = Tour::where('wholesaler_id', 2)->limit(5)->get();
foreach ($tours as $tour) {
    echo "\nTour ID: {$tour->id}, external_id: " . ($tour->external_id ?? 'NULL') . "\n";
    echo "  hotel_star: " . ($tour->hotel_star ?? 'NULL') . ", min: " . ($tour->hotel_star_min ?? 'NULL') . ", max: " . ($tour->hotel_star_max ?? 'NULL') . "\n";
    
    $itins = TourItinerary::where('tour_id', $tour->id)->get();
    echo "  Itineraries: " . $itins->count() . "\n";
    foreach ($itins->take(3) as $itin) {
        echo "    Day {$itin->day_number}: hotel_star = " . ($itin->hotel_star ?? 'NULL') . "\n";
    }
    
    // Test fetch itineraries if endpoint is set
    if ($itinEndpoint && $tour->external_id) {
        $url = str_replace('{external_id}', $tour->external_id, $itinEndpoint);
        echo "  Testing: $url\n";
        
        $adapter = AdapterFactory::create(2);
        $result = $adapter->fetchItineraries($url);
        
        if ($result->success && !empty($result->itineraries)) {
            echo "  API returned " . count($result->itineraries) . " itineraries\n";
            $first = $result->itineraries[0];
            echo "  Sample: " . json_encode($first, JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "  API failed or empty\n";
        }
        break; // Only test one
    }
}
