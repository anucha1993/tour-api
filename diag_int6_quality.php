<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Integration 6 Tour/Period Quality Report ===\n\n";

// 1. Total tours
$totalTours = DB::table('tours')->where('wholesaler_id', 6)->count();
echo "Total tours: {$totalTours}\n";

// 2. Tours with periods
$toursWithPeriods = DB::table('tours')
    ->where('tours.wholesaler_id', 6)
    ->whereExists(function ($q) {
        $q->select(DB::raw(1))->from('periods')->whereColumn('periods.tour_id', 'tours.id');
    })
    ->count();
echo "Tours with periods: {$toursWithPeriods}\n";

// 3. Tours WITHOUT periods
$toursWithoutPeriods = $totalTours - $toursWithPeriods;
echo "Tours WITHOUT periods: {$toursWithoutPeriods}\n";

// 4. Period price analysis
$totalPeriods = DB::table('periods')
    ->whereIn('tour_id', DB::table('tours')->where('wholesaler_id', 6)->select('id'))
    ->count();
echo "\nTotal periods: {$totalPeriods}\n";

// Periods with no adult price (0 or null)
$noPrice = DB::table('periods')
    ->whereIn('tour_id', DB::table('tours')->where('wholesaler_id', 6)->select('id'))
    ->where(function ($q) {
        $q->whereNull('adult_price')->orWhere('adult_price', 0)->orWhere('adult_price', '');
    })
    ->count();
echo "Periods with NO adult price (0/null): {$noPrice}\n";

$hasPrice = $totalPeriods - $noPrice;
echo "Periods WITH adult price: {$hasPrice}\n";

// 5. Future periods only
$futurePeriods = DB::table('periods')
    ->whereIn('tour_id', DB::table('tours')->where('wholesaler_id', 6)->select('id'))
    ->where('start_date', '>=', now()->toDateString())
    ->count();
echo "\nFuture periods: {$futurePeriods}\n";

$futurePriced = DB::table('periods')
    ->whereIn('tour_id', DB::table('tours')->where('wholesaler_id', 6)->select('id'))
    ->where('start_date', '>=', now()->toDateString())
    ->where('adult_price', '>', 0)
    ->count();
echo "Future periods WITH price: {$futurePriced}\n";

$futureNoPrice = $futurePeriods - $futurePriced;
echo "Future periods WITHOUT price: {$futureNoPrice}\n";

// 6. Tours that have ONLY zero-price periods (problematic tours)
echo "\n=== Tours with ONLY zero/no price periods ===\n";
$tourIds = DB::table('tours')->where('wholesaler_id', 6)->pluck('id');
$badTours = [];
foreach ($tourIds as $tid) {
    $total = DB::table('periods')->where('tour_id', $tid)->count();
    if ($total == 0) continue;
    $priced = DB::table('periods')->where('tour_id', $tid)->where('adult_price', '>', 0)->count();
    if ($priced == 0) {
        $tour = DB::table('tours')->where('id', $tid)->first(['id', 'tour_code', 'title', 'created_at']);
        $badTours[] = $tour;
    }
}
echo "Tours with periods but ALL zero-price: " . count($badTours) . "\n";
foreach (array_slice($badTours, 0, 5) as $bt) {
    echo "  ID:{$bt->id} {$bt->tour_code} | " . mb_substr($bt->title, 0, 60) . "\n";
}

// 7. Sample period data from screenshot tour
echo "\n=== Sample: Look for tour with code like NT202603352 ===\n";
$sampleTour = DB::table('tours')
    ->where('wholesaler_id', 6)
    ->where(function ($q) {
        $q->where('tour_code', 'like', '%NT202603352%')
          ->orWhere('wholesaler_tour_code', 'like', '%NT202603352%');
    })
    ->first();
if (!$sampleTour) {
    // Try searching by title keywords
    $sampleTour = DB::table('tours')
        ->where('wholesaler_id', 6)
        ->where('title', 'like', '%คุนหมิง%ลี่เจียง%')
        ->first();
}
if ($sampleTour) {
    echo "Found: ID:{$sampleTour->id} code:{$sampleTour->tour_code} wcode:{$sampleTour->wholesaler_tour_code}\n";
    echo "Title: " . mb_substr($sampleTour->title, 0, 80) . "\n";
    $periods = DB::table('periods')->where('tour_id', $sampleTour->id)->orderBy('start_date')->get();
    echo "Periods: " . $periods->count() . "\n";
    foreach ($periods->take(5) as $p) {
        echo "  {$p->start_date} | adult:{$p->adult_price} single:{$p->single_supplement} child_bed:{$p->child_with_bed_price} child_no_bed:{$p->child_no_bed_price} | cap:{$p->capacity} booked:{$p->booked} avail:{$p->available_seats}\n";
    }
} else {
    echo "Tour not found\n";
}

// 8. Check period columns available
echo "\n=== Period column sample ===\n";
$samplePeriod = DB::table('periods')
    ->whereIn('tour_id', DB::table('tours')->where('wholesaler_id', 6)->select('id'))
    ->where('adult_price', '>', 0)
    ->first();
if ($samplePeriod) {
    foreach ((array)$samplePeriod as $k => $v) {
        if (is_string($v) && strlen($v) > 100) $v = substr($v, 0, 100) . '...';
        echo "  {$k}: {$v}\n";
    }
}

// 9. Check field mappings for departure section
echo "\n=== Departure field mappings (integration 6) ===\n";
$depMappings = DB::table('wholesaler_field_mappings')
    ->where('wholesaler_id', 6)
    ->where('section_name', 'departure')
    ->where('is_active', 1)
    ->get();
foreach ($depMappings as $m) {
    echo "  {$m->their_field} -> {$m->our_field} (transform: " . ($m->transform_type ?? 'direct') . ")\n";
}

// 10. Check promotion field mappings
echo "\n=== Promotion field mappings (integration 6) ===\n";
$promoMappings = DB::table('wholesaler_field_mappings')
    ->where('wholesaler_id', 6)
    ->where('section_name', 'promotion')
    ->where('is_active', 1)
    ->get();
foreach ($promoMappings as $m) {
    echo "  {$m->their_field} -> {$m->our_field} (transform: " . ($m->transform_type ?? 'direct') . ", default: " . ($m->default_value ?? 'null') . ")\n";
}
