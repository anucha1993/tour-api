<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== SYNC LOGS (integration 5) ===" . PHP_EOL;
$logs = DB::table('sync_logs')->where('wholesaler_id', 5)->orderByDesc('id')->limit(5)->get();
foreach ($logs as $log) {
    $cols = (array) $log;
    echo "ID: {$log->id} | Status: {$log->status} | Type: {$log->sync_type}" . PHP_EOL;
    echo "  Started: {$log->started_at}" . PHP_EOL;
    echo "  Heartbeat: " . ($cols['last_heartbeat_at'] ?? 'NULL') . PHP_EOL;
    echo "  Completed: " . ($cols['completed_at'] ?? 'NULL') . PHP_EOL;
    echo "  Error: " . ($cols['error_message'] ?? 'NULL') . PHP_EOL;
    // Print all columns
    foreach ($cols as $k => $v) {
        if (!in_array($k, ['id','status','sync_type','started_at'])) {
            echo "  {$k}: " . ($v ?? 'NULL') . PHP_EOL;
        }
    }
    echo PHP_EOL;
}

echo "=== JOBS TABLE ===" . PHP_EOL;
$jobs = DB::table('jobs')->get();
if ($jobs->isEmpty()) {
    echo "No jobs in queue" . PHP_EOL;
} else {
    foreach ($jobs as $job) {
        echo "ID: {$job->id} | Queue: {$job->queue} | Attempts: {$job->attempts}" . PHP_EOL;
        echo "  Reserved: " . ($job->reserved_at ? date('Y-m-d H:i:s', $job->reserved_at) : 'NULL') . PHP_EOL;
        echo "  Available: " . date('Y-m-d H:i:s', $job->available_at) . PHP_EOL;
        // Decode payload to see what job it is
        $payload = json_decode($job->payload, true);
        echo "  Job: " . ($payload['displayName'] ?? 'unknown') . PHP_EOL . PHP_EOL;
    }
}

echo "=== FAILED JOBS (recent 3) ===" . PHP_EOL;
$failed = DB::table('failed_jobs')->orderByDesc('id')->limit(3)->get();
if ($failed->isEmpty()) {
    echo "No failed jobs" . PHP_EOL;
} else {
    foreach ($failed as $f) {
        echo "ID: {$f->id} | Queue: {$f->queue} | Failed: {$f->failed_at}" . PHP_EOL;
        echo "  Exception: " . substr($f->exception, 0, 500) . PHP_EOL . PHP_EOL;
    }
}

echo "=== CACHE LOCK ===" . PHP_EOL;
$lock = DB::table('cache')->where('key', 'like', '%sync_lock%')->get();
if ($lock->isEmpty()) {
    echo "No sync locks" . PHP_EOL;
} else {
    foreach ($lock as $l) {
        echo "Key: {$l->key} | Expiration: " . date('Y-m-d H:i:s', $l->expiration) . PHP_EOL;
    }
}

echo PHP_EOL . "=== CURRENT TIME (server) ===" . PHP_EOL;
echo now()->toDateTimeString() . PHP_EOL;
