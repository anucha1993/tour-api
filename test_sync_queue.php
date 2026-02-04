<?php
/**
 * Test GO365 Sync and check queue immediately
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Jobs\SyncToursJob;
use Illuminate\Support\Facades\DB;

echo "=== Testing GO365 Sync with Queue Check ===\n\n";

// Check jobs before
$jobsBefore = DB::table('jobs')->count();
echo "Jobs before sync: {$jobsBefore}\n";

// Run sync
echo "\n--- Running Sync ---\n";
$job = new SyncToursJob(6, null, 'incremental', 1);
$job->handle();
echo "Sync completed!\n";

// Check jobs after
$jobsAfter = DB::table('jobs')->count();
echo "\nJobs after sync: {$jobsAfter}\n";

if ($jobsAfter > $jobsBefore) {
    echo "\n✅ SyncPeriodsJob was dispatched to queue!\n";
    $latestJob = DB::table('jobs')->latest('id')->first();
    echo "Queue: {$latestJob->queue}\n";
    echo "Payload: " . substr($latestJob->payload, 0, 200) . "...\n";
} else {
    echo "\n⚠️  No new jobs in queue - job may have run synchronously or failed\n";
}

// Check periods
$tour = \App\Models\Tour::where('wholesaler_id', 6)->orderBy('updated_at', 'desc')->first();
if ($tour) {
    $periods = \App\Models\Period::where('tour_id', $tour->id)->count();
    echo "\nTour ID: {$tour->id}\n";
    echo "Periods: {$periods}\n";
}
