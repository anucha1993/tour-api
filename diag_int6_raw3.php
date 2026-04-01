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

echo "All endpoints:\n";
foreach ($endpoints as $key => $url) {
    echo "  {$key}: {$url}\n";
}

$tour = Tour::where('wholesaler_id', 6)->whereNotNull('external_id')->first();
echo "\nTour: {$tour->tour_code} (external_id: {$tour->external_id})\n";

// Check if there's a detail endpoint
$detailEndpoint = $endpoints['detail'] ?? $endpoints['tour_detail'] ?? null;
if ($detailEndpoint) {
    echo "Detail endpoint: {$detailEndpoint}\n";
}

// Try fetching the tour detail via adapter
$adapter = AdapterFactory::create(6);

// Check what methods the adapter has
$ref = new ReflectionClass($adapter);
echo "\nAdapter class: " . get_class($adapter) . "\n";
echo "Adapter methods: " . implode(', ', array_map(fn($m) => $m->getName(), $ref->getMethods(\ReflectionMethod::IS_PUBLIC))) . "\n";

// Try fetchTourDetail if exists
if (method_exists($adapter, 'fetchTourDetail')) {
    if ($detailEndpoint) {
        $url = str_replace(
            ['{external_id}', '{tour_code}', '{wholesaler_tour_code}', '{id}', '{tour_id}'],
            [$tour->external_id, $tour->tour_code, $tour->wholesaler_tour_code ?? '', $tour->external_id, $tour->external_id],
            $detailEndpoint
        );
        echo "\nFetching detail from: {$url}\n";
        $detail = $adapter->fetchTourDetail($url);
        
        if (is_array($detail)) {
            echo "Detail top-level keys: " . implode(', ', array_keys($detail)) . "\n";
            
            if (isset($detail['periods'])) {
                $periodCount = count($detail['periods']);
                echo "\nPeriods count: {$periodCount}\n";
                
                if (isset($detail['periods'][0])) {
                    $fp = $detail['periods'][0];
                    echo "First period keys: " . implode(', ', array_keys($fp)) . "\n";
                    
                    if (isset($fp['tour_daily'])) {
                        $dailyCount = count($fp['tour_daily']);
                        echo "First period tour_daily count: {$dailyCount}\n";
                        
                        if (isset($fp['tour_daily'][0])) {
                            $fd = $fp['tour_daily'][0];
                            echo "First daily keys: " . implode(', ', array_keys($fd)) . "\n";
                            
                            $fdClean = $fd;
                            $dayListCount = 0;
                            if (isset($fdClean['day_list'])) {
                                $dayListCount = count($fdClean['day_list']);
                                if (isset($fdClean['day_list'][0])) {
                                    echo "First day_list keys: " . implode(', ', array_keys($fdClean['day_list'][0])) . "\n";
                                    echo "First day_list item: " . json_encode($fdClean['day_list'][0], JSON_UNESCAPED_UNICODE) . "\n";
                                }
                                unset($fdClean['day_list']);
                            }
                            echo "\nFirst daily (without day_list): " . json_encode($fdClean, JSON_UNESCAPED_UNICODE) . "\n";
                        }
                        
                        // All days summary
                        echo "\nAll days in first period:\n";
                        foreach ($fp['tour_daily'] as $i => $day) {
                            $dlc = isset($day['day_list']) ? count($day['day_list']) : 0;
                            $dn = $day['day_num'] ?? 'N/A';
                            $dt = isset($day['day_topics']) ? mb_substr($day['day_topics'], 0, 60) : '';
                            echo "  [{$i}] day_num={$dn}, topics={$dt}, day_list={$dlc}\n";
                        }
                    }
                    
                    // Flatten analysis
                    $totalDaily = 0;
                    $totalDayList = 0;
                    foreach ($detail['periods'] as $p) {
                        if (isset($p['tour_daily'])) {
                            $totalDaily += count($p['tour_daily']);
                            foreach ($p['tour_daily'] as $d) {
                                if (isset($d['day_list'])) {
                                    $totalDayList += count($d['day_list']);
                                }
                            }
                        }
                    }
                    echo "\n=== Flatten Analysis ===\n";
                    echo "Unique days (first period): {$dailyCount}\n";
                    echo "Total daily across ALL periods: {$totalDaily}\n";
                    echo "Total day_list across ALL periods: {$totalDayList}\n";
                    echo "=> Path 'periods[].tour_daily[]' produces: {$totalDaily} items\n";
                    echo "=> Path 'periods[].tour_daily[].day_list[]' produces: {$totalDayList} items\n";
                }
            }
        }
    }
}
