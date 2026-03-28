<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$cols = DB::select("SHOW COLUMNS FROM wholesaler_field_mappings WHERE Field = 'transform_type'");
echo "transform_type: " . $cols[0]->Type . PHP_EOL;

// Also check if date_format existed in migration
$migrations = DB::select("SELECT * FROM migrations WHERE migration LIKE '%field_mapping%'");
foreach ($migrations as $m) {
    echo "Migration: " . $m->migration . PHP_EOL;
}
