$c = \App\Models\WholesalerApiConfig::find(25);
$base = $c->api_base_url;
$headers = ($c->auth_credentials['headers']??[]);
$http = \Illuminate\Support\Facades\Http::withHeaders($headers)->timeout(60);
$targets = ["BT-CTS_W05_XJ_XJ","BT-KIX_W03_XJ_XJ"];
$page=1;$tp=1;$hit=[];
do{
  $j=$http->get($base,["page"=>$page,"limit"=>50])->json();
  $tp=$j['data']['meta']['totalPages']??1;
  foreach(($j['data']['data']??[]) as $t){ if(in_array($t['code']??'',$targets)) $hit[$t['code']]=$t; }
  $page++;
}while($page<=$tp);

foreach($targets as $code){
  $t=$hit[$code]??null;
  echo PHP_EOL."=== $code ===".PHP_EOL;
  if(!$t){ echo "not fetched".PHP_EOL; continue; }
  echo "id=".$t['id']." saller=".($t['saller']??'?')." newpro=".($t['newpro']??'?')." price=".($t['price']??'?')." day=".($t['day']??'?').PHP_EOL;
  echo "periods=".count($t['period']??[]).PHP_EOL;
  foreach(($t['period']??[]) as $p){
    echo "  pid=".($p['pid']??'?')." go=".($p['dateGo']??'?')." back=".($p['dateBack']??'?')." confirm=".($p['confirm']??'?')." avbl=".($p['avbl']??'?')." adult=".($p['adultPrice']??'?').PHP_EOL;
  }
}
echo PHP_EOL."today=".now()->toDateString().PHP_EOL;
