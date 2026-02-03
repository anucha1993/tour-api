<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Jobs\SyncToursJob;
use Illuminate\Support\Facades\Bus;

$wholesalerId = isset($argv[1]) ? (int)$argv[1] : 3;

echo "=== Test Sync Wholesaler #$wholesalerId (1 record) ===\n";

// Dispatch and run synchronously
try {
    $job = new SyncToursJob($wholesalerId, null, 'incremental', 1);
    Bus::dispatchSync($job);
    echo "\n✅ Sync completed successfully!\n";
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Check latest sync log
$log = \App\Models\SyncLog::where('wholesaler_id', $wholesalerId)
    ->orderBy('id', 'desc')
    ->first();

if ($log) {
    echo "\n=== Latest Sync Log ===\n";
    echo "Status: {$log->status}\n";
    echo "Tours received: {$log->tours_received}\n";
    echo "Tours created: {$log->tours_created}\n";
    echo "Tours updated: {$log->tours_updated}\n";
    echo "Periods received: {$log->periods_received}\n";
    echo "Periods created: {$log->periods_created}\n";
    echo "Periods updated: {$log->periods_updated}\n";
    
    if ($log->error_summary) {
        echo "\nErrors:\n";
        print_r(json_decode($log->error_summary, true));
    }
}
