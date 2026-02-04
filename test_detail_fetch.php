<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\AdapterFactory;

// Get integration config for wholesaler_id = 7 (Integration 11)
$config = WholesalerApiConfig::where('wholesaler_id', 7)->first();
if (!$config) {
    echo "Config not found\n";
    exit;
}

echo "Found config for wholesaler: " . $config->wholesaler_id . "\n";
$authCredentials = is_array($config->auth_credentials) ? $config->auth_credentials : json_decode($config->auth_credentials, true);
echo "Endpoints: " . json_encode($authCredentials['endpoints'] ?? []) . "\n";

// Create adapter using factory
$adapter = AdapterFactory::create($config->wholesaler_id);
echo "Adapter: " . get_class($adapter) . "\n";

echo "\nTesting fetchTourDetail for tour 14346...\n";
$detail = $adapter->fetchTourDetail('14346');

if ($detail) {
    echo "SUCCESS!\n";
    
    // Debug the structure
    if (is_array($detail)) {
        if (isset($detail[0]) && is_array($detail[0])) {
            // It's a numeric array with one element
            echo "Response is an array with " . count($detail) . " element(s)\n";
            $tour = $detail[0];
            echo "Tour Keys: " . implode(', ', array_keys($tour)) . "\n";
            
            if (isset($tour['periods'])) {
                echo "periods count: " . count($tour['periods']) . "\n";
            }
            if (isset($tour['tour_daily'])) {
                echo "tour_daily count: " . count($tour['tour_daily']) . "\n";
            }
        } else {
            echo "Keys: " . implode(', ', array_keys($detail)) . "\n";
        }
    }
} else {
    echo "FAILED - null returned\n";
}
