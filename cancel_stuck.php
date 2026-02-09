<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Cancel stuck sync #455
$updated = DB::table('sync_logs')
    ->where('id', 455)
    ->where('status', 'running')
    ->update([
        'status' => 'failed',
        'completed_at' => now(),
        'error_summary' => json_encode(['message' => 'Cancelled - worker inactive on production']),
    ]);

echo "Sync #455 updated: {$updated} rows" . PHP_EOL;

// Verify
$log = DB::table('sync_logs')->where('id', 455)->first();
echo "Status now: {$log->status}" . PHP_EOL;
