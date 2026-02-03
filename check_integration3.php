<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Check jobs in queue
$jobs = DB::table('jobs')->get();
echo "=== Jobs in Queue: " . $jobs->count() . " ===\n";
foreach ($jobs as $job) {
    echo "- ID: " . $job->id . " | Queue: " . $job->queue . " | Created: " . date('Y-m-d H:i:s', $job->created_at) . "\n";
    $payload = json_decode($job->payload, true);
    echo "  Job: " . ($payload['displayName'] ?? 'Unknown') . "\n";
}

// Check tours from wholesaler 3
echo "\n=== Tours from Wholesaler #3 ===\n";
$tours = DB::table('tours')->where('wholesaler_id', 3)->get();
echo "Count: " . $tours->count() . "\n";
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
