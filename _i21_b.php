$wid = 39;
$code = 'Ckg3u0626';

echo "=== Existing tour with code {$code}? ===\n";
$t = App\Models\Tour::where('wholesaler_id',$wid)
    ->where(function($q) use ($code){
        $q->where('wholesaler_tour_code',$code)
          ->orWhere('tour_code',$code)
          ->orWhere('external_id',$code);
    })
    ->first();
if ($t) {
    echo "FOUND tour_id={$t->id} title=" . mb_substr($t->title,0,80) . "\n";
    echo " status={$t->status} external_id={$t->external_id} wholesaler_tour_code={$t->wholesaler_tour_code}\n";
    echo " last_synced_at={$t->last_synced_at} sync_status={$t->sync_status} data_source={$t->data_source}\n";
    $pCount = App\Models\Period::where('tour_id',$t->id)->count();
    echo " periods={$pCount}\n";
} else {
    echo "NOT FOUND in DB — code '{$code}' has never been synced\n";
}

echo "\n=== Any similar codes? (case-insensitive LIKE) ===\n";
$sim = App\Models\Tour::where('wholesaler_id',$wid)
    ->where(function($q) use ($code){
        $q->where('wholesaler_tour_code','LIKE',"%{$code}%")
          ->orWhere('external_id','LIKE',"%{$code}%")
          ->orWhere('wholesaler_tour_code','LIKE','%CKG3U%')
          ->orWhere('external_id','LIKE','%CKG3U%');
    })
    ->get(['id','external_id','wholesaler_tour_code','tour_code','title','status']);
foreach($sim as $r) {
    echo " id={$r->id} ext={$r->external_id} wcode={$r->wholesaler_tour_code} status={$r->status}  " . mb_substr($r->title,0,80) . "\n";
}

echo "\n=== Recent SyncLogs (wholesaler_id={$wid}) ===\n";
$logs = App\Models\SyncLog::where('wholesaler_id',$wid)
    ->orderByDesc('id')->limit(5)
    ->get(['id','sync_type','status','started_at','completed_at','tours_received','tours_created','tours_updated','tours_skipped','tours_failed','periods_received','error_count']);
foreach ($logs as $l) {
    echo sprintf("id=%d %s %s started=%s t.r/c/u/s/f=%d/%d/%d/%d/%d p.r=%d err=%d\n",
        $l->id,$l->sync_type,$l->status,$l->started_at,
        $l->tours_received,$l->tours_created,$l->tours_updated,$l->tours_skipped,$l->tours_failed,
        $l->periods_received,$l->error_count);
}

echo "\n=== Recent SyncErrorLog for wholesaler {$wid} (last 15) ===\n";
$errs = App\Models\SyncErrorLog::where('wholesaler_id',$wid)
    ->orderByDesc('id')->limit(15)
    ->get(['id','sync_log_id','external_tour_code','tour_id','section_name','field_name','error_type','error_message','created_at']);
foreach ($errs as $e) {
    echo sprintf("[%d] %s tour=%s sect=%s field=%s type=%s\n  %s\n",
        $e->id,$e->created_at,$e->external_tour_code,$e->section_name,$e->field_name,$e->error_type,
        mb_substr($e->error_message ?? '',0,300));
}

echo "\n=== Tour stats (wid={$wid}) ===\n";
$total = App\Models\Tour::where('wholesaler_id',$wid)->count();
$byS = DB::table('tours')->where('wholesaler_id',$wid)->groupBy('status')->select('status',DB::raw('count(*) as n'))->get();
echo "total={$total}\n";
foreach($byS as $b){ echo "  {$b->status}={$b->n}\n"; }
