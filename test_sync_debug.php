<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Pending Jobs Analysis ===\n\n";

// Count by queue
$queueCounts = DB::table('jobs')
    ->select('queue', DB::raw('count(*) as count'))
    ->groupBy('queue')
    ->get();

echo "Jobs by Queue:\n";
foreach ($queueCounts as $q) {
    echo "  {$q->queue}: {$q->count}\n";
}

// Get sample of job types
echo "\nJob Types (sample):\n";
$sampleJobs = DB::table('jobs')->limit(10)->get();
foreach ($sampleJobs as $job) {
    $payload = json_decode($job->payload, true);
    $displayName = $payload['displayName'] ?? 'Unknown';
    echo "  - {$displayName} (queue: {$job->queue})\n";
}

// Count by job type
echo "\nJobs by Type:\n";
$allJobs = DB::table('jobs')->get();
$jobTypes = [];
foreach ($allJobs as $job) {
    $payload = json_decode($job->payload, true);
    $displayName = $payload['displayName'] ?? 'Unknown';
    $jobTypes[$displayName] = ($jobTypes[$displayName] ?? 0) + 1;
}
arsort($jobTypes);
foreach ($jobTypes as $type => $count) {
    echo "  {$type}: {$count}\n";
}
