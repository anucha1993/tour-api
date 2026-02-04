<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Update GO365 aggregation_config ===\n\n";

$aggregationConfig = [
    'data_structure' => [
        'departures' => [
            'path' => 'periods[].tour_period[]',
            'description' => 'Departures are nested inside periods.tour_period array'
        ],
        'itineraries' => [
            'path' => 'periods[].tour_daily[].day_list[]',
            'description' => 'Itineraries are nested inside periods.tour_daily.day_list array'
        ]
    ]
];

$jsonConfig = json_encode($aggregationConfig, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

echo "New aggregation_config:\n";
echo $jsonConfig . "\n\n";

$affected = DB::table('wholesaler_api_configs')
    ->where('wholesaler_id', 6)
    ->update(['aggregation_config' => $jsonConfig]);

if ($affected > 0) {
    echo "✅ Updated GO365 (wholesaler_id: 6) aggregation_config\n";
} else {
    echo "❌ No rows updated - check if wholesaler_id 6 exists\n";
}

// Verify
echo "\n=== Verify ===\n";
$config = DB::table('wholesaler_api_configs')->where('wholesaler_id', 6)->first();
echo "Current aggregation_config:\n";
echo $config->aggregation_config ?? 'NULL';
echo "\n";
