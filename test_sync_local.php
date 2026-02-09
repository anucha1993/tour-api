<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Step 1: Cancel #459
DB::table('sync_logs')->where('id', 459)->where('status', 'running')->update([
    'status' => 'failed',
    'completed_at' => now(),
    'error_summary' => json_encode(['message' => 'Cancelled - worker crashed again']),
]);
echo "Sync #459 cancelled" . PHP_EOL;

// Step 2: Clear everything
DB::table('jobs')->delete();
DB::table('cache')->where('key', 'like', '%sync_lock%')->delete();
echo "Jobs + locks cleared" . PHP_EOL;

// Step 3: Dispatch new sync
\App\Jobs\SyncToursJob::dispatch(5, null, 'manual', 2); // Only 2 tours for quick test
echo "Dispatched sync (limit=2)" . PHP_EOL;

// Step 4: Run job locally to see actual errors
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
    echo "Trace: " . $e->getTraceAsString() . PHP_EOL;
}

echo PHP_EOL . "=== Final state ===" . PHP_EOL;
$latest = DB::table('sync_logs')->where('wholesaler_id', 5)->orderByDesc('id')->first();
echo "Latest: #{$latest->id} | Status: {$latest->status} | Processed: {$latest->processed_items}/{$latest->total_items}" . PHP_EOL;
echo "Heartbeat: {$latest->last_heartbeat_at}" . PHP_EOL;
echo "Error: " . ($latest->error_summary ?? 'none') . PHP_EOL;
echo "Jobs remaining: " . DB::table('jobs')->count() . PHP_EOL;
echo "Failed jobs: " . DB::table('failed_jobs')->count() . PHP_EOL;
