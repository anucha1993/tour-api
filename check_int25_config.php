<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$config = App\Models\WholesalerApiConfig::find(25);
echo "ID: " . $config->id . PHP_EOL;
echo "wholesaler_id: " . $config->wholesaler_id . PHP_EOL;
echo "sync_mode: " . $config->sync_mode . PHP_EOL;
echo "sync_method: " . $config->sync_method . PHP_EOL;
echo "auth_type: " . $config->auth_type . PHP_EOL;
echo "field_mappings: " . json_encode($config->field_mappings) . PHP_EOL;
echo "response_structure: " . json_encode($config->response_structure) . PHP_EOL;
echo PHP_EOL . "auth_credentials:" . PHP_EOL;
echo json_encode($config->auth_credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo PHP_EOL . "enabled_fields:" . PHP_EOL;
echo json_encode($config->enabled_fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo PHP_EOL . "aggregation_config:" . PHP_EOL;
echo json_encode($config->aggregation_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo PHP_EOL . "extract_countries_from_name: " . ($config->extract_countries_from_name ? 'true' : 'false') . PHP_EOL;
echo "extract_cities_from_name: " . ($config->extract_cities_from_name ? 'true' : 'false') . PHP_EOL;
echo "past_period_handling: " . ($config->past_period_handling ?? 'null') . PHP_EOL;

echo PHP_EOL . "Existing field mappings for wholesaler 57:" . PHP_EOL;
$maps = App\Models\WholesalerFieldMapping::where('wholesaler_id', 57)->get();
echo "COUNT: " . $maps->count() . PHP_EOL;
foreach ($maps as $m) {
    echo "  " . $m->section_name . "." . $m->our_field . " => " . ($m->their_field_path ?? $m->their_field) . " (" . $m->transform_type . ")" . PHP_EOL;
}

// Check API response to confirm structure
echo PHP_EOL . "=== Fetching 1 tour from API ===" . PHP_EOL;
$creds = $config->auth_credentials;
$url = $config->api_base_url;
$pagination = $creds['pagination'] ?? [];
$pageParam = $pagination['page_param'] ?? 'page';
$perPageParam = $pagination['per_page_param'] ?? 'limit';

$testUrl = $url . '?' . $pageParam . '=1&' . $perPageParam . '=1';
echo "URL: " . $testUrl . PHP_EOL;

$ch = curl_init($testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Add auth headers if needed
$headers = $creds['headers'] ?? [];
$headerArr = [];
foreach ($headers as $k => $v) {
    $headerArr[] = "$k: $v";
}
if (!empty($headerArr)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArr);
}

$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $httpCode" . PHP_EOL;
$data = json_decode($resp, true);

// Show response structure
echo "Top-level keys: " . implode(', ', array_keys($data ?? [])) . PHP_EOL;
if (isset($data['data'])) {
    echo "data keys: " . implode(', ', array_keys($data['data'])) . PHP_EOL;
    if (isset($data['data']['data']) && is_array($data['data']['data'])) {
        echo "data.data count: " . count($data['data']['data']) . PHP_EOL;
        if (!empty($data['data']['data'])) {
            $tour = $data['data']['data'][0];
            echo PHP_EOL . "=== First tour structure ===" . PHP_EOL;
            echo "Tour keys: " . implode(', ', array_keys($tour)) . PHP_EOL;
            foreach ($tour as $k => $v) {
                if (is_array($v)) {
                    echo "  $k: [array, count=" . count($v) . "]" . PHP_EOL;
                    if (!empty($v) && isset($v[0]) && is_array($v[0])) {
                        echo "    First item keys: " . implode(', ', array_keys($v[0])) . PHP_EOL;
                        echo "    First item: " . json_encode($v[0], JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    }
                } else {
                    echo "  $k: " . json_encode($v, JSON_UNESCAPED_UNICODE) . PHP_EOL;
                }
            }
        }
    }
    if (isset($data['data']['meta'])) {
        echo PHP_EOL . "meta: " . json_encode($data['data']['meta'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
