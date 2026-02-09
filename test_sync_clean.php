<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Step 1: Force release any locks
Cache::lock("sync_lock:wholesaler:5")->forceRelease();
echo "Lock released" . PHP_EOL;

// Step 2: Dispatch sync (limit 2 for quick test)
\App\Jobs\SyncToursJob::dispatch(5, null, 'manual', 2);
echo "Dispatched sync (manual, limit=2)" . PHP_EOL;

// Step 3: Process locally
echo PHP_EOL . "=== Processing job locally ===" . PHP_EOL;
try {
    Artisan::call('queue:work', [
        '--once' => true,
        '--timeout' => 600,
        '--verbose' => true,
    ]);
    echo Artisan::output();
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
}

echo PHP_EOL . "=== Result ===" . PHP_EOL;
$latest = DB::table('sync_logs')->where('wholesaler_id', 5)->orderByDesc('id')->first();
echo "Sync #{$latest->id} | Status: {$latest->status} | Processed: {$latest->processed_items}/{$latest->total_items}" . PHP_EOL;
echo "Created: {$latest->tours_created} | Updated: {$latest->tours_updated} | Failed: {$latest->tours_failed}" . PHP_EOL;
echo "Periods: {$latest->periods_received} created:{$latest->periods_created} updated:{$latest->periods_updated}" . PHP_EOL;
echo "Duration: {$latest->duration_seconds}s" . PHP_EOL;
echo "Error: " . ($latest->error_summary ?? 'none') . PHP_EOL;
echo "Jobs: " . DB::table('jobs')->count() . " | Failed: " . DB::table('failed_jobs')->count() . PHP_EOL;

// Check lock state
$lockExists = DB::table('cache_locks')->where('key', 'like', '%sync_lock%')->exists();
echo "Lock after sync: " . ($lockExists ? 'YES (stuck!)' : 'NO (clean)') . PHP_EOL;
