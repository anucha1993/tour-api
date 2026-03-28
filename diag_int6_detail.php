<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SyncLog;
use App\Models\OutboundApiLog;

$wholesalerId = 6;

// Get latest sync log
$log = SyncLog::where('wholesaler_id', $wholesalerId)
    ->orderByDesc('created_at')
    ->first();

echo "=== Sync Log #{$log->id} ===\n";
echo "All columns:\n";
foreach ($log->toArray() as $k => $v) {
    if (is_array($v)) {
        echo "  {$k}: " . json_encode($v, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "  {$k}: {$v}\n";
    }
}

// ALL outbound logs for this wholesaler from sync time
echo "\n=== ALL Outbound API Logs (around sync time) ===\n";
$apiLogs = OutboundApiLog::where('wholesaler_id', $wholesalerId)
    ->where('created_at', '>=', $log->started_at ?? $log->created_at)
    ->orderBy('created_at')
    ->get();

echo "Total API calls: {$apiLogs->count()}\n\n";

foreach ($apiLogs->take(15) as $al) {
    echo "[{$al->id}] {$al->action} {$al->http_method} " . substr($al->url, 0, 120) . "\n";
    echo "  status={$al->response_status}, time={$al->response_time_ms}ms\n";
    if ($al->error_message) {
        echo "  ERROR: " . substr($al->error_message, 0, 500) . "\n";
    }
    // Check request body
    $reqBody = $al->request_body ?? $al->request_data ?? null;
    if ($reqBody) {
        echo "  request: " . substr(json_encode($reqBody, JSON_UNESCAPED_UNICODE), 0, 200) . "\n";
    }
    // Check response body  
    $resp = $al->response_body ?? [];
    if (is_array($resp) && !empty($resp)) {
        $keys = array_keys($resp);
        echo "  response keys: " . implode(', ', $keys) . "\n";
        if (isset($resp['data'])) {
            if (is_array($resp['data'])) {
                echo "  data count: " . count($resp['data']) . "\n";
            } else {
                echo "  data type: " . gettype($resp['data']) . "\n";
            }
        }
        if (isset($resp['message'])) {
            echo "  message: " . $resp['message'] . "\n";
        }
    }
    echo "\n";
}

// Count status breakdown  
$statusCounts = $apiLogs->groupBy('response_status')->map->count();
echo "=== Status breakdown ===\n";
foreach ($statusCounts as $status => $count) {
    echo "  {$status}: {$count}\n";
}

// Count action breakdown
$actionCounts = $apiLogs->groupBy('action')->map->count();
echo "\n=== Action breakdown ===\n";
foreach ($actionCounts as $action => $count) {
    echo "  {$action}: {$count}\n";
}

// Check errors
$errorLogs = $apiLogs->filter(fn($al) => !empty($al->error_message));
echo "\n=== Error samples (first 5) ===\n";
foreach ($errorLogs->take(5) as $al) {
    echo "[{$al->id}] {$al->action} {$al->url}\n";
    echo "  ERROR: " . substr($al->error_message, 0, 500) . "\n\n";
}
