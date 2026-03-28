<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tourIds = DB::table('tours')->where('wholesaler_id', 6)->pluck('id');

// 1. Tour status distribution  
echo "=== Tour status distribution (integration 6) ===\n";
$statuses = DB::table('tours')
    ->where('wholesaler_id', 6)
    ->select('status', DB::raw('count(*) as cnt'))
    ->groupBy('status')
    ->get();
foreach ($statuses as $s) {
    echo "  {$s->status}: {$s->cnt}\n";
}

// 2. Tours with ALL past periods (no future start_date)
echo "\n=== Tours with ONLY past periods (start_date < today) ===\n";
$allPastTours = 0;
$allPastList = [];
foreach ($tourIds as $tid) {
    $total = DB::table('periods')->where('tour_id', $tid)->count();
    if ($total == 0) continue;
    $future = DB::table('periods')->where('tour_id', $tid)->where('start_date', '>=', '2026-03-28')->count();
    if ($future == 0) {
        $allPastTours++;
        if ($allPastTours <= 10) {
            $t = DB::table('tours')->where('id', $tid)->first(['id','tour_code','status','created_at']);
            $allPastList[] = "  ID:{$t->id} {$t->tour_code} status:{$t->status} created:{$t->created_at} | periods:{$total} future:0";
        }
    }
}
echo "Total: {$allPastTours}\n";
foreach ($allPastList as $line) echo $line . "\n";

// 3. Check tour 2029 specifically
echo "\n=== Tour NT202603352 (ID:2029) detail ===\n";
$t = DB::table('tours')->where('id', 2029)->first();
echo "status: {$t->status}\n";
echo "data_source: {$t->data_source}\n";
echo "sync_status: {$t->sync_status}\n";
echo "created_at: {$t->created_at}\n";
echo "updated_at: {$t->updated_at}\n";
echo "last_synced_at: {$t->last_synced_at}\n";

// 4. Check its periods and offers 
echo "\n=== Tour 2029 periods + offers ===\n";
$periods = DB::table('periods')->where('tour_id', 2029)->orderBy('start_date')->get();
foreach ($periods as $p) {
    $offer = DB::table('offers')->where('period_id', $p->id)->first();
    $priceInfo = $offer 
        ? "adult:{$offer->price_adult} single:{$offer->price_single} child:{$offer->price_child} childNb:{$offer->price_child_nobed}"
        : "NO OFFER";
    echo "  {$p->start_date}-{$p->end_date} | status:{$p->status} vis:{$p->is_visible} sale:{$p->sale_status} | cap:{$p->capacity} avail:{$p->available} | {$priceInfo}\n";
}

// 5. Tours created today (March 28) by sync
echo "\n=== Tours created today (Mar 28) ===\n";
$todayTours = DB::table('tours')
    ->where('wholesaler_id', 6)
    ->where('created_at', '>=', '2026-03-28 00:00:00')
    ->count();
echo "Created today: {$todayTours}\n";

// Of those, how many have only past periods?
$todayWithOnlyPast = 0;
$todayIds = DB::table('tours')
    ->where('wholesaler_id', 6)
    ->where('created_at', '>=', '2026-03-28 00:00:00')
    ->pluck('id');
foreach ($todayIds as $tid) {
    $total = DB::table('periods')->where('tour_id', $tid)->count();
    if ($total == 0) {
        $todayWithOnlyPast++; // no periods at all
        continue;
    }
    $future = DB::table('periods')->where('tour_id', $tid)->where('start_date', '>=', '2026-03-28')->count();
    if ($future == 0) $todayWithOnlyPast++;
}
echo "Created today with only past periods: {$todayWithOnlyPast}\n";

// 6. Check how many tours created today have future priced periods
$todayGood = 0;
foreach ($todayIds as $tid) {
    $futPids = DB::table('periods')->where('tour_id', $tid)->where('start_date', '>=', '2026-03-28')->pluck('id');
    if ($futPids->isEmpty()) continue;
    $hasOffer = DB::table('offers')->whereIn('period_id', $futPids)->where('price_adult', '>', 0)->exists();
    if ($hasOffer) $todayGood++;
}
echo "Created today with future priced periods: {$todayGood}\n";

// 7. Tours with inactive status
$inactiveTours = DB::table('tours')
    ->where('wholesaler_id', 6)
    ->where('status', 'inactive')
    ->count();
echo "\nInactive tours: {$inactiveTours}\n";
