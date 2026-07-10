$ch = curl_init('https://www.ttnplus.co.th/api/program');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30, CURLOPT_SSL_VERIFYPEER=>false]);
$body = curl_exec($ch);
curl_close($ch);

$json = json_decode($body, true);
if (!is_array($json)) { echo "not json\n"; return; }

echo "Total top-level keys: " . count($json) . "\n";
$keys = array_keys($json);
echo "First 10 keys: " . implode(',', array_slice($keys,0,10)) . "\n";
echo "Last 10 keys: " . implode(',', array_slice($keys,-10)) . "\n";
echo "All keys numeric-string? " . (array_reduce($keys,fn($c,$k)=>$c && ctype_digit((string)$k), true) ? 'yes' : 'no') . "\n";

// Look for CKG3U0626 or CKG3U0326
echo "\n=== Searching for CKG3U codes in response ===\n";
foreach($json as $k=>$v) {
    if (!is_array($v)) continue;
    $code = $v['P_CODE'] ?? '';
    if (stripos($code,'CKG3U') !== false) {
        echo " key=$k P_ID={$v['P_ID']} P_CODE=$code P_NAME=" . mb_substr($v['P_NAME']??'',0,80) . "\n";
        echo "   P_DAY={$v['P_DAY']} P_NIGHT={$v['P_NIGHT']} P_PRICE=" . ($v['P_PRICE']??'?') . "\n";
        echo "   available_keys=" . implode(',',array_keys($v)) . "\n";
    }
}
