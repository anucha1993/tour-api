$c = \App\Models\WholesalerApiConfig::find(25);
$base = $c->api_base_url;
$headers = ($c->auth_credentials['headers']??[]);
$http = \Illuminate\Support\Facades\Http::withHeaders($headers)->timeout(60);
$j = $http->get($base)->json();
$outer = $j['data'];
echo "outer keys: ".implode(", ", array_keys($outer)).PHP_EOL;
foreach($outer as $k=>$v){
    if($k==='data'){ echo "  data => list count=".count($v).PHP_EOL; }
    else { echo "  $k => ".json_encode($v, JSON_UNESCAPED_UNICODE).PHP_EOL; }
}
