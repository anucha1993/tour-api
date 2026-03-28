<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Find the latest sync log for integration 6
$syncLog = DB::table('sync_logs')->where('wholesaler_id', 6)->orderByDesc('id')->first();
echo "Sync Log ID: {$syncLog->id}\n";
echo "Status: {$syncLog->status}\n";
echo "Tours received: {$syncLog->tours_received}\n";
echo "Tours created: {$syncLog->tours_created}\n";
echo "Tours updated: {$syncLog->tours_updated}\n";
echo "Tours skipped: {$syncLog->tours_skipped}\n";
echo "Tours failed: {$syncLog->tours_failed}\n";
echo "Error count: {$syncLog->error_count}\n";
echo "Started: {$syncLog->started_at}\n";
echo "Completed: {$syncLog->completed_at}\n";

// Check errors for this sync
$errors = DB::table('sync_error_logs')->where('sync_log_id', $syncLog->id)->get();
echo "\nSync Error Logs: " . $errors->count() . "\n";

if ($errors->count() > 0) {
    echo "\n=== First 3 errors ===\n";
    foreach ($errors->take(3) as $err) {
        echo "---\n";
        echo "error_type: {$err->error_type}\n";
        echo "error_message: " . substr($err->error_message, 0, 200) . "\n";
    }
    
    // Group by error message
    echo "\n=== Errors by message ===\n";
    $byMsg = [];
    foreach ($errors as $err) {
        $key = substr($err->error_message, 0, 80);
        $byMsg[$key] = ($byMsg[$key] ?? 0) + 1;
    }
    foreach ($byMsg as $msg => $count) {
        echo "{$count}x: {$msg}\n";
    }
}

// Tour counts for wholesaler 6
echo "\n=== Tour counts (wholesaler_id=6) ===\n";
$totalTours = DB::table('tours')->where('wholesaler_id', 6)->count();
$totalPeriods = DB::table('periods')->whereIn('tour_id', DB::table('tours')->where('wholesaler_id', 6)->select('id'))->count();
echo "Total tours: {$totalTours}\n";
echo "Total periods: {$totalPeriods}\n";
