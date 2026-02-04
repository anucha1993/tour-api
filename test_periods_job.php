<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Jobs\SyncPeriodsJob;
use Illuminate\Support\Facades\DB;

$tourId = $argv[1] ?? 294;
$tour = DB::table('tours')->where('id', $tourId)->first();

if (!$tour) {
    echo "Tour ID {$tourId} not found!\n";
    exit(1);
}

echo "=== Testing SyncPeriodsJob for Tour ID {$tourId} ===\n\n";
echo "Tour: {$tour->title}\n";
echo "External ID: {$tour->external_id}\n";
echo "Wholesaler ID: {$tour->wholesaler_id}\n\n";

// Run SyncPeriodsJob
echo "=== Running SyncPeriodsJob ===\n";
$job = new SyncPeriodsJob($tourId, $tour->external_id, $tour->wholesaler_id);
$job->handle();

// Check results
echo "\n=== Results ===\n";
$periodsCount = DB::table('periods')->where('tour_id', $tourId)->count();
$itinCount = DB::table('tour_itineraries')->where('tour_id', $tourId)->count();
$citiesCount = DB::table('tour_cities')->where('tour_id', $tourId)->count();
$transportCount = DB::table('tour_transports')->where('tour_id', $tourId)->count();

echo "Periods: {$periodsCount}\n";
echo "Itineraries: {$itinCount}\n";
echo "Cities: {$citiesCount}\n";
echo "Transports: {$transportCount}\n";

// Show sample itinerary
if ($itinCount > 0) {
    echo "\n=== Sample Itinerary ===\n";
    $itin = DB::table('tour_itineraries')->where('tour_id', $tourId)->first();
    echo "Day {$itin->day_number}: {$itin->title}\n";
}

// Show sample cities
if ($citiesCount > 0) {
    echo "\n=== Tour Cities ===\n";
    $cities = DB::table('tour_cities')
        ->leftJoin('cities', 'tour_cities.city_id', '=', 'cities.id')
        ->where('tour_cities.tour_id', $tourId)
        ->select('cities.name_th', 'cities.name_en', 'tour_cities.city_id')
        ->get();
    foreach ($cities as $city) {
        echo "  - {$city->name_th} ({$city->name_en}) [ID: {$city->city_id}]\n";
    }
}

echo "\n=== Done ===\n";
