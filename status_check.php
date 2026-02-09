<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "======= SYNC STATUS CHECK =======" . PHP_EOL;
echo "Time: " . now()->toDateTimeString() . PHP_EOL . PHP_EOL;

// Latest sync logs for integration 5
echo "--- Last 5 sync_logs (integration 5) ---" . PHP_EOL;
$logs = DB::table('sync_logs')->where('wholesaler_id', 5)->orderByDesc('id')->limit(5)->get();
foreach ($logs as $log) {
    $age = $log->last_heartbeat_at ? now()->diffInSeconds($log->last_heartbeat_at) : '?';
    echo "#{$log->id} | {$log->status} | {$log->sync_type} | {$log->processed_items}/{$log->total_items} | started: {$log->started_at} | hb: {$log->last_heartbeat_at} ({$age}s ago)" . PHP_EOL;
    if ($log->error_summary) echo "  error: {$log->error_summary}" . PHP_EOL;
}

echo PHP_EOL . "--- Jobs table ---" . PHP_EOL;
$jobs = DB::table('jobs')->get();
echo "Count: {$jobs->count()}" . PHP_EOL;
foreach ($jobs as $j) {
    $p = json_decode($j->payload, true);
    echo "  #{$j->id} | {$p['displayName']} | attempts: {$j->attempts} | reserved: " . ($j->reserved_at ? date('Y-m-d H:i:s', $j->reserved_at) : 'no') . PHP_EOL;
}

echo PHP_EOL . "--- Failed jobs ---" . PHP_EOL;
$fj = DB::table('failed_jobs')->orderByDesc('id')->limit(5)->get();
echo "Count: {$fj->count()}" . PHP_EOL;
foreach ($fj as $f) {
    echo "  #{$f->id} | {$f->failed_at} | " . substr($f->exception, 0, 200) . PHP_EOL;
}

echo PHP_EOL . "--- Cache locks ---" . PHP_EOL;
$locks = DB::table('cache')->where('key', 'like', '%sync_lock%')->get();
echo "Count: {$locks->count()}" . PHP_EOL;
foreach ($locks as $l) {
    $exp = date('Y-m-d H:i:s', $l->expiration);
    $expired = $l->expiration < time() ? ' (EXPIRED)' : '';
    echo "  {$l->key} | expires: {$exp}{$expired}" . PHP_EOL;
}
