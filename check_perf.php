<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tour;

$today = now()->toDateString();

echo "=== Tour Counts ===" . PHP_EOL;
$total = Tour::where('status', 'active')->count();
echo "Total active: $total" . PHP_EOL;

$denorm = Tour::where('status', 'active')
    ->whereNotNull('next_departure_date')
    ->where('next_departure_date', '>=', $today)
    ->count();
echo "next_departure_date >= today: $denorm" . PHP_EOL;

$wherehas = Tour::where('status', 'active')
    ->whereHas('periods', function($q) use ($today) {
        $q->where('start_date', '>=', $today)->where('status', 'open');
    })
    ->count();
echo "whereHas periods: $wherehas" . PHP_EOL;

$avail = Tour::where('status', 'active')
    ->where('available_seats', '>', 0)
    ->count();
echo "available_seats > 0: $avail" . PHP_EOL;

echo PHP_EOL . "=== Query Timing ===" . PHP_EOL;

// Test 1: whereHas approach (current)
$t1 = microtime(true);
$q1 = Tour::where('status', 'active')
    ->whereHas('periods', function($q) use ($today) {
        $q->where('start_date', '>=', $today)->where('status', 'open');
    })
    ->where(function($q) { $q->where('primary_country_id', '!=', 8)->orWhereNull('primary_country_id'); })
    ->where('primary_country_id', 3) // china
    ->with([
        'primaryCountry:id,name_th,name_en,iso2,flag_emoji',
        'cities:id,name_th,name_en,slug',
        'transports' => fn($q) => $q->orderBy('sort_order'),
        'transports.transport:id,code,name,image',
        'periods' => fn($q) => $q->where('is_visible', true)->orderBy('start_date')->limit(6),
        'periods.offer.promotion',
        'itineraries' => fn($q) => $q->select('id', 'tour_id', 'has_breakfast', 'has_lunch', 'has_dinner'),
    ])
    ->paginate(10, ['*'], 'page', 2);
$ms1 = round((microtime(true) - $t1) * 1000);
echo "whereHas approach: {$ms1}ms ({$q1->total()} total)" . PHP_EOL;

// Test 2: denormalized approach (proposed)
$t2 = microtime(true);
$q2 = Tour::where('status', 'active')
    ->whereNotNull('next_departure_date')
    ->where('next_departure_date', '>=', $today)
    ->where(function($q) { $q->where('primary_country_id', '!=', 8)->orWhereNull('primary_country_id'); })
    ->where('primary_country_id', 3) // china
    ->with([
        'primaryCountry:id,name_th,name_en,iso2,flag_emoji',
        'cities:id,name_th,name_en,slug',
        'transports' => fn($q) => $q->orderBy('sort_order'),
        'transports.transport:id,code,name,image',
        'periods' => fn($q) => $q->where('is_visible', true)->orderBy('start_date')->limit(6),
        'periods.offer.promotion',
        'itineraries' => fn($q) => $q->select('id', 'tour_id', 'has_breakfast', 'has_lunch', 'has_dinner'),
    ])
    ->paginate(10, ['*'], 'page', 2);
$ms2 = round((microtime(true) - $t2) * 1000);
echo "denormalized approach: {$ms2}ms ({$q2->total()} total)" . PHP_EOL;

echo PHP_EOL . "Speedup: " . ($ms1 > 0 ? round($ms1 / max($ms2, 1), 1) : '?') . "x" . PHP_EOL;
