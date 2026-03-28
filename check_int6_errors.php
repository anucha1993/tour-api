<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Check columns first
$cols = Schema::getColumnListing('sync_error_logs');
echo "Columns: " . implode(', ', $cols) . "\n\n";

// Find the latest sync log for integration 6
$syncLog = DB::table('sync_logs')->where('wholesaler_id', 6)->orderByDesc('id')->first();
echo "Sync Log ID: {$syncLog->id}\n";

// Check SyncErrorLog 
$errors = DB::table('sync_error_logs')->where('sync_log_id', $syncLog->id)->get();
echo "Error count: " . $errors->count() . "\n";

if ($errors->count() > 0) {
    echo "\n=== First 3 errors (full row) ===\n";
    foreach ($errors->take(3) as $err) {
        echo "---\n";
        foreach ((array)$err as $col => $val) {
            if ($col === 'raw_data') {
                echo "{$col}: [truncated " . strlen($val ?? '') . " bytes]\n";
            } else {
                echo "{$col}: {$val}\n";
            }
        }
    }
    
    // Group by error_message (first 150 chars)
    echo "\n=== Errors by message ===\n";
    $byMsg = [];
    foreach ($errors as $err) {
        $msg = $err->error_message ?? $err->message ?? 'unknown';
        $key = substr($msg, 0, 150);
        $byMsg[$key] = ($byMsg[$key] ?? 0) + 1;
    }
    foreach ($byMsg as $msg => $count) {
        echo "{$count}x: {$msg}\n";
    }
}
