<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Cancel stuck sync #456
$updated = DB::table('sync_logs')
    ->where('id', 456)
    ->where('status', 'running')
    ->update([
        'status' => 'failed',
        'completed_at' => now(),
        'error_summary' => json_encode(['message' => 'Cancelled - production code not yet deployed']),
    ]);

echo "Sync #456 updated: {$updated} rows" . PHP_EOL;

// Verify
$log = DB::table('sync_logs')->where('id', 455)->first();
echo "Status now: {$log->status}" . PHP_EOL;
