<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Latest Sync Error Logs ===\n\n";

$logs = DB::table('sync_error_logs')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

if ($logs->isEmpty()) {
    echo "No sync errors found.\n";
} else {
    foreach ($logs as $log) {
        echo "ID: {$log->id}\n";
        // Print all fields
        foreach ((array) $log as $key => $value) {
            if ($key !== 'id') {
                $val = is_string($value) ? substr($value, 0, 500) : $value;
                echo "  {$key}: {$val}\n";
            }
        }
        echo str_repeat('-', 80) . "\n\n";
    }
}

// Also check sync_logs for recent failed syncs
echo "\n=== Recent Sync Logs (last 5) ===\n\n";

$syncLogs = DB::table('sync_logs')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

foreach ($syncLogs as $log) {
    echo "ID: {$log->id}\n";
    foreach ((array) $log as $key => $value) {
        if ($key !== 'id') {
            $val = is_string($value) ? substr($value, 0, 1000) : $value;
            echo "  {$key}: {$val}\n";
        }
    }
    echo str_repeat('-', 80) . "\n";
}
