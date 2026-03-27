<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$config = \App\Models\WholesalerApiConfig::find(20);
$creds = $config->auth_credentials;
$secret = $creds['headers']['itravels-secret'] ?? '';

// Check CCZ318 which has 13 items
$code = 'CCZ318';
$url = "https://itravels.center/api/program/{$code}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json',
    "itravels-secret: {$secret}",
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

echo "=== Top level ===" . PHP_EOL;
echo "result: " . ($data['result'] ?? 'N/A') . PHP_EOL;
echo "data type: " . gettype($data['data']) . PHP_EOL;

if (is_array($data['data'])) {
    // Check if it's an array of periods
    $d = $data['data'];
    echo "data count: " . count($d) . PHP_EOL;
    
    if (isset($d[0])) {
        echo PHP_EOL . "=== data[0] ===" . PHP_EOL;
        if (is_array($d[0])) {
            echo "Keys: " . implode(', ', array_keys($d[0])) . PHP_EOL;
            echo json_encode($d[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        } else {
            echo "Value: " . $d[0] . PHP_EOL;
        }
    }
    
    // Check if data has named keys too
    $namedKeys = array_filter(array_keys($d), fn($k) => !is_int($k));
    if ($namedKeys) {
        echo PHP_EOL . "Named keys: " . implode(', ', $namedKeys) . PHP_EOL;
    }
}

// Also check a tour WITH periods (C3U248)
echo PHP_EOL . "====================================" . PHP_EOL;
$code2 = 'C3U248';
$url2 = "https://itravels.center/api/program/{$code2}";

$ch2 = curl_init($url2);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json',
    "itravels-secret: {$secret}",
]);
$response2 = curl_exec($ch2);
curl_close($ch2);

$data2 = json_decode($response2, true);
echo "=== C3U248 (has periods) ===" . PHP_EOL;
echo "data type: " . gettype($data2['data']) . PHP_EOL;

if (is_array($data2['data'])) {
    $d2 = $data2['data'];
    // Check if named keys
    $namedKeys2 = array_filter(array_keys($d2), fn($k) => !is_int($k));
    echo "Named keys: " . implode(', ', $namedKeys2) . PHP_EOL;
    
    if (isset($d2['periods'])) {
        echo "periods count: " . count($d2['periods']) . PHP_EOL;
        echo "period[0]: " . json_encode($d2['periods'][0], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
    
    // Numeric keys?
    $numKeys = array_filter(array_keys($d2), fn($k) => is_int($k));
    if ($numKeys) {
        echo "Also has " . count($numKeys) . " numeric keys" . PHP_EOL;
    }
}
