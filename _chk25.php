$c = \App\Models\WholesalerApiConfig::find(25);
if(!$c){ echo "config 25 NOT found".PHP_EOL; return; }
echo "id=".$c->id." wholesaler_id=".$c->wholesaler_id." name=".($c->wholesaler->name ?? "?").PHP_EOL;
echo "integration_type=".$c->integration_type." sync_mode=".$c->sync_mode." sync_method=".$c->sync_method." sync_enabled=".var_export($c->sync_enabled,true).PHP_EOL;
echo "skip_past=".var_export($c->skip_past_periods_on_sync,true)." past_period_handling=".$c->past_period_handling.PHP_EOL;
$cred = $c->auth_credentials ?? [];
echo "endpoints=".json_encode($cred['endpoints']??null, JSON_UNESCAPED_UNICODE).PHP_EOL;
echo "pagination=".json_encode($cred['pagination']??null, JSON_UNESCAPED_UNICODE).PHP_EOL;
echo "auth_type=".($cred['auth_type']??$c->auth_type ?? "?")." header keys=".json_encode(array_keys($cred['headers']??[])).PHP_EOL;
echo "aggregation_config=".json_encode($c->aggregation_config, JSON_UNESCAPED_UNICODE).PHP_EOL;

echo PHP_EOL."=== DB tours for wholesaler ".$c->wholesaler_id." ===".PHP_EOL;
$wid=$c->wholesaler_id;
$tot = \App\Models\Tour::where("wholesaler_id",$wid)->where("data_source","api")->count();
echo "api tours total=$tot".PHP_EOL;
foreach(["BT-CTS_W05_XJ_XJ","BT-KIX_W03_XJ_XJ"] as $code){
    $t = \App\Models\Tour::where("wholesaler_id",$wid)->where(function($q)use($code){$q->where("wholesaler_tour_code",$code)->orWhere("tour_code",$code);})->first();
    echo "  code $code => ".($t? ("FOUND db_id=".$t->id." ext=".$t->external_id." status=".$t->status) : "NOT in DB").PHP_EOL;
}
