<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\TourItinerary;

$tourIds = Tour::where('wholesaler_id', 6)->pluck('id');
echo "Deleting itineraries for " . $tourIds->count() . " tours (wholesaler_id=6)...\n";

$deleted = TourItinerary::whereIn('tour_id', $tourIds)->delete();
echo "Deleted: {$deleted} itineraries\n";

// Verify
$remaining = TourItinerary::whereIn('tour_id', $tourIds)->count();
echo "Remaining: {$remaining}\n";
