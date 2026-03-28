<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check how other working integrations have their field mappings
// Pick one that works (e.g. wholesaler_id=35 which is iTravel, integration 20)

echo "=== WholesalerFieldMapping for wholesaler 35 (iTravel) ===\n";
$mappings = App\Models\WholesalerFieldMapping::where('wholesaler_id', 35)
    ->where('is_active', true)
    ->get();
echo "Count: " . $mappings->count() . "\n";
foreach ($mappings as $m) {
    echo "  [{$m->section_name}] {$m->our_field} ← {$m->their_field}" 
        . ($m->their_field_path ? " (path: {$m->their_field_path})" : '')
        . ($m->transform_type !== 'direct' ? " [{$m->transform_type}]" : '')
        . ($m->default_value ? " default={$m->default_value}" : '')
        . "\n";
}

// Now check a few others that we know work
echo "\n=== WholesalerFieldMapping for wholesaler 57 (BEST) ===\n";
$mappings57 = App\Models\WholesalerFieldMapping::where('wholesaler_id', 57)->get();
echo "Count: " . $mappings57->count() . "\n";
foreach ($mappings57 as $m) {
    echo "  [{$m->section_name}] {$m->our_field} ← {$m->their_field}" 
        . ($m->their_field_path ? " (path: {$m->their_field_path})" : '')
        . "\n";
}

// Check what happens when GenericRestAdapter reads the BEST API response
// response['data'] = { data: [...tours], meta: {...} }
// So $tours = response['data'] which is an object with 'data' and 'meta' keys, not an array of tours!
echo "\n=== SIMULATING GenericRestAdapter parsing ===\n";
$response = json_decode(file_get_contents(__DIR__ . '/api_response_int25.json'), true);
// Wrap in the full response structure
$fullResponse = ['data' => $response, 'status' => 200, 'message' => 'Success'];

// Wait, the actual full response from the API is:
// { data: { data: [...], meta: {...} }, status: 200, message: "Success" }
// And api_response_int25.json only has the first tour item from data.data[0]

// Let me re-fetch
$url = 'https://tour-api.bestinternational.com/api/tour-programs/v2/tour/all?page=1&limit=2';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_SSL_VERIFYPEER => false,
]);
$raw = curl_exec($ch);
curl_close($ch);

$fullResponse = json_decode($raw, true);
echo "Full response top-level keys: " . implode(', ', array_keys($fullResponse)) . "\n";
echo "fullResponse['data'] type: " . gettype($fullResponse['data']) . "\n";
echo "fullResponse['data'] keys: " . implode(', ', array_keys($fullResponse['data'])) . "\n";

// GenericRestAdapter does: $tours = $response['data'] ?? ...
$tours = $fullResponse['data'] ?? [];
echo "\nWhat GenericRestAdapter gets as \$tours:\n";
echo "  type: " . gettype($tours) . "\n";
echo "  is array with [0]? " . (isset($tours[0]) ? 'YES' : 'NO') . "\n";
echo "  keys: " . implode(', ', array_keys($tours)) . "\n";
echo "  tours['data'] count: " . (is_array($tours['data'] ?? null) ? count($tours['data']) : 'N/A') . "\n";

// So $tours = { data: [...], meta: {...} } which is NOT an array of tours
// count($tours) = 2 (keys: data, meta)
// But $tours[0] doesn't exist → SyncResult gets 0 real tours
echo "\n=== DIAGNOSIS ===\n";
echo "The GenericRestAdapter gets \$tours = response['data'] = {data: [...], meta: {...}}\n";
echo "This is NOT an array of tour objects!\n";
echo "count(\$tours)=" . count($tours) . " but these are 'data' and 'meta' keys, not tours\n";
echo "\nTo fix: need to handle nested data.data format\n";
echo "Actual tours are at: response['data']['data']\n";
echo "Actual meta is at: response['data']['meta']\n";
echo "Meta: " . json_encode($tours['meta'] ?? null) . "\n";
