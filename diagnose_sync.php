<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\SyncLog;
use App\Models\WholesalerApiConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

echo "=== SYNC DIAGNOSTICS ===" . PHP_EOL . PHP_EOL;

// 1. Running syncs
$running = SyncLog::where('status', 'running')->get();
echo "1. Running syncs: {$running->count()}" . PHP_EOL;
foreach ($running as $r) {
    echo "   #{$r->id} wholesaler:{$r->wholesaler_id} started:{$r->started_at} heartbeat:{$r->last_heartbeat_at}" . PHP_EOL;
}

// 2. Cache locks
echo PHP_EOL . "2. Cache locks:" . PHP_EOL;
foreach ([1, 2, 3, 4, 5, 6, 7] as $wid) {
    $key = "sync_lock:wholesaler:{$wid}";
    // Try to acquire and immediately release to test if locked
    $lock = Cache::lock($key, 1);
    $canAcquire = $lock->get();
    if ($canAcquire) {
        $lock->forceRelease();
        echo "   wholesaler:{$wid} = FREE" . PHP_EOL;
    } else {
        echo "   wholesaler:{$wid} = LOCKED!" . PHP_EOL;
    }
}

// 3. Queue status
$pendingJobs = DB::table('jobs')->count();
$failedJobs = DB::table('failed_jobs')->count();
echo PHP_EOL . "3. Queue: pending={$pendingJobs} failed={$failedJobs}" . PHP_EOL;

if ($failedJobs > 0) {
    $failed = DB::table('failed_jobs')->orderByDesc('failed_at')->take(3)->get();
    foreach ($failed as $f) {
        $payload = json_decode($f->payload, true);
        $jobClass = class_basename($payload['displayName'] ?? 'Unknown');
        $errorLine = explode("\n", $f->exception)[0] ?? '';
        echo "   FAILED: {$jobClass} at {$f->failed_at}" . PHP_EOL;
        echo "   Error: " . substr($errorLine, 0, 200) . PHP_EOL;
    }
}

// 4. Integration configs
echo PHP_EOL . "4. Integration configs:" . PHP_EOL;
$configs = WholesalerApiConfig::all();
foreach ($configs as $config) {
    $name = $config->wholesaler?->name ?? "ID:{$config->wholesaler_id}";
    echo "   wholesaler:{$config->wholesaler_id} ({$name}) sync_enabled=" . ($config->sync_enabled ? 'YES' : 'NO') . " mode={$config->sync_mode}" . PHP_EOL;
}

echo PHP_EOL . "=== DONE ===" . PHP_EOL;
