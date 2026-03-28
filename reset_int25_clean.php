<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Delete existing tours for wholesaler 57
$tours = App\Models\Tour::where('wholesaler_id', 57)->get();
echo "Deleting " . $tours->count() . " existing tours..." . PHP_EOL;
foreach ($tours as $tour) {
    $periodIds = $tour->periods()->pluck('id');
    App\Models\Offer::whereIn('period_id', $periodIds)->delete();
    $tour->periods()->delete();
    $tour->countries()->detach();
    $tour->cities()->detach();
    $tour->transports()->delete();
    $tour->delete();
}
echo "Done! Clean slate." . PHP_EOL;
