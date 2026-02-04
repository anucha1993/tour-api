<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Jobs\SyncToursJob;
use App\Services\UnifiedSearchService;
use Illuminate\Support\Facades\DB;

$configId = $argv[1] ?? 11;
$config = WholesalerApiConfig::find($configId);

if (!$config) {
    echo "Config ID {$configId} not found!\n";
    exit(1);
}

$wholesaler = DB::table('wholesalers')->where('id', $config->wholesaler_id)->first();

echo "=== Testing Integration ID {$configId} ===\n\n";
echo "Wholesaler ID: {$config->wholesaler_id}\n";
echo "Wholesaler Name: " . ($wholesaler->name ?? 'N/A') . "\n";
echo "API Base URL: {$config->api_base_url}\n";
echo "Sync Mode: {$config->sync_mode}\n";
echo "Is Active: " . ($config->is_active ? 'Yes' : 'No') . "\n\n";

// Check aggregation_config
$aggConfig = $config->aggregation_config ?? [];
echo "=== Aggregation Config ===\n";
if (!empty($aggConfig['data_structure'])) {
    echo "Data Structure:\n";
    foreach ($aggConfig['data_structure'] as $key => $val) {
        echo "  {$key}: " . ($val['path'] ?? 'N/A') . "\n";
    }
} else {
    echo "  (none)\n";
}
echo "\n";

// Check endpoints
$credentials = $config->auth_credentials ?? [];
echo "=== Endpoints ===\n";
if (!empty($credentials['endpoints'])) {
    foreach ($credentials['endpoints'] as $key => $val) {
        echo "  {$key}: {$val}\n";
    }
} else {
    echo "  (none)\n";
}
echo "\n";

// Test 1: SyncToursJob
echo "=== Test 1: SyncToursJob ===\n";
try {
    // SyncToursJob expects wholesaler_id, not config_id
    $job = new SyncToursJob($config->wholesaler_id);
    $job->handle();
    
    $tourCount = DB::table('tours')->where('wholesaler_id', $config->wholesaler_id)->count();
    echo "Tours synced: {$tourCount}\n";
    
    // Get sample tour
    $sampleTour = DB::table('tours')
        ->where('wholesaler_id', $config->wholesaler_id)
        ->first();
    
    if ($sampleTour) {
        echo "\nSample Tour:\n";
        echo "  ID: {$sampleTour->id}\n";
        echo "  Title: {$sampleTour->title}\n";
        echo "  External ID: {$sampleTour->external_id}\n";
        echo "  Code: {$sampleTour->wholesaler_tour_code}\n";
        echo "  Duration: {$sampleTour->duration_days}d/{$sampleTour->duration_nights}n\n";
        echo "  Transport ID: " . ($sampleTour->transport_id ?? 'null') . "\n";
        
        // Check periods
        $periodCount = DB::table('periods')->where('tour_id', $sampleTour->id)->count();
        echo "  Periods: {$periodCount}\n";
        
        // Check itineraries
        $itinCount = DB::table('tour_itineraries')->where('tour_id', $sampleTour->id)->count();
        echo "  Itineraries: {$itinCount}\n";
        
        // Check tour_transports
        $transportCount = DB::table('tour_transports')->where('tour_id', $sampleTour->id)->count();
        echo "  Tour Transports: {$transportCount}\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Test 2: Realtime Search ===\n";
try {
    $searchService = new UnifiedSearchService();
    $result = $searchService->searchWholesaler($config, []);
    
    echo "Total tours: " . count($result['tours']) . "\n";
    
    if (!empty($result['tours'])) {
        $tour = $result['tours'][0];
        echo "\nFirst Tour:\n";
        echo "  Title: " . ($tour['title'] ?? 'N/A') . "\n";
        echo "  Code: " . ($tour['wholesaler_tour_code'] ?? 'N/A') . "\n";
        echo "  Country: " . ($tour['primary_country_id'] ?? 'N/A') . "\n";
        echo "  Transport: " . ($tour['transport_id_name'] ?? $tour['transport_id'] ?? 'N/A') . "\n";
        echo "  Periods: " . count($tour['periods'] ?? []) . "\n";
        
        if (!empty($tour['periods'])) {
            $period = $tour['periods'][0];
            echo "\n  First Period:\n";
            echo "    Date: " . ($period['start_date'] ?? $period['departure_date'] ?? 'N/A') . "\n";
            echo "    Price: " . ($period['price_adult'] ?? 'N/A') . "\n";
            echo "    Available: " . ($period['available'] ?? 'N/A') . "\n";
        }
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
