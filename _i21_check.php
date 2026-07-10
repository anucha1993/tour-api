$t = App\Models\Tour::where('wholesaler_id',39)->where('wholesaler_tour_code','CKG3U0626')->first();
if (!$t) { echo "NF\n"; return; }
echo "tour_id={$t->id} status={$t->status} ext={$t->external_id}\n";
echo "  title=" . $t->title . "\n";
echo "  min_price={$t->min_price} max_price={$t->max_price} display_price={$t->display_price}\n";
echo "  next_departure_date={$t->next_departure_date} total_departures={$t->total_departures}\n";

$today = now()->toDateString();
echo "\ntoday={$today}\n";

$periods = App\Models\Period::where('tour_id',$t->id)->orderBy('start_date')->get();
$statusCount = [];
$futureOpen = 0;
foreach($periods as $p){
    $statusCount[$p->status] = ($statusCount[$p->status]??0)+1;
    if ($p->status==='open' && $p->start_date >= $today) $futureOpen++;
}
echo "Total periods: " . count($periods) . "\n";
echo "By status: " . json_encode($statusCount) . "\n";
echo "future+open periods: {$futureOpen}\n";

echo "\n=== Sample recent/future periods ===\n";
$fut = App\Models\Period::where('tour_id',$t->id)
    ->where('start_date','>=',$today)
    ->orderBy('start_date')->limit(10)
    ->get(['id','start_date','end_date','status','capacity','booked','available']);
foreach($fut as $p){
    $o = App\Models\Offer::where('period_id',$p->id)->first();
    echo sprintf(" p=%d %s->%s status=%s cap=%d/booked=%d/avail=%d price=%s\n",
        $p->id,$p->start_date,$p->end_date,$p->status,$p->capacity,$p->booked,$p->available,
        $o ? $o->price_adult : 'none');
}
