<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WholesalerApiConfig;

// Fix Integration 11 auth_credentials using Model (with proper encryption)
$config = WholesalerApiConfig::find(11);

if (!$config) {
    echo "ERROR: Integration 11 not found\n";
    exit(1);
}

echo "Before fix:\n";
echo "auth_credentials type: " . gettype($config->auth_credentials) . "\n";
echo "auth_credentials: " . json_encode($config->auth_credentials) . "\n\n";

// Set new credentials using Model (will be encrypted automatically)
$config->auth_credentials = [
    'headers' => [
        'x-api-key' => 'eyJhbGciOiJIUzUxMiJ9.eyJhcGlfaWQiOjgsImFnZW50X2lkIjoxMDA3LCJ1c2VyX2lkIjoxNzQ5Nn0.NBXKegA03NYe8AI-9_EBgY4Zsu9PD3v4ppif-V6R7Y97njaYH2SKmrDZiV1gXaagZGOzrBNo8AottbJZIm4KpQ',
        'Content-Type' => 'application/json'
    ],
    'endpoints' => [
        'tours' => 'https://api.2ucenter.com/api/v1/tours/search',
        'periods' => 'https://api.2ucenter.com/api/v1/tours/detail/{tour_id}'
    ]
];
$config->save();

echo "After fix:\n";
$config->refresh();
echo "auth_credentials type: " . gettype($config->auth_credentials) . "\n";
echo "auth_credentials: " . json_encode($config->auth_credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

// Test fetch
echo "\n=== Testing API Fetch ===\n";
$adapter = App\Services\WholesalerAdapters\AdapterFactory::create($config->wholesaler_id);
$result = $adapter->fetchTours(null);
echo "Fetch success: " . ($result->success ? 'YES' : 'NO') . "\n";
if ($result->success) {
    echo "Tours count: " . count($result->tours) . "\n";
} else {
    echo "Error: " . ($result->errorMessage ?? 'unknown') . "\n";
}
