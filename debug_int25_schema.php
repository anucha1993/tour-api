<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check periods table schema
echo "=== periods table columns ===" . PHP_EOL;
$columns = DB::select("SHOW COLUMNS FROM periods");
foreach ($columns as $c) {
    echo "  " . $c->Field . " " . $c->Type . " " . ($c->Null === 'YES' ? 'NULL' : 'NOT NULL') . PHP_EOL;
}

// Check if there are related pricing tables  
echo PHP_EOL . "=== Tables with 'price' or 'pricing' ===" . PHP_EOL;
$tables = DB::select("SHOW TABLES");
$dbName = DB::connection()->getDatabaseName();
foreach ($tables as $t) {
    $name = array_values((array) $t)[0];
    if (stripos($name, 'price') !== false || stripos($name, 'pricing') !== false || stripos($name, 'period_') !== false) {
        echo "  " . $name . PHP_EOL;
    }
}

// Check the SyncToursJob to see which table it writes departures to
echo PHP_EOL . "=== Where does price data get saved? ===" . PHP_EOL;
// Check if period has mass-assignable price fields
$period = new App\Models\Period();
echo "Period fillable: " . implode(', ', $period->getFillable()) . PHP_EOL;
