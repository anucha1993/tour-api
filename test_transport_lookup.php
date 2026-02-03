<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$value = 'CHINA SOUTHERN AIRLINE (CZ)';

echo "Testing transport lookup for: $value\n\n";

// Step 1: Extract code from parentheses
if (preg_match('/\(([A-Z0-9]{2,3})\)/', $value, $matches)) {
    $code = $matches[1];
    echo "Extracted code: $code\n";
    
    $transport = \App\Models\Transport::where('code', $code)
        ->orWhere('code1', $code)
        ->first();
    
    if ($transport) {
        echo "Found by code: ID={$transport->id}, Name={$transport->name}\n";
    } else {
        echo "Not found by code\n";
    }
}

// Step 2: Clean name (remove parentheses)
$cleanName = preg_replace('/\s*\([^)]+\)\s*/', '', $value);
$cleanName = trim($cleanName);
echo "\nClean name: $cleanName\n";

$transport2 = \App\Models\Transport::where('name', 'LIKE', '%' . $cleanName . '%')->first();
if ($transport2) {
    echo "Found by clean name: ID={$transport2->id}, Name={$transport2->name}\n";
} else {
    echo "Not found by clean name\n";
}
