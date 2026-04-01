<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\WholesalerApiConfig;

$config = WholesalerApiConfig::where('wholesaler_id', 6)->first();
$creds = $config->auth_credentials ?? [];
$baseUrl = $creds['base_url'] ?? '';
$apiKey = $creds['api_key'] ?? '';
$tourEndpoint = $creds['endpoints']['tours'] ?? '';

// Fetch a single tour to see data structure
$tour = Tour::where('wholesaler_id', 6)->whereNotNull('external_id')->first();
echo "Tour: {$tour->tour_code} (external_id: {$tour->external_id})\n\n";

// Make API call to get raw data
$adapter = App\Services\WholesalerAdapters\AdapterFactory::create(6);

// Try fetching tour detail 
$detailEndpoint = $creds['endpoints']['detail'] ?? null;
if ($detailEndpoint) {
    $url = str_replace(
        ['{external_id}', '{tour_code}', '{wholesaler_tour_code}'],
        [$tour->external_id ?? '', $tour->tour_code ?? '', $tour->wholesaler_tour_code ?? ''],
        $detailEndpoint
    );
    echo "Detail URL: {$url}\n";
    $response = $adapter->fetchTourDetail($url);
    echo "Detail response keys: " . implode(', ', array_keys($response ?? [])) . "\n";
} else {
    echo "No detail endpoint, using search...\n";
}

// Fetch from search endpoint with limit 1
$searchUrl = $tourEndpoint;
echo "Search URL: {$searchUrl}\n";

$client = new \GuzzleHttp\Client();
$headers = [];
if ($apiKey) {
    $headers['Authorization'] = "Bearer {$apiKey}";
    $headers['x-api-key'] = $apiKey;
}

try {
    $resp = $client->get($searchUrl, [
        'headers' => $headers,
        'query' => ['per_page' => 1, 'page' => 1, 'limit' => 1],
        'timeout' => 30,
    ]);
    $body = json_decode($resp->getBody()->getContents(), true);
    
    // Find tours in response
    $tours = $body['data'] ?? $body['tours'] ?? $body ?? [];
    if (isset($tours[0])) {
        $rawTour = $tours[0];
    } else {
        $rawTour = $tours;
    }
    
    echo "Raw tour top-level keys: " . implode(', ', array_keys($rawTour)) . "\n\n";
    
    // Check periods structure
    if (isset($rawTour['periods']) && is_array($rawTour['periods'])) {
        $periodCount = count($rawTour['periods']);
        echo "Periods count: {$periodCount}\n";
        
        if (isset($rawTour['periods'][0])) {
            $firstPeriod = $rawTour['periods'][0];
            echo "First period keys: " . implode(', ', array_keys($firstPeriod)) . "\n";
            
            // Check tour_daily
            if (isset($firstPeriod['tour_daily']) && is_array($firstPeriod['tour_daily'])) {
                $dailyCount = count($firstPeriod['tour_daily']);
                echo "First period tour_daily count: {$dailyCount}\n";
                
                if (isset($firstPeriod['tour_daily'][0])) {
                    $firstDay = $firstPeriod['tour_daily'][0];
                    echo "First tour_daily keys: " . implode(', ', array_keys($firstDay)) . "\n";
                    echo "First tour_daily (without day_list):\n";
                    $dayInfo = $firstDay;
                    if (isset($dayInfo['day_list'])) {
                        $dayListCount = count($dayInfo['day_list']);
                        echo "  day_list count: {$dayListCount}\n";
                        if (isset($dayInfo['day_list'][0])) {
                            echo "  First day_list item keys: " . implode(', ', array_keys($dayInfo['day_list'][0])) . "\n";
                            echo "  First day_list item:\n";
                            echo "  " . json_encode($dayInfo['day_list'][0], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                        }
                        unset($dayInfo['day_list']);
                    }
                    echo json_encode($dayInfo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                }
                
                // Show all day_num values
                echo "\nAll day_num values in first period:\n";
                foreach ($firstPeriod['tour_daily'] as $i => $day) {
                    $dayListCount = isset($day['day_list']) ? count($day['day_list']) : 0;
                    $dayNum = isset($day['day_num']) ? $day['day_num'] : 'N/A';
                    $dayTopics = isset($day['day_topics']) ? mb_substr($day['day_topics'], 0, 50) : '';
                    echo "  Day {$i}: day_num={$dayNum}, day_topics={$dayTopics}, day_list_count={$dayListCount}\n";
                }
            }
            
            // Check second period for comparison
            if (isset($rawTour['periods'][1]['tour_daily'])) {
                $secondDailyCount = count($rawTour['periods'][1]['tour_daily']);
                echo "\nSecond period tour_daily count: {$secondDailyCount}\n";
                echo "Same itinerary across periods? Days match: " . 
                    (($dailyCount === $secondDailyCount) ? 'YES (same count)' : 'NO (different count)') . "\n";
            }
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
