<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\AdapterFactory;
use App\Services\WholesalerAdapters\Adapters\GenericRestAdapter;

// Get Integration 11 (2ucenter, wholesaler_id 7)
$config = WholesalerApiConfig::where('id', 11)->first();

if (!$config) {
    echo "ERROR: Integration 11 not found\n";
    exit(1);
}

echo "=== Debug 2ucenter Realtime Search (Integration 11) ===\n\n";

echo "ID: {$config->id}\n";
echo "wholesaler_id: {$config->wholesaler_id}\n";
echo "sync_mode: " . ($config->sync_mode ?? 'null') . "\n";
echo "is_active: " . ($config->is_active ? 'YES' : 'NO') . "\n\n";

// Check base_url
echo "base_url: " . ($config->base_url ?? 'NULL') . "\n\n";

// Check auth_credentials
$credentials = $config->auth_credentials ?? [];
echo "auth_credentials:\n";
echo json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// Check endpoints
$periodsEndpoint = $credentials['endpoints']['periods'] ?? null;
echo "periods endpoint: " . ($periodsEndpoint ?? 'NULL') . "\n\n";

// Get adapter
try {
    $adapter = AdapterFactory::create($config->wholesaler_id);
    echo "adapter class: " . get_class($adapter) . "\n";
    echo "is GenericRestAdapter: " . ($adapter instanceof GenericRestAdapter ? 'YES' : 'NO') . "\n\n";
} catch (Exception $e) {
    echo "ERROR creating adapter: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Fetch tours
echo "Fetching tours...\n";
$result = $adapter->fetchTours(null);
echo "success: " . ($result->success ? 'YES' : 'NO') . "\n";
if (!$result->success) {
    echo "error: " . ($result->errorMessage ?? 'unknown') . "\n";
    exit(1);
}

echo "tours count: " . count($result->tours) . "\n\n";

if (!empty($result->tours)) {
    $tour = $result->tours[0];
    echo "=== First Tour ===\n";
    echo "Keys: " . implode(', ', array_keys($tour)) . "\n\n";
    
    // Show some important fields
    echo "tour_id: " . ($tour['tour_id'] ?? 'N/A') . "\n";
    echo "tour_code: " . ($tour['tour_code'] ?? 'N/A') . "\n";
    echo "external_id: " . ($tour['external_id'] ?? 'N/A') . "\n";
    echo "id: " . ($tour['id'] ?? 'N/A') . "\n";
    echo "tour_name: " . mb_substr($tour['tour_name'] ?? 'N/A', 0, 60) . "...\n\n";
    
    // Try to build periods endpoint
    if ($periodsEndpoint) {
        echo "Building periods endpoint...\n";
        $endpoint = $periodsEndpoint;
        
        if (preg_match_all('/\{([^}]+)\}/', $periodsEndpoint, $matches)) {
            foreach ($matches[1] as $fieldName) {
                // Try multiple sources
                $value = $tour[$fieldName] 
                    ?? $tour['tour_' . $fieldName] 
                    ?? $tour[str_replace('tour_', '', $fieldName)]
                    ?? null;
                    
                echo "  Placeholder: {$fieldName}\n";
                echo "    tour[{$fieldName}] = " . ($tour[$fieldName] ?? 'N/A') . "\n";
                echo "    tour[tour_{$fieldName}] = " . ($tour['tour_' . $fieldName] ?? 'N/A') . "\n";
                echo "    Selected value: " . ($value ?? 'NULL') . "\n";
                
                if ($value !== null) {
                    $endpoint = str_replace('{' . $fieldName . '}', $value, $endpoint);
                }
            }
        }
        
        echo "\nFinal endpoint: " . $endpoint . "\n";
        
        // Check if all placeholders are resolved
        if (!preg_match('/\{[^}]+\}/', $endpoint)) {
            echo "\nFetching periods from this endpoint...\n";
            $periodsResult = $adapter->fetchPeriods($endpoint);
            echo "success: " . ($periodsResult->success ? 'YES' : 'NO') . "\n";
            
            if ($periodsResult->success) {
                echo "periods count: " . count($periodsResult->periods ?? []) . "\n";
            } else {
                echo "error: " . ($periodsResult->errorMessage ?? 'unknown') . "\n";
            }
        } else {
            echo "\n** ERROR: Unresolved placeholders in endpoint! **\n";
        }
    } else {
        echo "** No periods endpoint configured **\n";
    }
}
