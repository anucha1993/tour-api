<?php
/**
 * Full check of Tour 272 data
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\TourItinerary;
use App\Models\Period;
use Illuminate\Support\Facades\DB;

$tour = Tour::with(['countries', 'cities'])->find(272);

echo "=== Tour 272 Full Data ===\n\n";

echo "--- Basic Info ---\n";
echo "ID: {$tour->id}\n";
echo "Title: {$tour->title}\n";
echo "Wholesaler Tour Code: {$tour->wholesaler_tour_code}\n";
echo "Duration: {$tour->duration_days} days / {$tour->duration_nights} nights\n";
echo "Min Price: {$tour->min_price}\n";
echo "Next Departure: {$tour->next_departure_date}\n";
echo "Total Departures: {$tour->total_departures}\n";

echo "\n--- Transport ---\n";
if ($tour->transport_id) {
    $transport = DB::table('transports')->where('id', $tour->transport_id)->first();
    if ($transport) {
        echo "ID: {$transport->id}\n";
        echo "Name: " . ($transport->name ?? $transport->name_th ?? 'N/A') . "\n";
        echo "Code: " . ($transport->code ?? 'N/A') . "\n";
    }
} else {
    echo "No transport assigned\n";
}

echo "\n--- Countries ---\n";
foreach ($tour->countries as $c) {
    echo "  - {$c->name_th} ({$c->code})\n";
}

echo "\n--- Cities ---\n";
foreach ($tour->cities as $c) {
    echo "  - {$c->name_th}\n";
}

echo "\n--- Periods (first 5) ---\n";
$periods = Period::where('tour_id', 272)->with('offer')->orderBy('start_date')->take(5)->get();
foreach ($periods as $p) {
    $price = $p->offer ? $p->offer->price_adult : 'N/A';
    echo "  - {$p->start_date} to {$p->end_date} | Price: {$price} | Available: {$p->available}/{$p->capacity}\n";
}

echo "\n--- Itineraries (first 5) ---\n";
$itins = TourItinerary::where('tour_id', 272)->orderBy('day_number')->take(5)->get();
foreach ($itins as $i) {
    $title = mb_substr($i->title ?? 'No title', 0, 40);
    $desc = $i->description ? mb_substr($i->description, 0, 50) . '...' : '';
    echo "  Day {$i->day_number}: {$title}\n";
    if ($desc) echo "    {$desc}\n";
}

$totalItins = TourItinerary::where('tour_id', 272)->count();
if ($totalItins > 5) {
    echo "  ... and " . ($totalItins - 5) . " more\n";
}
