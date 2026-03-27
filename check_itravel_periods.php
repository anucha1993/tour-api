<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$config = \App\Models\WholesalerApiConfig::where('wholesaler_id', 35)->first();
$tourCode = 'CVZ240';
$secret = $config->auth_config;
$secretValue = is_array($secret) ? ($secret['value'] ?? '') : '';

$ch = curl_init("https://itravels.center/api/program/{$tourCode}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json',
    "itravels-secret: {$secretValue}",
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "GET /api/program/{$tourCode} → HTTP {$httpCode}\n";
$data = json_decode($response, true);

if (isset($data['data'])) {
    $d = $data['data'];
    echo "Data keys: " . implode(', ', array_keys($d)) . "\n";
    
    if (isset($d['periods'])) {
        echo "\n=== Periods ===" . PHP_EOL;
        echo "Count: " . count($d['periods']) . PHP_EOL;
        if (count($d['periods']) > 0) {
            echo "period[0] keys: " . implode(', ', array_keys($d['periods'][0])) . "\n";
            print_r($d['periods'][0]);
        }
    } else {
        echo "\nNO periods key in data\n";
        // Look for any array that looks like periods
        foreach ($d as $k => $v) {
            if (is_array($v) && count($v) > 0 && isset($v[0]) && is_array($v[0])) {
                echo "Possible period array: {$k} (count=" . count($v) . ") keys: " . implode(', ', array_keys($v[0])) . "\n";
            }
        }
    }
} else {
    echo "Full response: " . substr($response, 0, 500) . "\n";
}
