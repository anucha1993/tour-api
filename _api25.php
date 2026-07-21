$c = \App\Models\WholesalerApiConfig::find(25);
$base = $c->api_base_url;
$headers = ($c->auth_credentials['headers']??[]);
$http = \Illuminate\Support\Facades\Http::withHeaders($headers)->timeout(60);

$r = $http->get($base);
echo "GET $base -> HTTP ".$r->status().PHP_EOL;
$j = $r->json();
echo "top keys: ".(is_array($j)?implode(", ",array_keys($j)):gettype($j)).PHP_EOL;
// find data array + meta
foreach(["data","tours","items","result","results"] as $k){
    if(isset($j[$k]) && is_array($j[$k])){ echo "list key='$k' count=".count($j[$k]).PHP_EOL; }
}
echo "meta=".json_encode($j['meta']??$j['pagination']??$j['links']??null, JSON_UNESCAPED_UNICODE).PHP_EOL;
$data = $j['data'] ?? $j['tours'] ?? $j['items'] ?? [];
if(isset($data[0])){
    echo "item0 keys: ".implode(", ", array_keys($data[0])).PHP_EOL;
    // print code-like fields
    $it=$data[0];
    foreach($it as $kk=>$vv){ if(is_scalar($vv) && preg_match('/code|id|name|program/i',$kk)){ echo "   $kk = ".mb_substr((string)$vv,0,60).PHP_EOL; } }
}
