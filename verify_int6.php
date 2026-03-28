<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Tour;
use App\Models\Period;
use App\Models\Offer;
use App\Models\TourItinerary;

$wholesalerId = 6;

echo "=== Integration 6 (GO365) Sync Results ===\n\n";

// Tours
$tours = Tour::where('wholesaler_id', $wholesalerId)->where('data_source', 'api')->get();
echo "Tours: {$tours->count()}\n";

if ($tours->isEmpty()) {
    echo "❌ No tours found! Checking sync logs...\n\n";
    
    $logs = App\Models\SyncLog::where('wholesaler_id', $wholesalerId)
        ->orderByDesc('created_at')
        ->limit(3)
        ->get();
    
    foreach ($logs as $log) {
        echo "SyncLog #{$log->id}: status={$log->status}, type={$log->sync_type}\n";
        echo "  started: {$log->started_at}, ended: {$log->ended_at}\n";
        echo "  tours_fetched: {$log->tours_fetched}, tours_created: {$log->tours_created}, tours_updated: {$log->tours_updated}\n";
        echo "  periods_created: {$log->periods_created}, periods_updated: {$log->periods_updated}\n";
        echo "  errors: {$log->error_count}\n";
        if ($log->error_message) {
            echo "  error_message: {$log->error_message}\n";
        }
        echo "\n";
    }
    
    // Check outbound API logs
    echo "=== Recent Outbound API Logs ===\n";
    $apiLogs = App\Models\OutboundApiLog::where('wholesaler_id', $wholesalerId)
        ->orderByDesc('created_at')
        ->limit(5)
        ->get();
    
    foreach ($apiLogs as $al) {
        echo "  {$al->action} {$al->http_method} {$al->url}\n";
        echo "    status: {$al->response_status}, time: {$al->response_time_ms}ms\n";
        if ($al->error_message) {
            echo "    error: " . substr($al->error_message, 0, 200) . "\n";
        }
        // Show response snippet
        $resp = $al->response_body ?? [];
        if (is_array($resp)) {
            $keys = array_keys($resp);
            echo "    response keys: " . implode(', ', $keys) . "\n";
            if (isset($resp['data']) && is_array($resp['data'])) {
                echo "    data count: " . count($resp['data']) . "\n";
                if (count($resp['data']) > 0) {
                    $first = $resp['data'][0] ?? $resp['data'];
                    echo "    first item keys: " . implode(', ', array_keys(is_array($first) ? $first : [])) . "\n";
                }
            }
        }
        echo "\n";
    }
    
    exit;
}

// Summary
$tourIds = $tours->pluck('id');
$periods = Period::whereIn('tour_id', $tourIds)->get();
$offers = Offer::whereIn('period_id', $periods->pluck('id'))->get();
$itineraries = TourItinerary::whereIn('tour_id', $tourIds)->get();

echo "Periods: {$periods->count()}\n";
echo "Offers: {$offers->count()}\n";
echo "Itineraries: {$itineraries->count()}\n";

// Tours with 0 periods
$toursWithNoPeriods = $tours->filter(fn($t) => $periods->where('tour_id', $t->id)->count() === 0);
echo "\nTours with 0 periods: {$toursWithNoPeriods->count()}\n";

// Sample tours
echo "\n=== Sample Tours (first 5) ===\n";
foreach ($tours->take(5) as $t) {
    $pCount = $periods->where('tour_id', $t->id)->count();
    $oCount = $offers->whereIn('period_id', $periods->where('tour_id', $t->id)->pluck('id'))->count();
    $iCount = $itineraries->where('tour_id', $t->id)->count();
    echo "  [{$t->id}] {$t->title}\n";
    echo "    code={$t->tour_code}, ext={$t->external_id}, periods={$pCount}, offers={$oCount}, itin={$iCount}\n";
    echo "    duration={$t->duration_days}d/{$t->duration_nights}n, country_id={$t->primary_country_id}\n";
    
    // Sample period
    $samplePeriod = $periods->where('tour_id', $t->id)->first();
    if ($samplePeriod) {
        echo "    Sample period: {$samplePeriod->start_date} ~ {$samplePeriod->end_date}, status={$samplePeriod->status}, cap={$samplePeriod->capacity}, avail={$samplePeriod->available}\n";
        $sampleOffer = $offers->where('period_id', $samplePeriod->id)->first();
        if ($sampleOffer) {
            echo "    Sample offer: adult={$sampleOffer->price_adult}, single={$sampleOffer->price_single}\n";
        }
    }
    echo "\n";
}

// Check sync log
echo "=== Latest Sync Log ===\n";
$log = App\Models\SyncLog::where('wholesaler_id', $wholesalerId)
    ->orderByDesc('created_at')
    ->first();

if ($log) {
    echo "Status: {$log->status}\n";
    echo "Tours fetched: {$log->tours_fetched}\n";
    echo "Tours created: {$log->tours_created}\n";
    echo "Tours updated: {$log->tours_updated}\n";
    echo "Tours skipped: {$log->tours_skipped}\n";
    echo "Periods created: {$log->periods_created}\n";
    echo "Periods updated: {$log->periods_updated}\n";
    echo "Errors: {$log->error_count}\n";
    if ($log->error_message) {
        echo "Error: {$log->error_message}\n";
    }
}
