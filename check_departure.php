<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Setting;

echo "=== Test Setting Model ===\n";
$config = Setting::get('tour_aggregations');
echo "Global Config:\n";
print_r($config);

echo "\n=== API Response Format ===\n";
$response = app(\App\Http\Controllers\SettingsController::class)->getAggregationConfig();
$data = json_decode($response->getContent(), true);
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
