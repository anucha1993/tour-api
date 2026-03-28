<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SyncLog;
use App\Models\OutboundApiLog;

$wholesalerId = 6;

// Latest sync log
$log = SyncLog::where('wholesaler_id', $wholesalerId)
    ->orderByDesc('created_at')
    ->first();

echo "=== Sync Log #{$log->id} ===\n";
echo "Status: {$log->status}\n";
echo "Started: {$log->started_at}\n";
echo "Ended: {$log->ended_at}\n";
echo "tours_fetched: {$log->tours_fetched}\n";
echo "tours_created: {$log->tours_created}\n";
echo "tours_updated: {$log->tours_updated}\n";
echo "tours_skipped: {$log->tours_skipped}\n";
echo "periods_created: {$log->periods_created}\n";
echo "periods_updated: {$log->periods_updated}\n";
echo "error_count: {$log->error_count}\n";
echo "error_message: " . ($log->error_message ?? 'null') . "\n";

// Check sync_details or error_details
if ($log->sync_details) {
    echo "\n=== sync_details ===\n";
    echo json_encode($log->sync_details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

// Outbound API logs for this sync
echo "\n=== Outbound API Logs (last 10) ===\n";
$apiLogs = OutboundApiLog::where('sync_log_id', $log->id)
    ->orderByDesc('created_at')
    ->limit(10)
    ->get();

if ($apiLogs->isEmpty()) {
    // Try without sync_log_id filter
    $apiLogs = OutboundApiLog::where('wholesaler_id', $wholesalerId)
        ->where('created_at', '>=', $log->started_at)
        ->orderByDesc('created_at')
        ->limit(10)
        ->get();
}

foreach ($apiLogs as $al) {
    echo "  [{$al->id}] {$al->action} {$al->http_method} " . substr($al->url, 0, 100) . "\n";
    echo "    status: {$al->response_status}, time: {$al->response_time_ms}ms\n";
    if ($al->error_message) {
        echo "    ERROR: " . substr($al->error_message, 0, 300) . "\n";
    }
    echo "\n";
}

// Check Laravel logs for errors
echo "\n=== Recent Log Errors (from storage/logs) ===\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    // Get last 200 lines and search for go365/integration 6 errors
    $lines = [];
    $fh = fopen($logFile, 'r');
    if ($fh) {
        // Read last 5000 lines
        $allLines = [];
        while (($line = fgets($fh)) !== false) {
            $allLines[] = $line;
            if (count($allLines) > 5000) {
                array_shift($allLines);
            }
        }
        fclose($fh);
        
        // Search for recent errors related to sync
        $errorLines = [];
        $inError = false;
        foreach (array_reverse($allLines) as $line) {
            if (count($errorLines) >= 50) break;
            
            if (preg_match('/\[\d{4}-\d{2}-\d{2}.*\] .+\.(ERROR|error)/', $line)) {
                $inError = true;
                $errorLines[] = trim($line);
            } elseif ($inError && (str_starts_with($line, '[') || str_starts_with($line, ' '))) {
                if (str_starts_with($line, '[')) {
                    $inError = false;
                }
            }
        }
        
        if (!empty($errorLines)) {
            foreach (array_slice(array_reverse($errorLines), -10) as $el) {
                echo substr($el, 0, 500) . "\n\n";
            }
        } else {
            echo "No recent errors found in log file\n";
        }
    }
} else {
    echo "Log file not found\n";
}
