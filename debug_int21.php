<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$c = \App\Models\WholesalerApiConfig::find(21);

echo "=== Integration 21: TTN Europe Debug ===\n\n";

// 1. Check auth config
echo "--- Auth Config ---\n";
echo "api_base_url: {$c->api_base_url}\n";
echo "auth_type: {$c->auth_type}\n";
echo "auth_header_name: " . ($c->auth_header_name ?? 'NULL') . "\n";
$creds = $c->auth_credentials ?? [];
echo "credentials keys: " . implode(', ', array_keys($creds)) . "\n";
if (isset($creds['headers'])) {
    echo "custom headers:\n";
    foreach ($creds['headers'] as $k => $v) {
        // Redact value but show key  
        echo "  {$k}: " . mb_substr($v, 0, 10) . "...\n";
    }
}
echo "\n";

// 2. Check field mappings
$mappings = \App\Models\WholesalerFieldMapping::where('wholesaler_id', $c->wholesaler_id)
    ->where('is_active', true)
    ->get();
echo "--- Field Mappings ---\n";
echo "Total active mappings: " . $mappings->count() . "\n";
$grouped = $mappings->groupBy('section');
foreach ($grouped as $section => $fields) {
    echo "  {$section}: " . $fields->count() . " fields\n";
    foreach ($fields as $f) {
        echo "    {$f->our_field} ← {$f->their_field}\n";
    }
}
echo "\n";

// 3. Check data structure config
echo "--- Data Structure (aggregation_config) ---\n";
$agg = $c->aggregation_config;
echo json_encode($agg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

// 4. Try fetching from API directly
echo "--- Direct API Test ---\n";
$url = $c->api_base_url;
echo "GET {$url}\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => array_merge(
        ['Accept: application/json'],
        array_map(fn($k, $v) => "{$k}: {$v}", array_keys($creds['headers'] ?? []), $creds['headers'] ?? [])
    ),
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Status: {$httpCode}\n";
if ($error) echo "cURL Error: {$error}\n";
echo "Response length: " . strlen($response) . "\n";

if ($response) {
    $data = json_decode($response, true);
    if ($data === null) {
        echo "JSON decode failed. First 500 chars:\n";
        echo mb_substr($response, 0, 500) . "\n";
    } else {
        echo "JSON decoded. Type: " . gettype($data) . "\n";
        if (is_array($data)) {
            if (isset($data[0])) {
                // Indexed array
                echo "Array with " . count($data) . " items\n";
                echo "First item keys: " . implode(', ', array_keys($data[0])) . "\n";
                echo "First item (truncated):\n";
                echo json_encode($data[0], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
            } else {
                // Assoc array - show top-level keys
                echo "Object keys: " . implode(', ', array_keys($data)) . "\n";
                // Check for nested arrays
                foreach ($data as $k => $v) {
                    if (is_array($v)) {
                        echo "  {$k}: array[" . count($v) . "]\n";
                        if (!empty($v) && isset($v[0]) && is_array($v[0])) {
                            echo "    first item keys: " . implode(', ', array_keys($v[0])) . "\n";
                        }
                    } else {
                        echo "  {$k}: " . mb_substr(json_encode($v, JSON_UNESCAPED_UNICODE), 0, 80) . "\n";
                    }
                }
            }
        }
    }
}

// 5. Check SyncCursor
$cursor = \App\Models\SyncCursor::where('wholesaler_id', $c->wholesaler_id)->first();
echo "\n--- SyncCursor ---\n";
if ($cursor) {
    echo "cursor_value: " . ($cursor->cursor_value ?? 'NULL') . "\n";
    echo "last_synced_at: " . ($cursor->last_synced_at ?? 'NULL') . "\n";
} else {
    echo "No cursor found\n";
}
