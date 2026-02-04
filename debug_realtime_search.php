<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\AdapterFactory;
use App\Services\WholesalerAdapters\Adapters\GenericRestAdapter;

$config = WholesalerApiConfig::where('wholesaler_id', 6)->first();

echo "=== Debug GO365 Realtime Search ===\n\n";

echo "sync_mode: " . ($config->sync_mode ?? 'null') . "\n";

// Get adapter
$adapter = AdapterFactory::create(6);
echo "adapter class: " . get_class($adapter) . "\n";
echo "is GenericRestAdapter: " . ($adapter instanceof GenericRestAdapter ? 'YES' : 'NO') . "\n\n";

// Check endpoints
$credentials = $config->auth_credentials ?? [];
$periodsEndpoint = $credentials['endpoints']['periods'] ?? null;
echo "periods endpoint: " . ($periodsEndpoint ?? 'NULL') . "\n\n";

// Check aggregation_config
$aggregationConfig = $config->aggregation_config ?? [];
$dataStructure = $aggregationConfig['data_structure'] ?? [];
$departuresPath = $dataStructure['departures']['path'] ?? null;
echo "departures path: " . ($departuresPath ?? 'NULL') . "\n\n";

// Fetch one tour and test periods fetch
$result = $adapter->fetchTours(null);
if ($result->success && !empty($result->tours)) {
    $tour = $result->tours[0];
    echo "Raw tour keys: " . implode(', ', array_keys($tour)) . "\n\n";
    echo "tour['external_id']: " . ($tour['external_id'] ?? 'N/A') . "\n";
    echo "tour['id']: " . ($tour['id'] ?? 'N/A') . "\n";
    echo "tour['tour_id']: " . ($tour['tour_id'] ?? 'N/A') . "\n";
    echo "tour['code']: " . ($tour['code'] ?? 'N/A') . "\n";
    
    // Build endpoint
    $endpoint = $periodsEndpoint;
    if ($endpoint && preg_match_all('/\{([^}]+)\}/', $endpoint, $matches)) {
        foreach ($matches[1] as $fieldName) {
            $value = $tour[$fieldName] ?? $tour['id'] ?? $tour['code'] ?? null;
            echo "Replacing {$fieldName} with: " . ($value ?? 'null') . "\n";
            if ($value !== null) {
                $endpoint = str_replace('{' . $fieldName . '}', $value, $endpoint);
            }
        }
    }
    echo "Final endpoint: " . ($endpoint ?? 'NULL') . "\n\n";
    
    // Fetch periods
    if ($endpoint && !preg_match('/\{[^}]+\}/', $endpoint)) {
        echo "Fetching periods...\n";
        $periodsResult = $adapter->fetchPeriods($endpoint);
        echo "success: " . ($periodsResult->success ? 'YES' : 'NO') . "\n";
        echo "periods count (raw): " . count($periodsResult->periods ?? []) . "\n";
        
        if (!empty($periodsResult->periods)) {
            echo "\nFirst raw period keys: " . implode(', ', array_keys($periodsResult->periods[0])) . "\n";
            
            // Check if nested
            if (isset($periodsResult->periods[0]['tour_period'])) {
                echo "\n** Found nested tour_period! **\n";
                echo "tour_period count: " . count($periodsResult->periods[0]['tour_period']) . "\n";
            }
        }
    }
}
