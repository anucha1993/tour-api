<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Sync Status Check ===\n\n";

// Count tours
$toursCount = \App\Models\Tour::where('wholesaler_id', 1)->count();
echo "Tours from wholesaler 1: " . $toursCount . PHP_EOL;

// Count periods
$tourIds = \App\Models\Tour::where('wholesaler_id', 1)->pluck('id');
$periodsCount = \App\Models\Period::whereIn('tour_id', $tourIds)->count();
echo "Periods: " . $periodsCount . PHP_EOL;

// Last sync log
echo "\n=== Last 3 Sync Logs ===\n";
$logs = \App\Models\SyncLog::where('wholesaler_id', 1)
    ->orderBy('id', 'desc')
    ->take(3)
    ->get();

foreach ($logs as $log) {
    echo sprintf(
        "[%s] %s - Received: %d, Created: %d, Updated: %d, Failed: %d\n",
        $log->started_at,
        $log->status,
        $log->tours_received ?? 0,
        $log->tours_created ?? 0,
        $log->tours_updated ?? 0,
        $log->tours_failed ?? 0
    );
    
    if ($log->error_summary) {
        echo "  Errors: " . json_encode($log->error_summary) . PHP_EOL;
    }
}

// Check if any tour has data
echo "\n=== Sample Tour Data ===\n";
$tour = \App\Models\Tour::where('wholesaler_id', 1)->first();
if ($tour) {
    echo "Tour: " . $tour->title . PHP_EOL;
    echo "Code: " . $tour->tour_code . PHP_EOL;
    echo "External ID: " . $tour->external_tour_id . PHP_EOL;
    echo "Periods: " . $tour->periods()->count() . PHP_EOL;
} else {
    echo "No tours found for wholesaler 1\n";
}
