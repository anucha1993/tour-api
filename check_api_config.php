<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WholesalerApiConfig;

$config = WholesalerApiConfig::where('wholesaler_id', 6)->first();

echo "=== Auth Credentials - Endpoints ===\n";
$creds = $config->auth_credentials;
print_r($creds['endpoints'] ?? 'No endpoints');

echo "\n=== Aggregation Config ===\n";
print_r($config->aggregation_config);
