<?php
/**
 * Check integration 25 sync results
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\Period;
use App\Models\SyncLog;

// Get latest sync log for wholesaler 57
$log = SyncLog::where('wholesaler_id', 57)
    ->orderBy('id', 'desc')
    ->first();

if ($log) {
    echo "=== Latest Sync Log ===" . PHP_EOL;
    echo "ID: {$log->id}" . PHP_EOL;
    echo "Status: {$log->status}" . PHP_EOL;
    echo "Sync type: {$log->sync_type}" . PHP_EOL;
    echo "Total received: {$log->total_received}" . PHP_EOL;
    echo "Total created: {$log->total_created}" . PHP_EOL;
    echo "Total updated: {$log->total_updated}" . PHP_EOL;
    echo "Total skipped: {$log->total_skipped}" . PHP_EOL;
    echo "Total errors: {$log->total_errors}" . PHP_EOL;
    echo "Periods created: " . ($log->periods_created ?? 'N/A') . PHP_EOL;
    echo "Periods updated: " . ($log->periods_updated ?? 'N/A') . PHP_EOL;
    echo "Started: {$log->started_at}" . PHP_EOL;
    echo "Completed: {$log->completed_at}" . PHP_EOL;
    echo "Error: " . ($log->error_message ?? 'none') . PHP_EOL;
    if ($log->summary) {
        echo "Summary: " . json_encode($log->summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    }
}

// Check tours created for wholesaler 57
echo PHP_EOL . "=== Tours for wholesaler 57 ===" . PHP_EOL;
$tours = Tour::where('wholesaler_id', 57)->orderBy('id', 'desc')->get();
echo "Total tours: " . $tours->count() . PHP_EOL;

foreach ($tours->take(10) as $t) {
    $periodCount = Period::where('tour_id', $t->id)->count();
    $futurePeriods = Period::where('tour_id', $t->id)
        ->where('start_date', '>=', now()->toDateString())
        ->count();
    $countries = $t->countries()->pluck('name_th')->toArray();
    
    echo PHP_EOL . "  Tour #{$t->id}: {$t->tour_code}" . PHP_EOL;
    echo "    Title: " . mb_substr($t->title, 0, 80) . PHP_EOL;
    echo "    Status: {$t->status}, Source: {$t->data_source}" . PHP_EOL;
    echo "    External ID: {$t->external_id}, WS Code: {$t->wholesaler_tour_code}" . PHP_EOL;
    echo "    Duration: {$t->duration_days} days / {$t->duration_nights} nights" . PHP_EOL;
    echo "    Country: " . ($t->primary_country_id ?? 'null') . " | Countries: " . implode(', ', $countries) . PHP_EOL;
    echo "    Transport: {$t->transport_id}" . PHP_EOL;
    echo "    Cover: " . mb_substr($t->cover_image_url ?? 'null', 0, 60) . PHP_EOL;
    echo "    Periods: {$periodCount} total, {$futurePeriods} future" . PHP_EOL;
    
    // Show first period
    $firstPeriod = Period::where('tour_id', $t->id)
        ->where('start_date', '>=', now()->toDateString())
        ->orderBy('start_date')
        ->first();
    if ($firstPeriod) {
        echo "    First period: {$firstPeriod->start_date} - {$firstPeriod->end_date}" . PHP_EOL;
        echo "      Price: {$firstPeriod->price_adult} / Cap: {$firstPeriod->capacity} / Avail: {$firstPeriod->available}" . PHP_EOL;
        echo "      Commission: agent={$firstPeriod->commission_agent}, sale={$firstPeriod->commission_sale}" . PHP_EOL;
    }
}
