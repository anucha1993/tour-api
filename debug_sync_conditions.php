<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WholesalerApiConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

$wholesalerId = 5;
$syncType = 'manual';
$transformedData = null;

echo "=== Debug SyncToursJob conditions ===" . PHP_EOL;

// Step 1: Config
$config = WholesalerApiConfig::where('wholesaler_id', $wholesalerId)->first();
if (!$config) {
    echo "FAIL: Config not found for wholesaler_id={$wholesalerId}" . PHP_EOL;
    exit;
}
echo "Config found: ID={$config->id}, wholesaler_id={$config->wholesaler_id}" . PHP_EOL;
echo "  sync_enabled: " . ($config->sync_enabled ? 'YES' : 'NO') . PHP_EOL;

// Step 2: Sync enabled check
if (!$config->sync_enabled && !$transformedData && $syncType !== 'full') {
    echo "EARLY EXIT: Sync disabled and no transformed data and not full sync" . PHP_EOL;
    echo "  -> This is why the job exits without creating sync_log!" . PHP_EOL;
    exit;
}
echo "Sync enabled check: PASSED" . PHP_EOL;

// Step 3: Lock check
if (!$transformedData) {
    $lockKey = "sync_lock:wholesaler:{$wholesalerId}";
    echo "Lock key: {$lockKey}" . PHP_EOL;
    
    // Check existing locks
    $existingLock = DB::table('cache')->where('key', 'like', "%{$lockKey}%")->first();
    if ($existingLock) {
        echo "  Existing lock: expires " . date('Y-m-d H:i:s', $existingLock->expiration) . PHP_EOL;
        echo "  Expired: " . ($existingLock->expiration < time() ? 'YES' : 'NO') . PHP_EOL;
    } else {
        echo "  No existing lock" . PHP_EOL;
    }
    
    // Try acquire
    $lock = Cache::lock($lockKey, 600);
    if ($lock->get()) {
        echo "  Lock acquired: YES" . PHP_EOL;
        $lock->forceRelease();
    } else {
        echo "  Lock acquired: NO - another sync already running!" . PHP_EOL;
    }
}

// Step 4: Stuck sync logs
$stuckSyncs = DB::table('sync_logs')
    ->where('wholesaler_id', $wholesalerId)
    ->where('status', 'running')
    ->get();
echo "Running sync_logs: " . $stuckSyncs->count() . PHP_EOL;
foreach ($stuckSyncs as $s) {
    echo "  #{$s->id} | started: {$s->started_at} | hb: {$s->last_heartbeat_at}" . PHP_EOL;
}

echo PHP_EOL . "=== All checks passed - job should proceed to createSyncLog ===" . PHP_EOL;
