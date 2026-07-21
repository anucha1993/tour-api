$c = \App\Models\WholesalerApiConfig::find(25);
echo "api_base_url=".($c->api_base_url ?? "NULL").PHP_EOL;
echo "api_version=".($c->api_version ?? "NULL")."  api_format=".($c->api_format ?? "NULL").PHP_EOL;
echo "sync_limit=".($c->sync_limit ?? "NULL")." skip_disabled_tours=".var_export($c->skip_disabled_tours_on_sync,true).PHP_EOL;
echo "aggregation_config=".json_encode($c->aggregation_config).PHP_EOL;
// how is endpoint resolved? check adapter
$wid=$c->wholesaler_id;
echo PHP_EOL."wholesaler_id=$wid".PHP_EOL;
$w = \App\Models\Wholesaler::find($wid);
echo "wholesaler code=".($w->code ?? "?")." adapter_class=".($w->adapter_class ?? "n/a")." api_type=".($w->api_type ?? "n/a").PHP_EOL;
echo "wholesaler cols: ".implode(", ",array_keys($w->getAttributes())).PHP_EOL;
