<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Tour;
use App\Models\Period;
use Illuminate\Support\Facades\DB;

echo "=== Tour Statistics ===\n\n";

// Total tours
$total = Tour::count();
echo "Total tours: {$total}\n\n";

// Tour statuses
$statuses = Tour::select('status', DB::raw('count(*) as count'))
    ->groupBy('status')
    ->get();
echo "Tour statuses:\n";
foreach($statuses as $s) {
    echo "  {$s->status}: {$s->count}\n";
}
echo "\n";

// Published status
$published = Tour::where('is_published', true)->count();
$unpublished = Tour::where('is_published', false)->count();
$nullPub = Tour::whereNull('is_published')->count();
echo "is_published=true: {$published}\n";
echo "is_published=false: {$unpublished}\n";
echo "is_published=null: {$nullPub}\n\n";

// Check active tour
$active = Tour::where('status', 'active')->first();
if ($active) {
    echo "Active tour found:\n";
    echo "  ID: {$active->id}\n";
    echo "  Name: {$active->name}\n";
    echo "  is_published: " . ($active->is_published ? 'true' : 'false') . "\n";
    echo "  primary_country_id: {$active->primary_country_id}\n\n";
}

// Periods
echo "=== Period Statistics ===\n\n";
$periodStatuses = Period::select('status', DB::raw('count(*) as count'))
    ->groupBy('status')
    ->get();
echo "Period statuses:\n";
foreach($periodStatuses as $s) {
    echo "  {$s->status}: {$s->count}\n";
}
echo "\n";

$futurePeriods = Period::where('status', 'open')
    ->where('start_date', '>=', now()->toDateString())
    ->count();
echo "Future open periods: {$futurePeriods}\n\n";

// Tours with future periods (NEW - without is_published check)
$toursWithFuturePeriods = Tour::where('status', 'active')
    ->whereHas('periods', function($q) {
        $q->where('status', 'open')
          ->where('start_date', '>=', now()->toDateString());
    })
    ->count();
echo "Tours matching all conditions (status=active + future periods): {$toursWithFuturePeriods}\n\n";

// Get countries with tour counts
echo "=== Countries with matching tours ===\n\n";
$countries = DB::table('tours')
    ->join('countries', 'tours.primary_country_id', '=', 'countries.id')
    ->where('tours.status', 'active')
    ->whereExists(function($q) {
        $q->select(DB::raw(1))
          ->from('periods')
          ->whereRaw('periods.tour_id = tours.id')
          ->where('periods.status', 'open')
          ->where('periods.start_date', '>=', now()->toDateString());
    })
    ->select('countries.id', 'countries.name_th', 'countries.name_en', 'countries.flag_emoji', DB::raw('COUNT(tours.id) as tour_count'))
    ->groupBy('countries.id', 'countries.name_th', 'countries.name_en', 'countries.flag_emoji')
    ->orderByDesc('tour_count')
    ->limit(10)
    ->get();

if ($countries->isEmpty()) {
    echo "No countries found!\n";
} else {
    foreach($countries as $c) {
        echo "{$c->flag_emoji} {$c->name_th} ({$c->name_en}): {$c->tour_count} tours\n";
    }
}

// Check Popular Country Settings
use App\Models\PopularCountrySetting;

echo "\n=== Popular Country Settings ===\n\n";
$settings = PopularCountrySetting::all();
if ($settings->isEmpty()) {
    echo "No settings found!\n";
} else {
    foreach ($settings as $s) {
        echo "ID: {$s->id}\n";
        echo "  Name: {$s->name}\n";
        echo "  Slug: {$s->slug}\n";
        echo "  Mode: {$s->selection_mode}\n";
        echo "  Active: " . ($s->is_active ? 'Yes' : 'No') . "\n";
        echo "\n";
    }
}

// Test homepage setting
echo "=== Test Homepage Setting ===\n\n";
$homepage = PopularCountrySetting::where('slug', 'homepage')
    ->where('is_active', true)
    ->first();

if ($homepage) {
    echo "Found homepage setting!\n";
    $result = $homepage->getPopularCountries();
    echo "Countries returned: " . count($result) . "\n";
    foreach ($result as $c) {
        echo "  - {$c['flag_emoji']} {$c['name_th']} ({$c['tour_count']} tours)\n";
    }
} else {
    echo "No active homepage setting found.\n";
    echo "Please create a setting with slug='homepage' and is_active=true\n";
}
