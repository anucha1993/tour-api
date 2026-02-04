<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WholesalerApiConfig;

$creds = [
    'headers' => [
        'x-api-key' => 'eyJhbGciOiJIUzUxMiJ9.eyJhcGlfaWQiOjgsImFnZW50X2lkIjoxMDA3LCJ1c2VyX2lkIjoxNzQ5Nn0.NBXKegA03NYe8AI-9_EBgY4Zsu9PD3v4ppif-V6R7Y97njaYH2SKmrDZiV1gXaagZGOzrBNo8AottbJZIm4KpQ',
        'Content-Type' => 'application/json'
    ],
    'endpoints' => [
        'tours' => 'https://api.2ucenter.com/api/v1/tours/search',
        'periods' => 'https://api.2ucenter.com/api/v1/tours/detail/{tour_id}'
    ]
];

// Use Model to properly encrypt credentials
$config = WholesalerApiConfig::find(11);
if ($config) {
    $config->auth_credentials = $creds;
    $config->save();
    echo "Updated auth_credentials with encryption\n";
    
    // Verify
    $config->refresh();
    echo "Verified auth_credentials:\n";
    echo json_encode($config->auth_credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    echo "ERROR: Integration 11 not found\n";
}
