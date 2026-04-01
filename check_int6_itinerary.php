<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\TourItinerary;

$tourIds = Tour::where('wholesaler_id', 6)->pluck('id');
$tourCount = $tourIds->count();
$itinCount = TourItinerary::whereIn('tour_id', $tourIds)->count();

echo "Integration 6 tours: {$tourCount}\n";
echo "Integration 6 itineraries: {$itinCount}\n";

// Show sample itinerary data
$sample = TourItinerary::whereIn('tour_id', $tourIds)->first();
if ($sample) {
    echo "\nSample itinerary:\n";
    echo "  tour_id: {$sample->tour_id}\n";
    echo "  external_id: {$sample->external_id}\n";
    echo "  day_number: {$sample->day_number}\n";
    echo "  title: " . mb_substr($sample->title ?? '', 0, 80) . "\n";
    echo "  description: " . mb_substr($sample->description ?? '', 0, 80) . "\n";
}
