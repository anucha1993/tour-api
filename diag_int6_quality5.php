<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Check a newly created tour today with good data
echo "=== Sample of today's created tours (should be working) ===\n";
$goodTours = DB::table('tours')
    ->where('wholesaler_id', 6)
    ->where('created_at', '>=', '2026-03-28 00:00:00')
    ->where('status', 'draft')
    ->limit(3)
    ->get();
foreach ($goodTours as $t) {
    echo "\n--- Tour ID:{$t->id} {$t->tour_code} status:{$t->status}\n";
    echo "Title: " . mb_substr($t->title, 0, 80) . "\n";
    $periods = DB::table('periods')->where('tour_id', $t->id)->orderBy('start_date')->get();
    echo "Periods: " . $periods->count() . "\n";
    foreach ($periods->take(3) as $p) {
        $offer = DB::table('offers')->where('period_id', $p->id)->first();
        $priceInfo = $offer
            ? "adult:{$offer->price_adult} single:{$offer->price_single}"
            : "NO OFFER";
        echo "  {$p->start_date}-{$p->end_date} | status:{$p->status} vis:{$p->is_visible} sale:{$p->sale_status} | cap:{$p->capacity} avail:{$p->available} | {$priceInfo}\n";
    }
}

// Check the 14 tours with periods but no offers
echo "\n\n=== 14 tours with periods but NO offers (detail) ===\n";
$tourIds = DB::table('tours')->where('wholesaler_id', 6)->pluck('id');
$noOfferTours = [];
foreach ($tourIds as $tid) {
    $pids = DB::table('periods')->where('tour_id', $tid)->pluck('id');
    if ($pids->isEmpty()) continue;
    $offerCount = DB::table('offers')->whereIn('period_id', $pids)->count();
    if ($offerCount == 0) {
        $t = DB::table('tours')->where('id', $tid)->first(['id','tour_code','status','title','created_at']);
        $futCount = DB::table('periods')->where('tour_id', $tid)->where('start_date', '>=', '2026-03-28')->count();
        $noOfferTours[] = [
            'id' => $t->id,
            'code' => $t->tour_code,
            'status' => $t->status,
            'periods' => $pids->count(),
            'future' => $futCount,
            'created' => $t->created_at,
            'title' => mb_substr($t->title, 0, 50),
        ];
    }
}
foreach ($noOfferTours as $nt) {
    echo "  ID:{$nt['id']} {$nt['code']} status:{$nt['status']} | periods:{$nt['periods']} future:{$nt['future']} | created:{$nt['created']} | {$nt['title']}\n";
}

// Count: draft tours created today with ALL periods having offers
echo "\n=== Today's tours: all periods have offers? ===\n";
$todayIds = DB::table('tours')
    ->where('wholesaler_id', 6)
    ->where('created_at', '>=', '2026-03-28 00:00:00')
    ->pluck('id');
$allGood = 0;
$someMissing = 0;
foreach ($todayIds as $tid) {
    $pids = DB::table('periods')->where('tour_id', $tid)->pluck('id');
    if ($pids->isEmpty()) continue;
    $withOffers = DB::table('offers')->whereIn('period_id', $pids)->distinct()->count('period_id');
    if ($withOffers >= $pids->count()) {
        $allGood++;
    } else {
        $someMissing++;
    }
}
echo "All periods have offers: {$allGood}\n";
echo "Some periods missing offers: {$someMissing}\n";

// Check if some specific today tours have periods without offers
echo "\n=== Today's tours with some periods missing offers ===\n";
$count = 0;
foreach ($todayIds as $tid) {
    $pids = DB::table('periods')->where('tour_id', $tid)->pluck('id');
    if ($pids->isEmpty()) continue;
    $withOffers = DB::table('offers')->whereIn('period_id', $pids)->distinct()->pluck('period_id');
    $missingOfferPids = $pids->diff($withOffers);
    if ($missingOfferPids->isNotEmpty()) {
        $count++;
        if ($count <= 3) {
            $t = DB::table('tours')->where('id', $tid)->first(['id','tour_code']);
            echo "  Tour ID:{$t->id} {$t->tour_code} | total:{$pids->count()} with_offer:{$withOffers->count()} missing:{$missingOfferPids->count()}\n";
            foreach ($missingOfferPids->take(3) as $mpid) {
                $p = DB::table('periods')->where('id', $mpid)->first();
                echo "    period {$p->start_date} status:{$p->status} vis:{$p->is_visible}\n";
            }
        }
    }
}
echo "Total today tours with missing offers: {$count}\n";

// Summary
echo "\n=== SUMMARY ===\n";
echo "Total tours (int 6): " . DB::table('tours')->where('wholesaler_id', 6)->count() . "\n";
echo "  draft: " . DB::table('tours')->where('wholesaler_id', 6)->where('status', 'draft')->count() . "\n";
echo "  active: " . DB::table('tours')->where('wholesaler_id', 6)->where('status', 'active')->count() . "\n";
echo "  inactive: " . DB::table('tours')->where('wholesaler_id', 6)->where('status', 'inactive')->count() . "\n";
echo "Total periods: " . DB::table('periods')->whereIn('tour_id', $tourIds)->count() . "\n";
echo "Total offers: " . DB::table('offers')->whereIn('period_id', DB::table('periods')->whereIn('tour_id', $tourIds)->pluck('id'))->count() . "\n";
