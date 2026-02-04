<?php
/**
 * Check synced GO365 tour
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\Period;

// Get latest synced tour from GO365 (wholesaler_id = 6)
$tour = Tour::where('wholesaler_id', 6)
    ->orderBy('updated_at', 'desc')
    ->first();

if ($tour) {
    echo "=== Latest GO365 Tour ===\n";
    echo "ID: {$tour->id}\n";
    echo "Title: {$tour->title}\n";
    echo "Code: {$tour->tour_code}\n";
    echo "Wholesaler Code: {$tour->wholesaler_tour_code}\n";
    echo "Updated: {$tour->updated_at}\n";
    
    // Check periods
    $periods = Period::where('tour_id', $tour->id)->get();
    echo "\nPeriods: " . $periods->count() . "\n";
    foreach ($periods->take(5) as $p) {
        echo "  - {$p->departure_date} -> {$p->return_date} (Price: {$p->price_adult})\n";
    }
    
    // Check itineraries
    $itineraries = \App\Models\Itinerary::where('tour_id', $tour->id)->orderBy('day_number')->get();
    echo "\nItineraries: " . $itineraries->count() . "\n";
    foreach ($itineraries->take(3) as $i) {
        echo "  Day {$i->day_number}: " . substr($i->title ?? $i->description ?? 'No title', 0, 60) . "...\n";
    }
} else {
    echo "No GO365 tours found\n";
}
