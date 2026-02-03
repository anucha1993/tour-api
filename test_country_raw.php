<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Wholesaler;
use App\Services\UnifiedSearchService;

$wholesaler = Wholesaler::find(3);
$service = new UnifiedSearchService($wholesaler);
$config = \App\Models\WholesalerApiConfig::where('wholesaler_id', 3)->first();
$result = $service->searchWholesaler($config, []);
$tours = $result['tours'] ?? [];

echo "Total tours: " . count($tours) . PHP_EOL;

if (!empty($tours)) {
    echo "\n=== First Tour ===\n";
    $tour = reset($tours); // Get first element regardless of key
    echo "primary_country_id: " . ($tour['primary_country_id'] ?? 'NOT SET') . PHP_EOL;
    echo "transport_id: " . ($tour['transport_id'] ?? 'NOT SET') . PHP_EOL;
    
    $raw = $tour['_raw'] ?? [];
    echo "\nHas 'countries' key: " . (isset($raw['countries']) ? 'YES' : 'NO') . PHP_EOL;
    echo "Has 'country' key: " . (isset($raw['country']) ? 'YES' : 'NO') . PHP_EOL;
    echo "Has 'vehicle' key: " . (isset($raw['vehicle']) ? 'YES' : 'NO') . PHP_EOL;
    
    if (isset($raw['countries'])) {
        echo "countries value: " . json_encode($raw['countries']) . PHP_EOL;
    }
    if (isset($raw['country'])) {
        echo "country value: " . json_encode($raw['country']) . PHP_EOL;
    }
    if (isset($raw['vehicle'])) {
        echo "vehicle value: " . json_encode($raw['vehicle']) . PHP_EOL;
    }
    
    echo "\n=== All Raw Keys ===\n";
    echo implode(', ', array_keys($raw)) . PHP_EOL;
}
