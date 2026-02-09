<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Step 1: Cancel stuck sync #458 ===" . PHP_EOL;
$updated = DB::table('sync_logs')
    ->where('id', 458)
    ->where('status', 'running')
    ->update([
        'status' => 'failed',
        'completed_at' => now(),
        'error_summary' => json_encode(['message' => 'Cancelled - old worker process crashed']),
    ]);
echo "Updated: {$updated} rows" . PHP_EOL;

echo PHP_EOL . "=== Step 2: Clear all locks ===" . PHP_EOL;
$locks = DB::table('cache')->where('key', 'like', '%sync_lock%')->delete();
echo "Locks deleted: {$locks}" . PHP_EOL;

echo PHP_EOL . "=== Step 3: Clear orphaned jobs ===" . PHP_EOL;
$jobs = DB::table('jobs')->delete();
echo "Jobs deleted: {$jobs}" . PHP_EOL;

echo PHP_EOL . "=== Step 4: Send queue:restart signal ===" . PHP_EOL;
// This writes to cache, telling all workers to restart after current job
Artisan::call('queue:restart');
echo Artisan::output();
echo "Restart signal sent!" . PHP_EOL;

echo PHP_EOL . "=== Step 5: Dispatch new sync job ===" . PHP_EOL;
\App\Jobs\SyncToursJob::dispatch(5, null, 'manual', 10);
echo "Job dispatched for integration 5 (manual, limit 10)" . PHP_EOL;

echo PHP_EOL . "=== Current state ===" . PHP_EOL;
$jobCount = DB::table('jobs')->count();
echo "Jobs in queue: {$jobCount}" . PHP_EOL;

$latest = DB::table('sync_logs')->where('wholesaler_id', 5)->orderByDesc('id')->first();
echo "Latest sync: #{$latest->id} | {$latest->status}" . PHP_EOL;

echo PHP_EOL . "Now: " . now()->toDateTimeString() . PHP_EOL;
echo "Wait for cron to restart worker and pick up job..." . PHP_EOL;
