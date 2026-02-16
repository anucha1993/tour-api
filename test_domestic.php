<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\DomesticTourSetting;

$thailandId = DomesticTourSetting::THAILAND_ID;
echo "Thailand ID: $thailandId\n";

$allThaiTours = Tour::where('status', 'active')
    ->where('primary_country_id', $thailandId)
    ->count();
echo "Active Thai tours (all): $allThaiTours\n";

$today = now()->toDateString();
$withPeriods = Tour::where('status', 'active')
    ->where('primary_country_id', $thailandId)
    ->whereHas('periods', function($q) use ($today) {
        $q->where('status', 'open')
          ->where('start_date', '>=', $today);
    })
    ->count();
echo "Active Thai tours (with open future periods): $withPeriods\n";

// Test the setting model
$setting = new DomesticTourSetting([
    'conditions' => [],
    'sort_by' => 'popular',
    'display_limit' => 50,
    'per_page' => 10,
    'max_periods_display' => 6,
]);
$tours = $setting->getTours(10, []);
echo "Tours returned by getTours: " . $tours->total() . "\n";

if ($tours->total() > 0) {
    foreach ($tours->items() as $tour) {
        echo "  - [{$tour->tour_code}] {$tour->title}\n";
    }
}

echo "\nDone!\n";
