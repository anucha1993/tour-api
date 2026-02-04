<?php
/**
 * Check all mappings for wholesaler 6
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== All Sections for Wholesaler 6 ===\n\n";

$sections = DB::table('wholesaler_field_mappings')
    ->where('wholesaler_id', 6)
    ->where('is_active', true)
    ->select('section_name', DB::raw('count(*) as cnt'))
    ->groupBy('section_name')
    ->get();

foreach ($sections as $s) {
    echo "{$s->section_name}: {$s->cnt} fields\n";
}

echo "\n=== Itinerary Mappings ===\n";
$itinMappings = DB::table('wholesaler_field_mappings')
    ->where('wholesaler_id', 6)
    ->where('section_name', 'itinerary')
    ->where('is_active', true)
    ->get();

foreach ($itinMappings as $m) {
    $path = $m->their_field_path ?? $m->their_field;
    echo "{$m->our_field} => {$path}\n";
}

echo "\n=== Transport/Tour Mappings (transport related) ===\n";
$tourMappings = DB::table('wholesaler_field_mappings')
    ->where('wholesaler_id', 6)
    ->whereIn('section_name', ['tour', 'content'])
    ->where('is_active', true)
    ->where(function($q) {
        $q->where('our_field', 'like', '%transport%')
          ->orWhere('our_field', 'like', '%airline%')
          ->orWhere('our_field', 'like', '%flight%');
    })
    ->get();

foreach ($tourMappings as $m) {
    $path = $m->their_field_path ?? $m->their_field;
    echo "[{$m->section_name}] {$m->our_field} => {$path}\n";
}

// Check raw GO365 data structure
echo "\n=== GO365 Raw Data Sample (tour + periods) ===\n";
$tour = App\Models\Tour::where('wholesaler_id', 6)->first();
if ($tour) {
    echo "Tour ID: {$tour->id}\n";
    echo "Transport Type: " . ($tour->transport_type ?? 'null') . "\n";
    echo "Airline: " . ($tour->airline ?? 'null') . "\n";
    
    // Check itineraries
    $itineraries = DB::table('tour_itineraries')->where('tour_id', $tour->id)->count();
    echo "\nItineraries count: {$itineraries}\n";
}
