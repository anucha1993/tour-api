<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check what tour was synced
$log = \App\Models\SyncLog::where('wholesaler_id', 3)->latest()->first();
if ($log && isset($log->details['tours'])) {
    $tours = $log->details['tours'];
    echo "Tours in last sync:\n";
    foreach ($tours as $t) {
        echo "  - " . ($t['tour_code'] ?? $t['wholesaler_tour_code'] ?? 'N/A') . "\n";
    }
} else {
    echo "No tour details in log\n";
}

// Check raw data from API
echo "\n=== Raw data from API (first tour) ===\n";
$config = \App\Models\WholesalerApiConfig::where('wholesaler_id', 3)->first();
$adapter = \App\Services\WholesalerAdapters\AdapterFactory::create(3);
$result = $adapter->fetchTours(null);

if ($result->success && !empty($result->tours)) {
    $tour = $result->tours[0];
    echo "Tour code: " . ($tour['code'] ?? 'N/A') . "\n";
    echo "Vehicle (transport): " . ($tour['vehicle'] ?? 'N/A') . "\n";
    echo "Countries: " . json_encode($tour['countries'] ?? []) . "\n";
}
