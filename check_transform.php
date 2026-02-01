<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing fetchAndMapTours ===\n\n";

// Test adapter
$adapter = \App\Services\WholesalerAdapters\AdapterFactory::create(1);
echo "Adapter class: " . get_class($adapter) . PHP_EOL;

// Test fetch
echo "\nFetching tours...\n";
$result = $adapter->fetchTours(null);
echo "Success: " . ($result->success ? 'true' : 'false') . PHP_EOL;
echo "Tours count: " . count($result->tours) . PHP_EOL;
if (!$result->success) {
    echo "Error: " . $result->errorMessage . PHP_EOL;
}

// Test mapper
echo "\n=== Testing SectionMapper ===\n";
$mapper = new \App\Services\WholesalerAdapters\Mapper\SectionMapper();
$mapper->loadMappings(1);

if (!empty($result->tours)) {
    echo "Mapping first tour...\n";
    $mappingResult = $mapper->mapTour($result->tours[0]);
    echo "Mapping success: " . ($mappingResult->success ? 'true' : 'false') . PHP_EOL;
    
    if (!$mappingResult->success) {
        echo "Errors: \n";
        print_r($mappingResult->errors);
    } else {
        echo "Mapped data tour section:\n";
        print_r($mappingResult->data['tour'] ?? []);
    }
} else {
    echo "No tours to map\n";
}
