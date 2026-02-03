<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$wholesalerId = isset($argv[1]) ? (int)$argv[1] : 3;

// Get Wholesaler info
$ws = DB::table('wholesalers')->where('id', $wholesalerId)->first();
echo "=== Wholesaler #$wholesalerId ===\n";
if ($ws) {
    echo "Name: " . $ws->name . "\n";
    echo "Code: " . $ws->code . "\n";
}

// Recent sync_logs
echo "\n=== Recent Sync Logs ===\n";
$logs = DB::table('sync_logs')
    ->where('wholesaler_id', $wholesalerId)
    ->orderBy('created_at', 'desc')
    ->limit(3)
    ->get();

foreach ($logs as $log) {
    echo "- " . $log->created_at . " | Status: " . $log->status . " | Tours: " . $log->tours_received . "/" . $log->tours_created . "/" . $log->tours_updated . "\n";
    if ($log->error_summary) {
        echo "  Error: " . substr($log->error_summary, 0, 200) . "\n";
    }
}

// Sync error logs
echo "\n=== Recent Error Logs ===\n";
$errors = DB::table('sync_error_logs')
    ->where('wholesaler_id', $wholesalerId)
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

foreach ($errors as $err) {
    echo "- " . ($err->created_at ?? '') . ": " . ($err->error_message ?? json_encode($err)) . "\n";
}

// Tours from this wholesaler
echo "\n=== Tours from Wholesaler #$wholesalerId ===\n";
$tours = DB::table('tours')->where('wholesaler_id', $wholesalerId)->limit(5)->get();
echo "Count: " . DB::table('tours')->where('wholesaler_id', $wholesalerId)->count() . "\n";
foreach ($tours as $tour) {
    echo "- ID: " . $tour->id . " | Code: " . ($tour->code ?? 'N/A') . " | transport_id: " . ($tour->transport_id ?? 'NULL') . " | country_id: " . ($tour->primary_country_id ?? 'NULL') . "\n";
}

// Check if there are any tours from wholesaler_id = 3
$toursCount = DB::table('tours')->where('wholesaler_id', 3)->count();
echo "\nTours from Wholesaler #3: " . $toursCount . "\n";

// Check failed jobs
$failedJobs = DB::table('failed_jobs')->count();
echo "Failed Jobs Total: " . $failedJobs . "\n";

// Check recent failed jobs
$recentFailed = DB::table('failed_jobs')
    ->orderBy('failed_at', 'desc')
    ->limit(3)
    ->get();

if ($recentFailed->count() > 0) {
    echo "\nRecent Failed Jobs:\n";
    foreach ($recentFailed as $job) {
        echo "- Failed at: " . $job->failed_at . "\n";
        echo "  Exception: " . substr($job->exception, 0, 200) . "...\n\n";
    }
}

// Check jobs in queue
$pendingJobs = DB::table('jobs')->count();
echo "Pending Jobs in Queue: " . $pendingJobs . "\n";

// Check sync_logs for integration 3
$syncLogs = DB::table('sync_logs')
    ->where('integration_id', 3)
    ->orderBy('created_at', 'desc')
    ->limit(5)
    ->get();

echo "\nRecent Sync Logs for Integration #3:\n";
foreach ($syncLogs as $log) {
    echo "- " . $log->created_at . " | Status: " . ($log->status ?? 'N/A') . " | Message: " . ($log->message ?? 'N/A') . "\n";
}
