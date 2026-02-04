<?php
/**
 * Check synced GO365 tour with itinerary and transport
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\Period;
use App\Models\TourItinerary;
use Illuminate\Support\Facades\DB;

// Get GO365 tour
$tour = Tour::find(272);

if ($tour) {
    echo "=== GO365 Tour ===\n";
    echo "ID: {$tour->id}\n";
    echo "Title: {$tour->title}\n";
    echo "Transport ID: " . ($tour->transport_id ?? 'null') . "\n";
    
    // Get transport name
    if ($tour->transport_id) {
        $transport = DB::table('transports')->where('id', $tour->transport_id)->first();
        if ($transport) {
            $name = $transport->name_en ?? $transport->name ?? $transport->name_th ?? 'Unknown';
            $code = $transport->code ?? '';
            echo "Transport: {$name} ({$code})\n";
        }
    }
    
    // Check periods
    $periods = Period::where('tour_id', $tour->id)->get();
    echo "\nPeriods: " . $periods->count() . "\n";
    foreach ($periods->take(3) as $p) {
        echo "  - {$p->start_date} -> {$p->end_date}\n";
    }
    
    // Check itineraries
    $itineraries = TourItinerary::where('tour_id', $tour->id)->orderBy('day_number')->get();
    echo "\nItineraries: " . $itineraries->count() . "\n";
    foreach ($itineraries->take(5) as $i) {
        $title = $i->title ?? 'No title';
        $desc = $i->description ? mb_substr($i->description, 0, 50) . '...' : '';
        echo "  Day {$i->day_number}: {$title}\n";
        if ($desc) echo "    {$desc}\n";
    }
    
    if ($itineraries->count() > 5) {
        echo "  ... and " . ($itineraries->count() - 5) . " more\n";
    }
} else {
    echo "Tour 272 not found\n";
}
