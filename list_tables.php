<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== All Tables ===\n";
$tables = DB::select('SHOW TABLES');
foreach ($tables as $table) {
    $name = array_values((array)$table)[0];
    echo "$name\n";
}
