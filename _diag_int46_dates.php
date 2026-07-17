<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$c = App\Models\WholesalerApiConfig::find(46);
$headers = $c->auth_credentials['headers'] ?? [];
$base = 'https://api-formosa.ht1freshdigital.com/wp-json/bs-api/v1';

// Use tour id we found earlier: 22246
$tourId = 22246;
$url = "$base/tour-dates?tour_id=$tourId";
echo "GET $url\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(fn($k,$v)=>$k.': '.$v, array_keys($headers), array_values($headers)));
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "HTTP $code, bytes=".strlen((string)$body)."\n\n";

$j = json_decode($body, true);
if (!is_array($j)) {
    echo "not json. first 800:\n".substr($body,0,800)."\n";
    exit;
}

echo "top-level type: ".(array_is_list($j)?'list('.count($j).')':'assoc(keys='.implode(',',array_keys($j)).')')."\n\n";

// Find first period item
$sample = $j['data'][0] ?? $j[0] ?? null;
if (!$sample) {
    echo "raw first 2000:\n".substr(json_encode($j, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),0,2000)."\n";
    exit;
}

echo "period keys: ".implode(',', array_keys($sample))."\n\n";
echo "--- first period (full) ---\n";
echo json_encode($sample, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n\n";

if (count($j['data'] ?? $j) > 1) {
    echo "--- 2nd period ---\n";
    echo json_encode(($j['data'] ?? $j)[1], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
}
echo "\ntotal periods for this tour: ".count($j['data'] ?? $j)."\n";
