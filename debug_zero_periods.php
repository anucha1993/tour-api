<?php
/**
 * Debug: why tours with 0 periods were created
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\Period;
use App\Services\WholesalerAdapters\AdapterFactory;
use App\Models\WholesalerApiConfig;
use App\Models\WholesalerFieldMapping;

$config = WholesalerApiConfig::find(25);

// Get tours with 0 periods
$tours = Tour::where('wholesaler_id', 57)->withCount('periods')->get();
$zeroPeriodTours = $tours->where('periods_count', 0);

echo "=== Tours with 0 periods ===" . PHP_EOL;
foreach ($zeroPeriodTours as $t) {
    echo "  {$t->wholesaler_tour_code} ({$t->external_id}): {$t->title}" . PHP_EOL;
}

// Check raw API data for one of these tours
$adapter = AdapterFactory::create($config->wholesaler_id);
$result = $adapter->fetchTours('1');

if ($result->success) {
    // Find a tour that has 0 periods in our DB
    $zeroCodes = $zeroPeriodTours->pluck('external_id')->toArray();
    
    foreach ($result->tours as $rawTour) {
        $apiId = (string) ($rawTour['id'] ?? '');
        if (in_array($apiId, $zeroCodes)) {
            $periodCount = count($rawTour['period'] ?? []);
            echo PHP_EOL . "API tour id={$apiId}, code={$rawTour['code']}: {$periodCount} periods in API" . PHP_EOL;
            
            if ($periodCount > 0) {
                // Check dates
                foreach ($rawTour['period'] as $p) {
                    $dateGo = $p['dateGo'] ?? 'N/A';
                    $parsed = date('Y-m-d', strtotime($dateGo));
                    $isPast = $parsed < date('Y-m-d');
                    echo "  pid={$p['pid']}, dateGo={$dateGo} ({$parsed}) " . ($isPast ? 'PAST' : 'FUTURE') . PHP_EOL;
                }
            } else {
                echo "  -> API returns 0 periods for this tour!" . PHP_EOL;
            }
        }
    }
}

// Explain the rollback logic
echo PHP_EOL . "=== Rollback check analysis ===" . PHP_EOL;
echo "Rollback condition: action=created AND hasDepartures AND periodsCreated=0 AND periodsUpdated=0" . PHP_EOL;
echo "hasDepartures = !empty(\$tourData['departure'])" . PHP_EOL;
echo PHP_EOL . "If API returns periods but all are past -> departures array NOT empty -> hasDepartures=true -> ROLLBACK (correct)" . PHP_EOL;
echo "If API returns 0 periods -> departures array empty -> hasDepartures=false -> NO ROLLBACK (bug!)" . PHP_EOL;
