// When was the last time a tour for wid=39 was actually updated by sync?
echo "=== Tour last_synced_at distribution (wid=39) ===\n";
$rows = DB::table('tours')->where('wholesaler_id',39)
    ->selectRaw('DATE(last_synced_at) as d, count(*) as n')
    ->groupBy('d')->orderByDesc('d')->limit(10)->get();
foreach($rows as $r){ echo "  {$r->d}: {$r->n}\n"; }

echo "\n=== Recent tour creations (wid=39) ===\n";
$rows = DB::table('tours')->where('wholesaler_id',39)
    ->selectRaw('DATE(created_at) as d, count(*) as n')
    ->groupBy('d')->orderByDesc('d')->limit(10)->get();
foreach($rows as $r){ echo "  {$r->d}: {$r->n}\n"; }

echo "\n=== Recent tour updated_at (wid=39) ===\n";
$rows = DB::table('tours')->where('wholesaler_id',39)
    ->selectRaw('DATE(updated_at) as d, count(*) as n')
    ->groupBy('d')->orderByDesc('d')->limit(15)->get();
foreach($rows as $r){ echo "  {$r->d}: {$r->n}\n"; }

echo "\n=== Most recent SyncLogs with tours_received > 0 (wid=39) ===\n";
$logs = App\Models\SyncLog::where('wholesaler_id',39)
    ->where('tours_received','>',0)
    ->orderByDesc('id')->limit(5)
    ->get(['id','sync_type','status','started_at','completed_at','tours_received','tours_created','tours_updated','tours_skipped','tours_failed','periods_received','error_count','error_summary']);
foreach ($logs as $l) {
    echo sprintf("id=%d %s %s started=%s t.r/c/u/s/f=%d/%d/%d/%d/%d p.r=%d err=%d\n",
        $l->id,$l->sync_type,$l->status,$l->started_at,
        $l->tours_received,$l->tours_created,$l->tours_updated,$l->tours_skipped,$l->tours_failed,
        $l->periods_received,$l->error_count);
    if (!empty($l->error_summary)) echo "  err=".json_encode($l->error_summary,JSON_UNESCAPED_UNICODE)."\n";
}

echo "\n=== field mappings for wid=39 by section ===\n";
foreach (['tour','departure','content','media','seo','itinerary','city'] as $sec) {
    echo "\n-- {$sec} --\n";
    $maps = App\Models\WholesalerFieldMapping::where('wholesaler_id',39)
        ->where('section_name',$sec)->orderBy('our_field')->get();
    foreach($maps as $m){
        echo sprintf(" [%d] %-25s <- %-45s type=%-10s active=%d default=%s\n",
            $m->id,$m->our_field,$m->their_field_path ?? $m->their_field ?? '',$m->transform_type,$m->is_active,$m->default_value);
    }
}
