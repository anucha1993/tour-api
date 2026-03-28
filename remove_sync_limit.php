<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Remove sync limit for integration 25
$config = App\Models\WholesalerApiConfig::find(25);
echo "Before: sync_limit = " . var_export($config->sync_limit, true) . PHP_EOL;
$config->update(['sync_limit' => null]);
echo "After: sync_limit = " . var_export($config->fresh()->sync_limit, true) . PHP_EOL;
echo "Done!" . PHP_EOL;
