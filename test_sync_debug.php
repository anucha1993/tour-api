<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Testing Full Sync for Integration ID 5 ===\n\n";

try {
    $start = microtime(true);
    App\Jobs\SyncToursJob::dispatchSync(5, null, 'incremental', null);
    $elapsed = microtime(true) - $start;
    echo "SUCCESS: Sync completed in " . round($elapsed, 2) . " seconds\n";
    
    // Show result
    $log = App\Models\SyncLog::where('wholesaler_id', 5)->orderBy('id', 'desc')->first();
    echo "\nResult:\n";
    echo "  Status: {$log->status}\n";
    echo "  Tours received: {$log->tours_received}\n";
    echo "  Tours created: {$log->tours_created}\n";
    echo "  Tours updated: {$log->tours_updated}\n";
    echo "  Errors: {$log->error_count}\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
