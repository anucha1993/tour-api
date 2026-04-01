<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\AdapterFactory;

$config = WholesalerApiConfig::where('wholesaler_id', 6)->first();
$creds = $config->auth_credentials ?? [];
$endpoints = $creds['endpoints'] ?? [];

$tour = Tour::where('wholesaler_id', 6)->whereNotNull('external_id')->first();
echo "Tour: {$tour->tour_code} (external_id: {$tour->external_id})\n";

$adapter = AdapterFactory::create(6);

// Use periods endpoint as detail
$periodsEndpoint = $endpoints['periods'] ?? '';
$url = str_replace('{external_id}', $tour->external_id, $periodsEndpoint);
echo "Fetching: {$url}\n\n";

$result = $adapter->fetchPeriods($url);
echo "Success: " . ($result->success ? 'YES' : 'NO') . "\n";
echo "Periods count: " . count($result->periods ?? []) . "\n";

// Check raw response directly
$client = new \GuzzleHttp\Client();
$token = $creds['api_key'] ?? $creds['token'] ?? '';
$resp = $client->get($url, [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ],
    'timeout' => 30,
]);
$body = json_decode($resp->getBody()->getContents(), true);

echo "\nRaw response top keys: " . implode(', ', array_keys($body)) . "\n";

// Navigate into the data
$data = $body['data'] ?? $body;
if (is_array($data) && !isset($data[0])) {
    echo "Data top keys: " . implode(', ', array_keys($data)) . "\n";
    
    $periods = $data['periods'] ?? $data['tour_period'] ?? [];
    echo "Periods count: " . count($periods) . "\n";
    
    $tourDaily = $data['tour_daily'] ?? [];
    if (!empty($tourDaily)) {
        echo "\ntour_daily at top level! Count: " . count($tourDaily) . "\n";
        
        if (isset($tourDaily[0])) {
            echo "First daily keys: " . implode(', ', array_keys($tourDaily[0])) . "\n";
            
            $fd = $tourDaily[0];
            $dayListCount = isset($fd['day_list']) ? count($fd['day_list']) : 0;
            echo "day_num: " . ($fd['day_num'] ?? 'N/A') . "\n";
            echo "day_topics: " . ($fd['day_topics'] ?? 'N/A') . "\n";
            echo "day_list count: {$dayListCount}\n";
            
            if (isset($fd['day_list'][0])) {
                echo "First day_list keys: " . implode(', ', array_keys($fd['day_list'][0])) . "\n";
                echo "First day_list: " . json_encode($fd['day_list'][0], JSON_UNESCAPED_UNICODE) . "\n";
            }
            
            // Show all days
            echo "\nAll days:\n";
            foreach ($tourDaily as $i => $day) {
                $dlc = isset($day['day_list']) ? count($day['day_list']) : 0;
                $dn = $day['day_num'] ?? 'N/A';
                $dt = isset($day['day_topics']) ? mb_substr($day['day_topics'], 0, 80) : '';
                echo "  [{$i}] day_num={$dn}, topics={$dt}, day_list_items={$dlc}\n";
            }
        }
    }
    
    // Check if periods have tour_daily nested
    if (!empty($periods) && isset($periods[0])) {
        echo "\nFirst period keys: " . implode(', ', array_keys($periods[0])) . "\n";
        
        if (isset($periods[0]['tour_daily'])) {
            echo "Period has tour_daily! Count: " . count($periods[0]['tour_daily']) . "\n";
            
            // Full flatten analysis
            $totalDaily = 0;
            $totalDayList = 0;
            foreach ($periods as $p) {
                $td = $p['tour_daily'] ?? [];
                $totalDaily += count($td);
                foreach ($td as $d) {
                    $totalDayList += isset($d['day_list']) ? count($d['day_list']) : 0;
                }
            }
            echo "\n=== FLATTEN ANALYSIS ===\n";
            $uniqueDays = count($periods[0]['tour_daily'] ?? []);
            echo "Unique days (first period): {$uniqueDays}\n";
            echo "periods[].tour_daily[] => {$totalDaily} items\n";
            echo "periods[].tour_daily[].day_list[] => {$totalDayList} items\n";
            echo "\nCONCLUSION: Path should be 'periods[0].tour_daily[]' (first period only)"
                . " or just use top-level 'tour_daily' if available.\n";
        }
    }
}
