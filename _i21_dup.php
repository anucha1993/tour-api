// Any existing/soft-deleted/other-wholesaler tour with code CKG3U0626?
$code = 'CKG3U0626';

echo "=== Any tour with wholesaler_tour_code={$code} across ALL wholesalers (incl soft-deleted) ===\n";
$rows = DB::table('tours')
    ->where('wholesaler_tour_code', $code)
    ->orWhere('tour_code', $code)
    ->orWhere('external_id', $code)
    ->orWhere('wholesaler_tour_code', 'like', 'CKG3U0626%')
    ->orWhere('external_id', 'like', '%CKG3U0626%')
    ->get(['id','wholesaler_id','external_id','wholesaler_tour_code','tour_code','title','status','data_source','created_at','updated_at','last_synced_at']);
foreach ($rows as $r) {
    echo sprintf(" id=%d wid=%d ext=%s wcode=%s tcode=%s status=%s src=%s\n   created=%s updated=%s synced=%s\n   %s\n",
        $r->id,$r->wholesaler_id,$r->external_id,$r->wholesaler_tour_code,$r->tour_code,$r->status,$r->data_source,
        $r->created_at,$r->updated_at,$r->last_synced_at ?? 'null',
        mb_substr($r->title ?? '',0,100));
}
echo "count: " . count($rows) . "\n";

echo "\n=== Full CKG3U series in DB for wid=39 (order by wholesaler_tour_code) ===\n";
$rows = DB::table('tours')->where('wholesaler_id',39)
    ->where('wholesaler_tour_code','like','CKG3U%')
    ->orderBy('wholesaler_tour_code')
    ->get(['id','external_id','wholesaler_tour_code','tour_code','title','status','data_source','created_at','last_synced_at']);
foreach ($rows as $r) {
    echo sprintf(" id=%d ext=%s wcode=%s tcode=%s status=%s src=%s created=%s synced=%s\n",
        $r->id,$r->external_id,$r->wholesaler_tour_code,$r->tour_code,$r->status,$r->data_source,
        substr($r->created_at,0,16),$r->last_synced_at ? substr($r->last_synced_at,0,16) : 'null');
}

// Show whether unique index would prevent duplicate insert
echo "\n=== unique indexes on tours table ===\n";
$idx = DB::select("SHOW INDEX FROM tours WHERE Non_unique=0");
$byName = [];
foreach($idx as $i){ $byName[$i->Key_name][] = $i->Column_name; }
foreach($byName as $k=>$cols){ echo "  {$k} = (" . implode(',',$cols) . ")\n"; }
