<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// 1. Cancel stuck sync #457
DB::table('sync_logs')->where('id', 457)->where('status', 'running')->update([
    'status' => 'failed',
    'completed_at' => now(),
    'error_summary' => json_encode(['message' => 'Cancelled - worker did not process']),
]);
echo "Sync #457 cancelled" . PHP_EOL;

// 2. Clear any leftover locks
DB::table('cache')->where('key', 'like', '%sync_lock%')->delete();
echo "Locks cleared" . PHP_EOL;

// 3. Clear any leftover jobs
$deleted = DB::table('jobs')->delete();
echo "Jobs cleared: {$deleted}" . PHP_EOL;

echo "Ready for new sync test" . PHP_EOL;
