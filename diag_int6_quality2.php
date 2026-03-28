<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Integration 6 Tour/Period/Offer Quality Report ===\n\n";

$tourIds = DB::table('tours')->where('wholesaler_id', 6)->pluck('id');
echo "Total tours: " . $tourIds->count() . "\n";

// Periods
$totalPeriods = DB::table('periods')->whereIn('tour_id', $tourIds)->count();
echo "Total periods: {$totalPeriods}\n";

$futurePeriods = DB::table('periods')->whereIn('tour_id', $tourIds)->where('start_date', '>=', '2026-03-28')->count();
echo "Future periods: {$futurePeriods}\n";

$pastPeriods = DB::table('periods')->whereIn('tour_id', $tourIds)->where('start_date', '<', '2026-03-28')->count();
echo "Past periods: {$pastPeriods}\n";

// Offers
$periodIds = DB::table('periods')->whereIn('tour_id', $tourIds)->pluck('id');
$totalOffers = DB::table('offers')->whereIn('period_id', $periodIds)->count();
echo "\nTotal offers: {$totalOffers}\n";

$offersWithPrice = DB::table('offers')->whereIn('period_id', $periodIds)->where('price_adult', '>', 0)->count();
echo "Offers with adult price > 0: {$offersWithPrice}\n";

$offersNoPrice = DB::table('offers')->whereIn('period_id', $periodIds)->where(function ($q) {
    $q->whereNull('price_adult')->orWhere('price_adult', 0);
})->count();
echo "Offers with NO adult price: {$offersNoPrice}\n";

// Tours that have periods but zero offers
echo "\n=== Tours with periods but NO offers ===\n";
$noOfferCount = 0;
foreach ($tourIds as $tid) {
    $pids = DB::table('periods')->where('tour_id', $tid)->pluck('id');
    if ($pids->isEmpty()) continue;
    $offerCount = DB::table('offers')->whereIn('period_id', $pids)->count();
    if ($offerCount == 0) {
        $noOfferCount++;
        if ($noOfferCount <= 5) {
            $t = DB::table('tours')->where('id', $tid)->first(['id','tour_code','title']);
            $pc = $pids->count();
            echo "  ID:{$t->id} {$t->tour_code} | periods:{$pc} | " . mb_substr($t->title, 0, 50) . "\n";
        }
    }
}
echo "Total tours with periods but NO offers: {$noOfferCount}\n";

// Tours with all offers having price=0
echo "\n=== Tours where ALL offers have price=0 ===\n";
$allZeroCount = 0;
foreach ($tourIds as $tid) {
    $pids = DB::table('periods')->where('tour_id', $tid)->pluck('id');
    if ($pids->isEmpty()) continue;
    $totalOff = DB::table('offers')->whereIn('period_id', $pids)->count();
    if ($totalOff == 0) continue;
    $pricedOff = DB::table('offers')->whereIn('period_id', $pids)->where('price_adult', '>', 0)->count();
    if ($pricedOff == 0) {
        $allZeroCount++;
        if ($allZeroCount <= 5) {
            $t = DB::table('tours')->where('id', $tid)->first(['id','tour_code','title']);
            echo "  ID:{$t->id} {$t->tour_code} | offers:{$totalOff} all-zero | " . mb_substr($t->title, 0, 50) . "\n";
        }
    }
}
echo "Total tours with ALL zero-price offers: {$allZeroCount}\n";

// Sample period/offer for screenshot tour (คุนหมิง ลี่เจียง)
echo "\n=== Sample Tour: Look for คุนหมิง ต้าหลี่ ลี่เจียง ===\n";
$sample = DB::table('tours')
    ->where('wholesaler_id', 6)
    ->where('title', 'like', '%คุนหมิง%ต้าหลี่%ลี่เจียง%')
    ->first();
if ($sample) {
    echo "Found: ID:{$sample->id} code:{$sample->tour_code} wcode:{$sample->wholesaler_tour_code}\n";
    echo "Title: " . mb_substr($sample->title, 0, 100) . "\n";
    
    $periods = DB::table('periods')->where('tour_id', $sample->id)->orderBy('start_date')->get();
    echo "Periods: " . $periods->count() . "\n\n";
    
    foreach ($periods->take(11) as $p) {
        $offer = DB::table('offers')->where('period_id', $p->id)->first();
        $price = $offer ? $offer->price_adult : 'NO_OFFER';
        $single = $offer ? $offer->price_single : '-';
        $child = $offer ? $offer->price_child : '-';
        $childNb = $offer ? $offer->price_child_nobed : '-';
        echo "  {$p->start_date} - {$p->end_date} | status:{$p->status} visible:{$p->is_visible} sale:{$p->sale_status} | cap:{$p->capacity} avail:{$p->available} | adult:{$price} single:{$single} child:{$child} childNb:{$childNb}\n";
    }
} else {
    echo "Not found, searching broader...\n";
    $sample = DB::table('tours')->where('wholesaler_id', 6)->where('title', 'like', '%ROMANTIC ROAD%')->first();
    if ($sample) {
        echo "Found: ID:{$sample->id} {$sample->tour_code}\n";
        echo "Title: " . mb_substr($sample->title, 0, 100) . "\n";
        $periods = DB::table('periods')->where('tour_id', $sample->id)->orderBy('start_date')->get();
        echo "Periods: " . $periods->count() . "\n";
        foreach ($periods->take(5) as $p) {
            $offer = DB::table('offers')->where('period_id', $p->id)->first();
            echo "  {$p->start_date} | adult:" . ($offer->price_adult ?? 'NO') . " single:" . ($offer->price_single ?? '-') . "\n";
        }
    }
}

// Departure field mappings
echo "\n=== Departure field mappings for integration 6 ===\n";
$depMappings = DB::table('wholesaler_field_mappings')
    ->where('wholesaler_id', 6)
    ->where('section_name', 'departure')
    ->where('is_active', 1)
    ->get();
foreach ($depMappings as $m) {
    echo "  {$m->their_field} -> {$m->our_field} | path: {$m->their_field_path} | transform: " . ($m->transform_type ?? 'direct') . "\n";
}

// Check period status distribution
echo "\n=== Period status distribution ===\n";
$statuses = DB::table('periods')
    ->whereIn('tour_id', $tourIds)
    ->select('status', DB::raw('count(*) as cnt'))
    ->groupBy('status')
    ->get();
foreach ($statuses as $s) {
    echo "  {$s->status}: {$s->cnt}\n";
}

// sale_status distribution
echo "\n=== Period sale_status distribution ===\n";
$saleStatuses = DB::table('periods')
    ->whereIn('tour_id', $tourIds)
    ->select('sale_status', DB::raw('count(*) as cnt'))
    ->groupBy('sale_status')
    ->get();
foreach ($saleStatuses as $s) {
    echo "  " . ($s->sale_status ?? 'NULL') . ": {$s->cnt}\n";
}
