<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$config = \App\Models\WholesalerApiConfig::find(20);
$creds = $config->auth_credentials;
$secret = $creds['headers']['itravels-secret'] ?? '';

// Get ALL tours without periods  
$noPeriodTours = \App\Models\Tour::where('wholesaler_id', 35)
    ->doesntHave('periods')
    ->select('id', 'tour_code', 'external_id', 'title')
    ->get();

echo "Tours without periods: " . $noPeriodTours->count() . "\n";
echo "---\n";

$apiHasPeriods = 0;
$apiNoPeriods = 0;
$apiErrors = 0;

foreach ($noPeriodTours as $tour) {
    $code = $tour->external_id ?? $tour->tour_code;
    $url = "https://itravels.center/api/program/{$code}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        "itravels-secret: {$secret}",
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $data = json_decode($response, true);
        $inner = $data['data'] ?? [];
        
        // data is either array of periods (numeric keys) or named object
        $periodCount = 0;
        if (is_array($inner)) {
            // Check if first key is numeric (array of periods)
            $keys = array_keys($inner);
            if (!empty($keys) && is_int($keys[0])) {
                $periodCount = count($inner);
            } elseif (isset($inner['periods'])) {
                $periodCount = count($inner['periods']);
            }
        }
        
        if ($periodCount > 0) {
            echo "⚠️  {$code}: API has {$periodCount} periods but DB has 0 - {$tour->title}\n";
            $apiHasPeriods++;
        } else {
            echo "✅ {$code}: API also has 0 periods\n";
            $apiNoPeriods++;
        }
    } else {
        echo "❌ {$code}: HTTP {$httpCode}\n";
        $apiErrors++;
    }
    
    usleep(100000); // 100ms delay
}

echo "\n=== Summary ===\n";
echo "API has periods but DB doesn't: {$apiHasPeriods}\n";
echo "API also has no periods: {$apiNoPeriods}\n";
echo "API errors: {$apiErrors}\n";
