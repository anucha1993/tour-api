<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Set sync_limit back to 10
$config = App\Models\WholesalerApiConfig::find(25);
$config->update(['sync_limit' => 10]);
echo "sync_limit set to 10" . PHP_EOL;

// Delete existing tours to re-test cleanly
$tours = App\Models\Tour::where('wholesaler_id', 57)->get();
echo "Deleting " . $tours->count() . " existing tours..." . PHP_EOL;

foreach ($tours as $tour) {
    // Delete offers
    $periodIds = $tour->periods()->pluck('id');
    App\Models\Offer::whereIn('period_id', $periodIds)->delete();
    // Delete periods
    $tour->periods()->delete();
    // Delete pivot data
    $tour->countries()->detach();
    $tour->cities()->detach();
    $tour->transports()->delete();
    $tour->delete();
}
echo "Done! Clean slate for re-test." . PHP_EOL;
