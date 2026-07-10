echo "Running full sync for wholesaler_id=39 (TTN Europe, integration 21)...\n";
$before = [
    'tours' => App\Models\Tour::where('wholesaler_id',39)->count(),
    'periods' => DB::table('periods as p')->join('tours as t','t.id','=','p.tour_id')->where('t.wholesaler_id',39)->count(),
    'offers' => DB::table('offers as o')->join('periods as p','p.id','=','o.period_id')->join('tours as t','t.id','=','p.tour_id')->where('t.wholesaler_id',39)->count(),
];
echo "BEFORE: " . json_encode($before) . "\n";

$t0 = microtime(true);
try {
    $job = new App\Jobs\SyncToursJob(39, null, 'full', null);
    $job->setProcessPeriodsInline(true);
    $job->handle();
    $sec = round(microtime(true)-$t0, 1);
    echo "Sync completed OK. ({$sec}s)\n";
} catch (\Throwable $e) {
    echo "Sync FAILED: " . $e->getMessage() . "\n";
}

echo "\n=== Latest sync log ===\n";
$l = App\Models\SyncLog::where('wholesaler_id',39)->orderByDesc('id')->first();
echo sprintf("id=%d %s %s\n  t.recv/cre/upd/skip/fail=%d/%d/%d/%d/%d\n  p.recv/cre/upd=%d/%d/%d\n  err=%d\n",
    $l->id,$l->sync_type,$l->status,
    $l->tours_received,$l->tours_created,$l->tours_updated,$l->tours_skipped,$l->tours_failed,
    $l->periods_received,$l->periods_created,$l->periods_updated,
    $l->error_count);
if (!empty($l->error_summary)) echo "  err=".json_encode($l->error_summary,JSON_UNESCAPED_UNICODE)."\n";

$after = [
    'tours' => App\Models\Tour::where('wholesaler_id',39)->count(),
    'periods' => DB::table('periods as p')->join('tours as t','t.id','=','p.tour_id')->where('t.wholesaler_id',39)->count(),
    'offers' => DB::table('offers as o')->join('periods as p','p.id','=','o.period_id')->join('tours as t','t.id','=','p.tour_id')->where('t.wholesaler_id',39)->count(),
];
echo "\nAFTER : " . json_encode($after) . "\n";
echo "DELTA : tours=" . ($after['tours']-$before['tours']) . " periods=" . ($after['periods']-$before['periods']) . " offers=" . ($after['offers']-$before['offers']) . "\n";

$noOff = DB::table('periods as p')->join('tours as t','t.id','=','p.tour_id')
    ->leftJoin('offers as o','o.period_id','=','p.id')
    ->where('t.wholesaler_id',39)->whereNull('o.id')->count();
echo "periods without offer: {$noOff}\n";

// Check CKG3U0626 status
$t = App\Models\Tour::where('wholesaler_id',39)->where('wholesaler_tour_code','CKG3U0626')->first();
if ($t) {
    $futOpen = App\Models\Period::where('tour_id',$t->id)
        ->where('start_date','>=',now()->toDateString())
        ->where('status','open')->count();
    echo "\nCKG3U0626: id={$t->id} status={$t->status} periods=" . App\Models\Period::where('tour_id',$t->id)->count()
        . " future_open={$futOpen} display_price={$t->display_price}\n";
}
