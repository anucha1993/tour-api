<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$c = App\Models\WholesalerApiConfig::find(46);
$creds = $c->auth_credentials;
$base = $c->api_base_url;
$headers = $creds['headers'] ?? [];

echo "base: $base\n";
echo "header keys: ".implode(',', array_keys($headers))."\n\n";

// Discover endpoint shape — try list first
$urls = [
    $base.'?per_page=1',
    $base,
];
foreach ($urls as $url) {
    echo "=== GET $url ===\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(fn($k,$v)=>$k.': '.$v, array_keys($headers), array_values($headers)));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "HTTP $code, bytes=".strlen((string)$body)."\n";
    if (!$body) { echo "curl err: ".curl_error($ch)."\n"; continue; }
    $j = json_decode($body, true);
    if (!is_array($j)) {
        echo "not JSON. first 400 chars:\n".substr($body,0,400)."\n\n";
        continue;
    }
    // find first item
    $sample = null;
    $paths = ['data.0', '0', 'tours.0', 'result.0', 'items.0', 'data.tours.0'];
    foreach ($paths as $p) {
        $ref = $j; $ok = true;
        foreach (explode('.', $p) as $seg) {
            if (is_array($ref) && array_key_exists($seg, $ref)) $ref = $ref[$seg];
            else { $ok = false; break; }
        }
        if ($ok) { $sample = $ref; echo "sample from path: $p\n"; break; }
    }
    if (!$sample) {
        echo "top keys: ".implode(',', array_keys($j))."\n";
        echo "raw first 800:\n".substr($body,0,800)."\n";
        break;
    }
    echo "sample keys: ".implode(',', array_keys($sample))."\n\n";
    echo "period value: ".json_encode($sample['period'] ?? '(MISSING)', JSON_UNESCAPED_UNICODE)."\n\n";
    // Also look for anything date-ish
    $dateLike = [];
    foreach ($sample as $k => $v) {
        if (preg_match('/date|period|start|end|depart|return|time/i', $k)) {
            $dateLike[$k] = is_scalar($v) ? $v : (is_array($v) ? '[array]' : gettype($v));
        }
    }
    echo "date-like fields:\n".json_encode($dateLike, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n\n";

    echo "--- full sample (first 2500 chars) ---\n";
    echo substr(json_encode($sample, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), 0, 2500)."\n";
    break;
}
