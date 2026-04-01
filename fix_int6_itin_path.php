<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\WholesalerApiConfig;

$config = WholesalerApiConfig::where('wholesaler_id', 6)->first();
$aggConfig = $config->aggregation_config ?? [];

echo "BEFORE:\n";
echo json_encode($aggConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Fix: change path from periods[].tour_daily[].day_list[] to periods[].tour_daily[]
$aggConfig['data_structure']['itineraries']['path'] = 'periods[].tour_daily[]';

$config->aggregation_config = $aggConfig;
$config->save();

echo "AFTER:\n";
$config->refresh();
echo json_encode($config->aggregation_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// Also fix the field mappings - day_num and day_topics are at tour_daily level
// Currently they have the old prefix which will be stripped anyway, but let's verify
$mappings = \App\Models\WholesalerFieldMapping::where('wholesaler_id', 6)
    ->where('section_name', 'itinerary')
    ->where('is_active', true)
    ->get();

echo "\nField mappings:\n";
foreach ($mappings as $m) {
    echo "  {$m->our_field} <= {$m->their_field_path} (transform: {$m->transform_type})\n";
}

echo "\nDone! Path updated to periods[].tour_daily[]\n";
