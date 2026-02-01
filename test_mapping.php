<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mapper = new \App\Services\WholesalerAdapters\Mapper\SectionMapper();
$mapper->loadMappings(1);

$log = \App\Models\OutboundApiLog::latest()->first();
$data = json_decode($log->response_body, true);

if (is_array($data) && isset($data[0])) {
    echo "Found " . count($data) . " tours\n";
    
    $result = $mapper->mapTour($data[0]);
    echo "Success: " . ($result->success ? 'true' : 'false') . "\n";
    
    if (!$result->success) {
        echo "Errors:\n";
        foreach ($result->errors as $error) {
            echo "  - {$error['section']}.{$error['field']}: {$error['error']}\n";
        }
    }
    
    echo "\nMapped Tour Data:\n";
    print_r($result->data['tour'] ?? []);
} else {
    echo "No tour data found\n";
    print_r($data);
}
