$c = \App\Models\WholesalerApiConfig::find(25);
$base = $c->api_base_url;
$headers = ($c->auth_credentials['headers']??[]);
$http = \Illuminate\Support\Facades\Http::withHeaders($headers)->timeout(60);
$targets = ["BT-CTS_W05_XJ_XJ","BT-KIX_W03_XJ_XJ"];
$allCodes = [];
$found = [];
$page=1; $totalPages=1;
do{
    $j = $http->get($base, ["page"=>$page,"limit"=>50])->json();
    $meta = $j['data']['meta'] ?? [];
    $totalPages = $meta['totalPages'] ?? 1;
    foreach(($j['data']['data'] ?? []) as $t){
        $code = $t['code'] ?? '';
        $allCodes[] = $code;
        if(in_array($code, $targets)){
            $found[$code] = ["id"=>$t['id']??null,"name"=>mb_substr($t['name']??'',0,30),"periods"=>count($t['period']??[]),"saller"=>$t['saller']??null,"newpro"=>$t['newpro']??null];
        }
    }
    $page++;
}while($page <= $totalPages && $page<=25);
echo "fetched codes total=".count($allCodes)." unique=".count(array_unique($allCodes))." pages=".$totalPages.PHP_EOL;
foreach($targets as $tg){
    echo "  $tg => ".(isset($found[$tg])? ("IN API: ".json_encode($found[$tg],JSON_UNESCAPED_UNICODE)) : "NOT in API")."\n";
}
// show any codes containing CTS or KIX or W05 or W03
echo PHP_EOL."codes matching CTS/KIX:".PHP_EOL;
foreach(array_unique($allCodes) as $cd){ if(preg_match('/CTS|KIX/i',$cd)) echo "  $cd".PHP_EOL; }
