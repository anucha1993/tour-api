<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

echo "=== Cleaning up stuck sync jobs ===\n\n";

// 1. Fix stuck sync logs (status = running)
$stuckCount = SyncLog::where('status', 'running')->count();
echo "1. Found {$stuckCount} stuck sync logs (status=running)\n";

if ($stuckCount > 0) {
    SyncLog::where('status', 'running')->update([
        'status' => 'failed',
        'completed_at' => now(),
        'error_summary' => json_encode(['message' => 'Manually cleaned up - stuck sync']),
    ]);
    echo "   Fixed!\n";
}

// 2. Check failed_jobs table
$failedCount = DB::table('failed_jobs')->count();
echo "\n2. Found {$failedCount} failed jobs in queue\n";

// 3. Flush failed jobs
if ($failedCount > 0) {
    DB::table('failed_jobs')->truncate();
    echo "   Flushed!\n";
}

// 4. Check pending jobs
$pendingCount = DB::table('jobs')->count();
echo "\n3. Found {$pendingCount} pending jobs in queue\n";

// Show pending jobs details
if ($pendingCount > 0) {
    $jobs = DB::table('jobs')->get();
    foreach ($jobs as $job) {
        $payload = json_decode($job->payload, true);
        echo "   - ID: {$job->id}, Queue: {$job->queue}, Job: " . ($payload['displayName'] ?? 'unknown') . "\n";
        echo "     Attempts: {$job->attempts}, Available at: " . date('Y-m-d H:i:s', $job->available_at) . "\n";
    }
}

// 5. Release any cache locks (both cache and cache_locks tables)
echo "\n4. Releasing sync locks...\n";
$wholesalerIds = SyncLog::distinct()->pluck('wholesaler_id');
foreach ($wholesalerIds as $wId) {
    $lockKey = "sync_lock:wholesaler:{$wId}";
    Cache::lock($lockKey)->forceRelease();
    echo "   Released lock for wholesaler {$wId}\n";
}

// Also clean up cache_locks table directly
$locksCleaned = DB::table('cache_locks')
    ->where('key', 'like', '%sync_lock%')
    ->delete();
if ($locksCleaned > 0) {
    echo "   Cleaned {$locksCleaned} entries from cache_locks table\n";
}

echo "\n=== Done! ===\n";
