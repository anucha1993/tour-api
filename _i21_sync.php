echo "Running full sync for wholesaler_id=39 (TTN Europe)...\n";
try {
    $job = new App\Jobs\SyncToursJob(39, null, 'full', null);
    $job->setProcessPeriodsInline(true);
    $job->handle();
    echo "Sync completed OK.\n";
} catch (\Throwable $e) {
    echo "Sync FAILED: " . $e->getMessage() . "\n";
    echo mb_substr($e->getTraceAsString(), 0, 2000) . "\n";
}

echo "\n=== Latest sync log ===\n";
$l = App\Models\SyncLog::where('wholesaler_id',39)->orderByDesc('id')->first();
echo sprintf("id=%d %s %s t.r/c/u/s/f=%d/%d/%d/%d/%d p.r/c/u=%d/%d/%d err=%d\n",
    $l->id,$l->sync_type,$l->status,
    $l->tours_received,$l->tours_created,$l->tours_updated,$l->tours_skipped,$l->tours_failed,
    $l->periods_received,$l->periods_created,$l->periods_updated,$l->error_count);
if (!empty($l->error_summary)) echo "  err=".json_encode($l->error_summary,JSON_UNESCAPED_UNICODE)."\n";

// Check if CKG3U0626 is now in DB
echo "\n=== CKG3U0626 in DB? ===\n";
$t = App\Models\Tour::where('wholesaler_id',39)
    ->where('wholesaler_tour_code','CKG3U0626')->first();
if ($t) {
    echo "FOUND tour_id={$t->id} title=" . mb_substr($t->title,0,80) . "\n";
    echo "  status={$t->status} external_id={$t->external_id}\n";
    echo "  price_adult={$t->price_adult} min={$t->min_price} display={$t->display_price}\n";
    $pCount = App\Models\Period::where('tour_id',$t->id)->count();
    $oCount = DB::table('offers as o')->join('periods as p','p.id','=','o.period_id')
        ->where('p.tour_id',$t->id)->count();
    echo "  periods={$pCount} offers={$oCount}\n";
} else {
    echo "NOT FOUND (still).\n";
}

// Overall stats
echo "\n=== Overall (wid=39) ===\n";
$stats = [
    'tours' => App\Models\Tour::where('wholesaler_id',39)->count(),
    'periods' => DB::table('periods as p')->join('tours as t','t.id','=','p.tour_id')->where('t.wholesaler_id',39)->count(),
    'offers' => DB::table('offers as o')->join('periods as p','p.id','=','o.period_id')->join('tours as t','t.id','=','p.tour_id')->where('t.wholesaler_id',39)->count(),
];
echo json_encode($stats) . "\n";
$noOff = DB::table('periods as p')->join('tours as t','t.id','=','p.tour_id')
    ->leftJoin('offers as o','o.period_id','=','p.id')
    ->where('t.wholesaler_id',39)->whereNull('o.id')->count();
echo "periods without offer: {$noOff}\n";
