<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Check outbound API logs for sync 3832
echo "=== Outbound API logs for sync 3832 ===\n";
$logs = DB::table('outbound_api_logs')
    ->where('sync_log_id', 3832)
    ->orderByDesc('id')
    ->limit(5)
    ->get();

echo "Total API calls: " . DB::table('outbound_api_logs')->where('sync_log_id', 3832)->count() . "\n\n";

foreach ($logs as $log) {
    echo "---\n";
    echo "URL: {$log->url}\n";
    echo "Request type: {$log->request_type}\n";
    echo "Status: {$log->response_status}\n";
    echo "Duration: {$log->duration_ms}ms\n";
    echo "Created: {$log->created_at}\n";
    echo "Items: {$log->items_count}\n";
}

// Check if there are any API calls that took very long
echo "\n=== Slowest API calls for integration 6 (recent) ===\n";
$slow = DB::table('outbound_api_logs')
    ->where('sync_log_id', 3832)
    ->orderByDesc('duration_ms')
    ->limit(5)
    ->get(['url', 'duration_ms', 'response_status', 'created_at', 'request_type']);
foreach ($slow as $s) {
    echo "{$s->duration_ms}ms | {$s->request_type} | {$s->response_status} | {$s->url}\n";
}

// Check PHP memory/time limits
echo "\n=== PHP limits ===\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "Current memory: " . round(memory_get_peak_usage(true) / 1024 / 1024, 1) . " MB\n";
