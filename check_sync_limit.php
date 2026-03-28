<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$config = App\Models\WholesalerApiConfig::find(25);
echo "sync_limit: " . var_export($config->sync_limit, true) . PHP_EOL;
