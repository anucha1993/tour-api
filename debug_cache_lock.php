<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Cache store: " . config('cache.default') . PHP_EOL;
echo "Cache prefix: " . config('cache.prefix') . PHP_EOL;
echo "Cache table: " . config('cache.stores.database.table') . PHP_EOL;

echo PHP_EOL . "=== ALL entries in cache table ===" . PHP_EOL;
$all = DB::table('cache')->get();
foreach ($all as $row) {
    $exp = date('Y-m-d H:i:s', $row->expiration);
    $expired = $row->expiration < time() ? ' (EXPIRED)' : '';
    echo "  key: {$row->key} | expires: {$exp}{$expired}" . PHP_EOL;
}
echo "Total: " . $all->count() . PHP_EOL;

echo PHP_EOL . "=== ALL entries in cache_locks table (if exists) ===" . PHP_EOL;
try {
    $locks = DB::table('cache_locks')->get();
    foreach ($locks as $row) {
        $exp = date('Y-m-d H:i:s', $row->expiration);
        $expired = $row->expiration < time() ? ' (EXPIRED)' : '';
        echo "  key: {$row->key} | owner: {$row->owner} | expires: {$exp}{$expired}" . PHP_EOL;
    }
    echo "Total: " . $locks->count() . PHP_EOL;
} catch (\Exception $e) {
    echo "  Table not found: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== Force release lock ===" . PHP_EOL;
$lockKey = "sync_lock:wholesaler:5";
Cache::lock($lockKey)->forceRelease();
echo "Force released: {$lockKey}" . PHP_EOL;

// Try to acquire after release
$lock = Cache::lock($lockKey, 600);
if ($lock->get()) {
    echo "Lock now available: YES" . PHP_EOL;
    $lock->forceRelease();
} else {
    echo "Lock still not available: NO" . PHP_EOL;
}
