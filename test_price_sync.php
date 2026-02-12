<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$t = App\Models\Tour::where('tour_code', 'like', '%VDAD43VZ%')->first();
if (!$t) {
    echo "Tour not found. Trying first tour with discount...\n";
    $t = App\Models\Tour::whereNotNull('discount_adult')->where('discount_adult', '>', 0)->first();
}
if (!$t) { echo "No tour with discount found.\n"; exit; }
echo "Tour: {$t->tour_code}\n";
echo "BEFORE: price_adult={$t->price_adult} | min_price={$t->min_price} | display_price={$t->display_price} | discount_adult={$t->discount_adult}\n";

$t->syncPricesFromPeriods();
$t->refresh();

echo "AFTER:  price_adult={$t->price_adult} | min_price={$t->min_price} | display_price={$t->display_price} | discount_adult={$t->discount_adult}\n";
