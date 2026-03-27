<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$adapter = \App\Services\WholesalerAdapters\AdapterFactory::create(35);
$config = \App\Models\WholesalerApiConfig::where('wholesaler_id', 35)->first();
$result = $adapter->fetchTours($config);
$firstTour = $result->tours[0] ?? null;

if ($firstTour) {
    echo "Top-level keys: " . implode(', ', array_keys($firstTour)) . PHP_EOL;
    foreach ($firstTour as $k => $v) {
        if (is_array($v)) {
            echo "  $k → array(" . count($v) . ")";
            if (count($v) > 0 && isset($v[0]) && is_array($v[0])) {
                echo " sub-keys: " . implode(', ', array_keys($v[0]));
            }
            echo PHP_EOL;
        }
    }

    // Show one period sample
    if (!empty($firstTour['periods'])) {
        echo PHP_EOL . "=== Sample period ===" . PHP_EOL;
        print_r($firstTour['periods'][0]);
    } else {
        echo PHP_EOL . "NO periods in tour data" . PHP_EOL;
    }

    // Count tours with periods
    $withPeriods = 0;
    $totalPeriods = 0;
    foreach ($result->tours as $t) {
        $pc = count($t['periods'] ?? []);
        if ($pc > 0) {
            $withPeriods++;
            $totalPeriods += $pc;
        }
    }
    echo PHP_EOL . "=== Period stats ===" . PHP_EOL;
    echo "Total tours: " . count($result->tours) . PHP_EOL;
    echo "Tours with periods: $withPeriods" . PHP_EOL;
    echo "Total periods: $totalPeriods" . PHP_EOL;
}
