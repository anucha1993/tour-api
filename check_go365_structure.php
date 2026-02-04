<?php
/**
 * Check GO365 raw data structure for itinerary and transport
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\WholesalerApiConfig;
use Illuminate\Support\Facades\Http;

$tour = Tour::where('wholesaler_id', 6)->first();
if (!$tour) {
    echo "No GO365 tour found!\n";
    exit(1);
}

echo "Tour ID: {$tour->id}\n";
echo "External ID: {$tour->external_id}\n\n";

$config = WholesalerApiConfig::where('wholesaler_id', 6)->first();
$creds = is_array($config->auth_credentials) ? $config->auth_credentials : json_decode($config->auth_credentials, true);

$response = Http::withHeaders([
    'X-API-Key' => $creds['api_key'] ?? '',
])
->timeout(30)
->get("{$config->api_base_url}/tours/{$tour->external_id}");

if (!$response->successful()) {
    echo "API Error: {$response->status()}\n";
    exit(1);
}

$data = $response->json();

echo "=== Raw Data Keys ===\n";
print_r(array_keys($data));

echo "\n=== Periods Structure ===\n";
if (isset($data['periods']) && !empty($data['periods'])) {
    $firstPeriod = $data['periods'][0];
    echo "First period keys:\n";
    print_r(array_keys($firstPeriod));
    
    // Check for tour_daily (itinerary)
    if (isset($firstPeriod['tour_daily']) && !empty($firstPeriod['tour_daily'])) {
        echo "\n=== Tour Daily (Itinerary) Structure ===\n";
        echo "Number of daily items: " . count($firstPeriod['tour_daily']) . "\n";
        
        $firstDay = $firstPeriod['tour_daily'][0];
        echo "\nFirst day keys:\n";
        print_r(array_keys($firstDay));
        
        if (isset($firstDay['day_list']) && !empty($firstDay['day_list'])) {
            echo "\nFirst day_list item:\n";
            print_r($firstDay['day_list'][0] ?? $firstDay['day_list']);
        }
    }
    
    // Check for transport/flight in tour_period
    if (isset($firstPeriod['tour_period']) && !empty($firstPeriod['tour_period'])) {
        $firstTourPeriod = $firstPeriod['tour_period'][0];
        echo "\n=== Tour Period (Departure) Structure ===\n";
        
        if (isset($firstTourPeriod['period_flight']) && !empty($firstTourPeriod['period_flight'])) {
            echo "Flight info:\n";
            print_r($firstTourPeriod['period_flight']);
        }
        
        if (isset($firstTourPeriod['period_airline'])) {
            echo "\nAirline: " . $firstTourPeriod['period_airline'] . "\n";
        }
    }
}
