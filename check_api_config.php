<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== wholesaler_api_configs Structure ===\n";
$columns = DB::select('DESCRIBE wholesaler_api_configs');
foreach ($columns as $c) {
    echo "  {$c->Field} - {$c->Type}\n";
}

echo "\n=== aggregation_config ของ GO365 ===\n";
$config = DB::table('wholesaler_api_configs')->where('wholesaler_id', 6)->first();
echo "aggregation_config: " . ($config->aggregation_config ?? 'NULL') . "\n";
