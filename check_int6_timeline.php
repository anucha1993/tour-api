<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Check all sync logs for integration 6
echo "=== All sync logs (wholesaler_id=6, last 5) ===\n";
$logs = DB::table('sync_logs')->where('wholesaler_id', 6)->orderByDesc('id')->limit(5)->get();
foreach ($logs as $log) {
    echo "ID:{$log->id} | {$log->status} | rcvd:{$log->tours_received} created:{$log->tours_created} updated:{$log->tours_updated} skipped:{$log->tours_skipped} failed:{$log->tours_failed} | {$log->started_at}\n";
}

// Check tours created around the latest sync time
echo "\n=== Tours created during latest sync (09:06-09:14 UTC) ===\n";
$newTours = DB::table('tours')
    ->where('wholesaler_id', 6)
    ->where('created_at', '>=', '2026-03-28 09:06:00')
    ->where('created_at', '<=', '2026-03-28 09:14:00')
    ->count();
echo "New tours created: {$newTours}\n";

// Check tours updated during latest sync
$updatedTours = DB::table('tours')
    ->where('wholesaler_id', 6)
    ->where('updated_at', '>=', '2026-03-28 09:06:00')
    ->where('updated_at', '<=', '2026-03-28 09:14:00')
    ->count();
echo "Tours updated: {$updatedTours}\n";

// Check total by date range
echo "\n=== Tours created by date ===\n";
$byDate = DB::table('tours')
    ->where('wholesaler_id', 6)
    ->select(DB::raw("DATE(created_at) as dt, count(*) as cnt"))
    ->groupBy(DB::raw("DATE(created_at)"))
    ->orderByDesc('dt')
    ->get();
foreach ($byDate as $row) {
    echo "{$row->dt}: {$row->cnt}\n";
}
