<?php
/**
 * Check raw periods data from GO365 API
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\AdapterFactory;

$tour = Tour::where('wholesaler_id', 6)->first();
if (!$tour) {
    echo "No GO365 tour found!\n";
    exit(1);
}

echo "Tour ID: {$tour->id}\n";
echo "External ID: {$tour->external_id}\n\n";

$config = WholesalerApiConfig::where('wholesaler_id', 6)->first();

// Get periods endpoint
$credentials = is_array($config->auth_credentials) ? $config->auth_credentials : json_decode($config->auth_credentials, true);
$endpoints = $credentials['endpoints'] ?? [];
$periodsEndpoint = $endpoints['periods'] ?? null;

echo "Periods endpoint: {$periodsEndpoint}\n\n";

if (!$periodsEndpoint) {
    echo "No periods endpoint configured!\n";
    exit(1);
}

// Build URL
$url = str_replace(
    ['{external_id}', '{tour_code}'],
    [$tour->external_id, $tour->tour_code ?? ''],
    $periodsEndpoint
);

echo "URL: {$url}\n\n";

// Fetch periods
$adapter = AdapterFactory::create(6);
$result = $adapter->fetchPeriods($url);

if (!$result->success) {
    echo "API Error: {$result->errorMessage}\n";
    exit(1);
}

echo "=== Raw Periods Structure ===\n";
$periods = $result->periods ?? [];
echo "Number of periods: " . count($periods) . "\n";

if (!empty($periods)) {
    $firstPeriod = $periods[0];
    echo "\nFirst period keys:\n";
    print_r(array_keys($firstPeriod));
    
    // Check tour_daily (itinerary)
    if (isset($firstPeriod['tour_daily'])) {
        echo "\n=== Tour Daily (Itinerary) ===\n";
        echo "Number of days: " . count($firstPeriod['tour_daily']) . "\n";
        
        if (!empty($firstPeriod['tour_daily'])) {
            $firstDay = $firstPeriod['tour_daily'][0];
            echo "\nFirst day keys:\n";
            print_r(array_keys($firstDay));
            
            if (isset($firstDay['day_list'])) {
                echo "\nFirst day_list:\n";
                print_r(array_slice($firstDay['day_list'], 0, 1));
            }
        }
    }
    
    // Check tour_period (departure with transport)
    if (isset($firstPeriod['tour_period'])) {
        echo "\n=== Tour Period (with Transport) ===\n";
        echo "Number of tour_periods: " . count($firstPeriod['tour_period']) . "\n";
        
        if (!empty($firstPeriod['tour_period'])) {
            $firstTourPeriod = $firstPeriod['tour_period'][0];
            
            if (isset($firstTourPeriod['period_airline'])) {
                echo "\nAirline (period_airline):\n";
                print_r($firstTourPeriod['period_airline']);
            }
            
            if (isset($firstTourPeriod['period_flight'])) {
                echo "\nFlight info:\n";
                print_r($firstTourPeriod['period_flight']);
            }
        }
    }
    
    // Check tour-level airline
    if (isset($firstPeriod['tour_airline'])) {
        echo "\n=== Tour Airline (tour-level) ===\n";
        print_r($firstPeriod['tour_airline']);
    }
}
