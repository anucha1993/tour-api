<?php
/**
 * Test sync for iTravel headcode adapter (wholesaler_id=35)
 * Run: php test_sync_itravel.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Jobs\SyncToursJob;
use App\Models\WholesalerApiConfig;
use Illuminate\Support\Facades\Log;

$config = WholesalerApiConfig::where('integration_type', 'headcode')
    ->where('headcode_file', 'itravel')
    ->first();

if (!$config) {
    echo "ERROR: headcode itravel config not found\n";
    exit(1);
}

echo "Config found: id={$config->id}, wholesaler_id={$config->wholesaler_id}\n";
echo "Running SyncToursJob (limit=3 via __limit:3__ cursor) synchronously...\n\n";

// Force-release any orphan lock before test
\Illuminate\Support\Facades\Cache::lock("sync_lock:wholesaler:{$config->wholesaler_id}")->forceRelease();
// Also mark any stuck running log as cancelled
\App\Models\SyncLog::where('wholesaler_id', $config->wholesaler_id)
    ->where('status', 'running')
    ->update(['status' => 'failed', 'completed_at' => now(), 'error_summary' => ['message' => 'Cleared before test']]);
echo "Lock released, stuck syncs cleared\n\n";

try {
    $job = new SyncToursJob(
        wholesalerId: $config->wholesaler_id,
        transformedData: null,
        syncType: 'full',
        limit: 3
    );

    // Temporarily enable sync for test
    $config->sync_enabled = true;
    $config->save();
    echo "sync_enabled set to true in DB\n";

    ob_implicit_flush(true);
    echo "Calling handle()...\n";
    $job->handle();
    echo "\nSync completed!\n";
} catch (\Throwable $e) {
    echo "\nERROR [" . get_class($e) . "]: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " line " . $e->getLine() . "\n";
    echo substr($e->getTraceAsString(), 0, 3000) . "\n";
    exit(1);
} finally {
    // Restore sync_enabled
    $config->sync_enabled = false;
    $config->save();
    echo "sync_enabled restored to false\n";
}

// Show results
echo "\n=== SYNC LOG ===\n";
$syncLog = \App\Models\SyncLog::where('wholesaler_id', $config->wholesaler_id)
    ->latest()
    ->first();
if ($syncLog) {
    echo "status        : {$syncLog->status}\n";
    echo "tours_received: {$syncLog->tours_received}\n";
    echo "tours_created : {$syncLog->tours_created}\n";
    echo "tours_updated : {$syncLog->tours_updated}\n";
    echo "tours_skipped : {$syncLog->tours_skipped}\n";
    echo "tours_failed  : {$syncLog->tours_failed}\n";
    echo "error_summary : " . json_encode($syncLog->error_summary, JSON_UNESCAPED_UNICODE) . "\n";
    // Check SyncErrorLog
    $errors = \App\Models\SyncErrorLog::where('sync_log_id', $syncLog->id)->limit(5)->get();
    if ($errors->isNotEmpty()) {
        echo "\n=== SYNC ERRORS (first 5) ===\n";
        foreach ($errors as $err) {
            echo "  [{$err->entity_code}] {$err->error_type}: {$err->error_message}\n";
        }
    }
} else {
    echo "(no sync log found)\n";
}

echo "\n=== RESULTS ===\n";
$tours = \App\Models\Tour::where('wholesaler_id', $config->wholesaler_id)
    ->with(['periods.offer', 'itineraries'])
    ->latest('last_synced_at')
    ->limit(3)
    ->get();

foreach ($tours as $tour) {
    echo "\nTour: [{$tour->tour_code}] {$tour->title}\n";
    echo "  wholesaler_tour_code: {$tour->wholesaler_tour_code}\n";
    echo "  duration: {$tour->duration_days}D {$tour->duration_nights}N\n";
    echo "  transport_id: {$tour->transport_id}\n";
    echo "  cover_image_url: " . ($tour->cover_image_url ? 'SET' : 'NULL') . "\n";
    echo "  pdf_url: " . ($tour->pdf_url ? 'SET' : 'NULL') . "\n";
    echo "  docx_url: " . ($tour->docx_url ? 'SET' : 'NULL') . "\n";
    echo "  departure_airports: " . json_encode($tour->departure_airports) . "\n";
    echo "  min_price: {$tour->min_price}  price_adult: {$tour->price_adult}\n";
    echo "  next_departure_date: {$tour->next_departure_date}  total_departures: {$tour->total_departures}  available_seats: {$tour->available_seats}\n";
    echo "  periods: " . $tour->periods->count() . "\n";
    foreach ($tour->periods as $period) {
        echo "    [{$period->period_code}] {$period->start_date} → {$period->end_date}";
        echo "  capacity={$period->capacity} available={$period->available} status={$period->status}\n";
        if ($period->offer) {
            echo "      adult={$period->offer->price_adult} single={$period->offer->price_single}";
            echo " child_nobed={$period->offer->price_child_nobed} infant={$period->offer->price_infant}";
            echo " joinland={$period->offer->price_joinland} comm_agent={$period->offer->commission_agent}";
            echo " comm_sale={$period->offer->commission_sale} deposit={$period->offer->deposit}\n";
        }
    }
    $itinCount = $tour->itineraries()->count();
    echo "  itineraries: {$itinCount}\n";
    foreach ($tour->itineraries()->orderBy('day_number')->get() as $itin) {
        $hasFood = implode(',', array_filter(['B'=>$itin->has_breakfast,'L'=>$itin->has_lunch,'D'=>$itin->has_dinner], fn($v) => $v));
        echo "    Day{$itin->day_number}: " . mb_substr($itin->title ?? '', 0, 50) . "\n";
        echo "      food=[" . ($itin->has_breakfast?'B':'') . ($itin->has_lunch?'L':'') . ($itin->has_dinner?'D':'') . "]";
        echo " hotel=" . mb_substr($itin->accommodation ?? 'null', 0, 30) . "\n";
    }
}
