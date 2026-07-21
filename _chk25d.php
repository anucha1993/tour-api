$wid=57;
echo "=== tour section mappings (wholesaler 57) that reference saller/status/active ===".PHP_EOL;
foreach(\App\Models\WholesalerFieldMapping::where("wholesaler_id",$wid)->get() as $m){
    if(preg_match('/saller|status|active|enable|sell|visible/i', $m->our_field.' '.$m->their_field.' '.json_encode($m->transform_config))){
        echo "  [".$m->section_name."] ".$m->our_field." <- ".$m->their_field." (".$m->transform_type.") cfg=".json_encode($m->transform_config,JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
}
echo PHP_EOL."=== last 5 sync logs wholesaler 57 ===".PHP_EOL;
foreach(\App\Models\SyncLog::where("wholesaler_id",$wid)->latest("id")->take(5)->get() as $l){
    echo "id=".$l->id." ".$l->status." ".$l->started_at." type=".($l->sync_type??'?')." | Trecv=".$l->tours_received." Tcre=".$l->tours_created." Tupd=".$l->tours_updated." Tskip=".$l->tours_skipped." Tfail=".$l->tours_failed.PHP_EOL;
}
echo PHP_EOL."aggregation_config=".json_encode($c=\App\Models\WholesalerApiConfig::find(25)->aggregation_config).PHP_EOL;
echo "always_sync_fields=".json_encode(\App\Models\WholesalerApiConfig::find(25)->always_sync_fields).PHP_EOL;
echo "never_sync_fields=".json_encode(\App\Models\WholesalerApiConfig::find(25)->never_sync_fields).PHP_EOL;
