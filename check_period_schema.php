<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get periods table columns
$columns = DB::select("SHOW COLUMNS FROM periods");
echo "=== periods table columns ===" . PHP_EOL;
foreach ($columns as $col) {
    echo "  {$col->Field} ({$col->Type})" . ($col->Null === 'YES' ? ' NULL' : ' NOT NULL') . PHP_EOL;
}

// Check Period model fillable
$period = new App\Models\Period();
echo PHP_EOL . "=== Period fillable ===" . PHP_EOL;
$fillable = $period->getFillable();
echo implode(', ', $fillable) . PHP_EOL;

// Check price-related columns
echo PHP_EOL . "=== Price columns check ===" . PHP_EOL;
$priceColumns = array_filter($columns, function($c) {
    return str_contains($c->Field, 'price') || str_contains($c->Field, 'commission') || str_contains($c->Field, 'cost');
});
foreach ($priceColumns as $col) {
    echo "  {$col->Field}: {$col->Type}" . PHP_EOL;
}

// Check offers table
try {
    $offerCols = DB::select("SHOW COLUMNS FROM offers");
    echo PHP_EOL . "=== offers table columns ===" . PHP_EOL;
    foreach ($offerCols as $col) {
        echo "  {$col->Field} ({$col->Type})" . PHP_EOL;
    }
} catch (Exception $e) {
    echo PHP_EOL . "No offers table" . PHP_EOL;
}

// Check a raw period
$raw = DB::table('periods')->where('tour_id', 3354)->first();
if ($raw) {
    echo PHP_EOL . "=== Raw period data ===" . PHP_EOL;
    foreach ((array)$raw as $k => $v) {
        if ($v !== null) {
            echo "  {$k}: " . mb_substr((string)$v, 0, 60) . PHP_EOL;
        }
    }
}
