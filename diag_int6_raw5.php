<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\AdapterFactory;
use App\Jobs\SyncToursJob;

$config = WholesalerApiConfig::where('wholesaler_id', 6)->first();
$creds = $config->auth_credentials ?? [];
$endpoints = $creds['endpoints'] ?? [];

$tour = Tour::where('wholesaler_id', 6)->whereNotNull('external_id')->first();
echo "Tour: {$tour->tour_code} (external_id: {$tour->external_id})\n";

$adapter = AdapterFactory::create(6);

// Fetch the tour detail using periods endpoint
$periodsEndpoint = $endpoints['periods'] ?? '';
$url = str_replace('{external_id}', $tour->external_id, $periodsEndpoint);
echo "Fetching: {$url}\n\n";

// Use the adapter's request method via fetchTourDetail
$detailResult = $adapter->fetchTourDetail($url);

if (!$detailResult || !is_array($detailResult)) {
    echo "No detail data returned\n";
    exit;
}

echo "Detail top keys: " . implode(', ', array_keys($detailResult)) . "\n";

// Check if periods is nested inside 'data'
$data = $detailResult['data'] ?? $detailResult;
if (is_array($data) && !isset($data[0]) && isset($data['periods'])) {
    $detailResult = $data;
    echo "Unwrapped 'data' - keys: " . implode(', ', array_keys($detailResult)) . "\n";
}

// Check for tour_daily at various levels
echo "\n=== Checking tour_daily location ===\n";

// Top level?
if (isset($detailResult['tour_daily'])) {
    echo "tour_daily at TOP level: " . count($detailResult['tour_daily']) . " items\n";
}

// Inside periods?
$periods = $detailResult['periods'] ?? [];
echo "Periods count: " . count($periods) . "\n";

if (!empty($periods) && isset($periods[0])) {
    echo "First period keys: " . implode(', ', array_keys($periods[0])) . "\n";
    
    if (isset($periods[0]['tour_daily'])) {
        echo "tour_daily inside period: " . count($periods[0]['tour_daily']) . " items\n";
        
        $firstDay = $periods[0]['tour_daily'][0];
        echo "\nFirst tour_daily keys: " . implode(', ', array_keys($firstDay)) . "\n";
        
        // Remove large nested data for display
        $display = $firstDay;
        if (isset($display['day_list'])) {
            $dlCount = count($display['day_list']);
            echo "day_list count in first daily: {$dlCount}\n";
            if (!empty($display['day_list'])) {
                echo "First day_list keys: " . implode(', ', array_keys($display['day_list'][0])) . "\n";
                echo "First day_list: " . json_encode($display['day_list'][0], JSON_UNESCAPED_UNICODE) . "\n";
                if ($dlCount > 1) {
                    echo "Second day_list: " . json_encode($display['day_list'][1], JSON_UNESCAPED_UNICODE) . "\n";
                }
            }
            unset($display['day_list']);
        }
        echo "\nFirst daily (no day_list): " . json_encode($display, JSON_UNESCAPED_UNICODE) . "\n";
        
        // All days
        echo "\nAll tour_daily in first period:\n";
        foreach ($periods[0]['tour_daily'] as $i => $day) {
            $dlc = isset($day['day_list']) ? count($day['day_list']) : 0;
            $dn = $day['day_num'] ?? 'N/A';
            $dt = isset($day['day_topics']) ? mb_substr($day['day_topics'], 0, 80) : '';
            echo "  [{$i}] day_num={$dn}, topics={$dt}, day_list={$dlc}\n";
        }
    }
    
    if (isset($periods[0]['tour_period'])) {
        echo "\ntour_period inside first period: " . count($periods[0]['tour_period']) . " items\n";
    }
}

// Flatten analysis
echo "\n=== FLATTEN ANALYSIS ===\n";
$totalDaily = 0;
$totalDayList = 0;
$uniqueDays = 0;
foreach ($periods as $pi => $period) {
    $td = $period['tour_daily'] ?? [];
    $pDaily = count($td);
    $totalDaily += $pDaily;
    if ($pi === 0) $uniqueDays = $pDaily;
    foreach ($td as $d) {
        $totalDayList += isset($d['day_list']) ? count($d['day_list']) : 0;
    }
}
echo "Unique days (first period tour_daily): {$uniqueDays}\n";
echo "Path 'periods[].tour_daily[]' => {$totalDaily} items\n";
echo "Path 'periods[].tour_daily[].day_list[]' => {$totalDayList} items\n";
echo "\nCurrent config path: periods[].tour_daily[].day_list[]\n";
echo "This OVER-FLATTENS to {$totalDayList} items instead of {$uniqueDays}!\n";

// What the correct approach should be
echo "\n=== RECOMMENDATION ===\n";
if ($uniqueDays > 0 && $totalDayList > $uniqueDays) {
    echo "PROBLEM: Itinerary path goes too deep (day_list level)\n";
    echo "  - day_number and title are at 'tour_daily' level (day_num, day_topics)\n";
    echo "  - day_list contains sub-items within each day (meals, activities)\n";
    echo "FIX: Change itineraries.path to 'periods[0].tour_daily[]' or deduplicate by day_number\n";
}
