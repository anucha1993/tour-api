<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$config = \App\Models\WholesalerApiConfig::find(20);
$creds = $config->auth_credentials;
$secret = $creds['headers']['itravels-secret'] ?? '';

// Get tours without periods
$noPeriodTours = \App\Models\Tour::where('wholesaler_id', 35)
    ->doesntHave('periods')
    ->select('id', 'tour_code', 'external_id', 'title')
    ->limit(5)
    ->get();

echo "Checking " . $noPeriodTours->count() . " tours without periods from API...\n\n";

foreach ($noPeriodTours as $tour) {
    $code = $tour->external_id ?? $tour->tour_code;
    $url = "https://itravels.center/api/program/{$code}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        "itravels-secret: {$secret}",
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "=== {$code} (HTTP {$httpCode}) ===\n";
    
    if ($httpCode == 200) {
        $data = json_decode($response, true);
        $inner = $data['data'] ?? $data;
        
        if (isset($inner['periods'])) {
            echo "  periods: " . count($inner['periods']) . "\n";
            if (count($inner['periods']) > 0) {
                echo "  period[0]: " . json_encode($inner['periods'][0], JSON_UNESCAPED_UNICODE) . "\n";
            }
        } else {
            echo "  NO periods key\n";
            // Show all keys
            echo "  Keys: " . implode(', ', array_keys($inner)) . "\n";
        }
    } else {
        echo "  Error: " . substr($response, 0, 200) . "\n";
    }
    echo "\n";
}
