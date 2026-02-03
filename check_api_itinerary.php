<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\WholesalerAdapters\AdapterFactory;

$wholesalerId = $argv[1] ?? 3;

$config = \App\Models\WholesalerApiConfig::where('wholesaler_id', $wholesalerId)->first();

if (!$config) {
    echo "Config not found!\n";
    exit;
}

// Fetch from API
$adapter = AdapterFactory::create($wholesalerId);
$result = $adapter->fetchTours();
$response = $result->tours;

if (empty($response)) {
    echo "No data from API\n";
    exit;
}

$tour = $response[0];

echo "=== API Response Structure ===\n";
echo "Tour Keys: " . implode(', ', array_keys($tour)) . "\n\n";

// Check for itinerary/plans data
$itineraryKeys = ['plans', 'itinerary', 'itineraries', 'days', 'schedule', 'programs'];

foreach ($itineraryKeys as $key) {
    if (isset($tour[$key])) {
        echo "Found: {$key}\n";
        echo "Type: " . gettype($tour[$key]) . "\n";
        
        if (is_array($tour[$key]) && !empty($tour[$key])) {
            echo "Count: " . count($tour[$key]) . "\n";
            echo "\nFirst item structure:\n";
            $first = $tour[$key][0];
            if (is_array($first)) {
                foreach ($first as $k => $v) {
                    $val = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v;
                    $val = mb_substr((string)$val, 0, 100);
                    echo "  {$k}: {$val}\n";
                }
            } else {
                echo "  Value: " . print_r($first, true) . "\n";
            }
        }
        echo "\n";
    }
}
