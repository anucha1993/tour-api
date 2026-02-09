<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Cancel #461
DB::table('sync_logs')->where('id', 461)->where('status', 'running')->update([
    'status' => 'failed', 'completed_at' => now(),
    'error_summary' => json_encode(['message' => 'Cancelled for debugging']),
]);
// Release lock
Cache::lock("sync_lock:wholesaler:5")->forceRelease();
DB::table('cache_locks')->where('key', 'like', '%sync_lock%')->delete();
DB::table('jobs')->delete();
echo "Cleaned up" . PHP_EOL;

// Dispatch with limit=1 (single tour test)
\App\Jobs\SyncToursJob::dispatch(5, null, 'manual', 1);
echo "Dispatched (limit=1)" . PHP_EOL;
echo "Jobs: " . DB::table('jobs')->count() . PHP_EOL;
