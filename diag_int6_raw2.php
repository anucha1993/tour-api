<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\WholesalerApiConfig;
use App\Services\WholesalerAdapters\AdapterFactory;

$config = WholesalerApiConfig::where('wholesaler_id', 6)->first();
$tour = Tour::where('wholesaler_id', 6)->whereNotNull('external_id')->first();
echo "Tour: {$tour->tour_code} (external_id: {$tour->external_id})\n\n";

// Use the adapter to fetch properly (includes auth)
$adapter = AdapterFactory::create(6);

// Fetch tours with limit 1
$result = $adapter->fetchTours(1, 1); // page 1, per_page 1

if (!$result->success) {
    echo "Fetch failed: " . ($result->error ?? 'unknown') . "\n";
    exit;
}

echo "Fetched " . count($result->tours) . " tour(s)\n";

if (empty($result->tours)) {
    echo "No tours returned\n";
    exit;
}

$rawTour = $result->tours[0];
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
            echo "\nFirst period tour_daily count: {$dailyCount}\n";
            
            if (isset($firstPeriod['tour_daily'][0])) {
                $firstDay = $firstPeriod['tour_daily'][0];
                echo "First tour_daily keys: " . implode(', ', array_keys($firstDay)) . "\n";
                
                // Show day_list structure
                if (isset($firstDay['day_list'])) {
                    $dayListCount = count($firstDay['day_list']);
                    echo "  day_list count: {$dayListCount}\n";
                    if (isset($firstDay['day_list'][0])) {
                        echo "  First day_list item keys: " . implode(', ', array_keys($firstDay['day_list'][0])) . "\n";
                        echo "  First day_list item:\n";
                        echo "  " . json_encode($firstDay['day_list'][0], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                    }
                }
                
                // Show day without day_list
                $dayInfo = $firstDay;
                unset($dayInfo['day_list']);
                echo "\nFirst tour_daily (without day_list):\n";
                echo json_encode($dayInfo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
            }
            
            // Show all day_num values
            echo "\nAll day_num values in first period:\n";
            foreach ($firstPeriod['tour_daily'] as $i => $day) {
                $dayListCount = isset($day['day_list']) ? count($day['day_list']) : 0;
                $dayNum = isset($day['day_num']) ? $day['day_num'] : 'N/A';
                $dayTopics = isset($day['day_topics']) ? mb_substr($day['day_topics'], 0, 60) : '';
                echo "  [{$i}] day_num={$dayNum}, topics={$dayTopics}, day_list_items={$dayListCount}\n";
            }
        }
        
        // Compare with second period
        if ($periodCount > 1 && isset($rawTour['periods'][1]['tour_daily'])) {
            $secondDailyCount = count($rawTour['periods'][1]['tour_daily']);
            echo "\nSecond period tour_daily count: {$secondDailyCount}\n";
        }
    }
    
    // Calculate total if flattened to day_list level
    $totalDayListItems = 0;
    $totalDailyItems = 0;
    foreach ($rawTour['periods'] as $period) {
        if (isset($period['tour_daily'])) {
            $totalDailyItems += count($period['tour_daily']);
            foreach ($period['tour_daily'] as $day) {
                if (isset($day['day_list'])) {
                    $totalDayListItems += count($day['day_list']);
                }
            }
        }
    }
    echo "\n=== Flatten Analysis ===\n";
    echo "Total if flatten to tour_daily level: {$totalDailyItems}\n";
    echo "Total if flatten to day_list level: {$totalDayListItems}\n";
    echo "Unique days (from first period): {$dailyCount}\n";
}
